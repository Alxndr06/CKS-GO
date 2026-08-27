-- ============================================================================
-- CKS GO - Migration definitive du dump historique du 31/07/2026
-- Version cible : application au 02/08/2026
-- SGBD valide : MariaDB 10.4+ (teste sur 10.4.32 ; dump source 10.11.18)
--
-- MODE D'EMPLOI
-- 1. Sauvegarder la base avec mysqldump.
-- 2. Selectionner explicitement la base a migrer avant d'executer ce fichier.
-- 3. Le script refuse toute base deja migree ou partiellement migree avant le
--    premier DDL. Toute la migration est encapsulee dans une procedure afin
--    que le SIGNAL de garde interrompe reellement l'execution.
-- 4. Les DDL MariaDB provoquent des validations implicites : une restauration du
--    dump est le seul retour arriere complet en cas d'interruption.
-- ============================================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `_cksgo_migrate_legacy_20260802`;
DELIMITER //
CREATE PROCEDURE `_cksgo_migrate_legacy_20260802`()
migration: BEGIN
    DECLARE legacy_tables_count INT DEFAULT 0;
    DECLARE anomaly_count BIGINT DEFAULT 0;
    DECLARE target_tables_count INT DEFAULT 0;
    DECLARE target_columns_count INT DEFAULT 0;
    DECLARE target_constraints_count INT DEFAULT 0;
    DECLARE target_indexes_count INT DEFAULT 0;

    IF DATABASE() IS NULL OR DATABASE() = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CKS GO : selectionnez explicitement la base a migrer.';
    END IF;

    SELECT COUNT(*)
    INTO legacy_tables_count
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_TYPE = 'BASE TABLE'
      AND TABLE_NAME IN (
          'alerts', 'alert_events', 'carts', 'cart_items', 'categories',
          'events', 'inventory_movements', 'invoices', 'logs', 'news',
          'orders', 'order_addresses', 'order_items', 'order_taxes',
          'payments', 'products', 'product_categories', 'product_images',
          'product_variants', 'promotions', 'promotion_redemptions',
          'refunds', 'settings', 'tickets', 'ticket_messages', 'users',
          'variant_attributes'
      );

    IF legacy_tables_count <> 27 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CKS GO : le schema historique attendu est incomplet.';
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (
            'payment_batches',
            'user_permission_overrides',
            'access_bans',
            'alert_refunds',
            'alert_items'
        )
    ) OR EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'logs'
          AND COLUMN_NAME = 'request_id'
    ) THEN
        SELECT COUNT(*)
        INTO target_tables_count
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE';

        SELECT COUNT(*)
        INTO target_columns_count
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE();

        SELECT COUNT(*)
        INTO target_constraints_count
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE();

        SELECT COUNT(DISTINCT CONCAT(TABLE_NAME, '|', INDEX_NAME))
        INTO target_indexes_count
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE();

        IF target_tables_count = 33
           AND target_columns_count = 292
           AND target_constraints_count = 118
           AND target_indexes_count = 143 THEN
            SELECT
                'ALREADY_CURRENT' AS statut,
                'CKS GO : schema deja a jour, aucune modification effectuee.' AS resultat;
            LEAVE migration;
        END IF;

        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CKS GO : schema partiellement migre ; restaurez une sauvegarde propre avant de poursuivre.';
    END IF;

    SELECT
        (SELECT COUNT(*) FROM product_variants WHERE stock_quantity < 0 OR price < 0)
        + (SELECT COUNT(*) FROM cart_items WHERE quantity <= 0)
        + (SELECT COUNT(*) FROM order_items WHERE quantity <= 0 OR unit_price < 0)
        + (SELECT COUNT(*) FROM payments WHERE amount_paid < 0)
        + (SELECT COUNT(*) FROM refunds WHERE quantity_refunded <= 0 OR amount < 0)
    INTO anomaly_count;

    IF anomaly_count > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CKS GO : donnees incompatibles avec les contraintes cibles ; corrigez les anomalies avant migration.';
    END IF;

-- ============================================================================
-- Source integree : database/migrations/20260731_add_staff_roles.sql
-- ============================================================================
-- Ajoute la hierarchie CKS GO sans invalider les anciennes valeurs helper/mod.
ALTER TABLE users
    MODIFY role ENUM(
        'user',
        'helper',
        'mod',
        'assistant',
        'gestionnaire',
        'responsable',
        'admin'
    ) NOT NULL DEFAULT 'user';

UPDATE users SET role = 'assistant' WHERE role = 'helper';
UPDATE users SET role = 'gestionnaire' WHERE role = 'mod';

ALTER TABLE users
    MODIFY role ENUM(
        'user',
        'assistant',
        'gestionnaire',
        'responsable',
        'admin'
    ) NOT NULL DEFAULT 'user';

-- ============================================================================
-- Source integree : database/migrations/20260731_add_user_permission_overrides.sql
-- ============================================================================
CREATE TABLE IF NOT EXISTS user_permission_overrides (
    user_id INT NOT NULL,
    permission VARCHAR(80) NOT NULL,
    effect ENUM('allow', 'deny') NOT NULL,
    granted_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, permission),
    KEY idx_user_permission_effect (effect),
    KEY idx_user_permission_granted_by (granted_by),
    CONSTRAINT fk_user_permission_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_permission_granted_by
        FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Source integree : database/migrations/20260731_add_financial_ledger.sql
-- ============================================================================
CREATE TABLE payment_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NULL,
    admin_id INT NULL,
    idempotency_key CHAR(64) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    allocated_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    unallocated_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    balance_before DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    method VARCHAR(30) NOT NULL,
    provider VARCHAR(50) NULL,
    provider_ref VARCHAR(100) NULL,
    status ENUM('captured', 'partially_refunded', 'refunded', 'voided') NOT NULL DEFAULT 'captured',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_batches_idempotency (idempotency_key),
    KEY idx_payment_batches_user_date (user_id, created_at),
    KEY idx_payment_batches_admin (admin_id),
    CONSTRAINT fk_payment_batches_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_payment_batches_admin
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE payments
    ADD COLUMN batch_id BIGINT UNSIGNED NULL AFTER id,
    ADD KEY idx_payments_batch_id (batch_id),
    ADD CONSTRAINT fk_payments_batch
        FOREIGN KEY (batch_id) REFERENCES payment_batches(id) ON DELETE SET NULL;

CREATE TABLE user_balance_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NULL,
    admin_id INT NULL,
    order_id INT NULL,
    payment_batch_id BIGINT UNSIGNED NULL,
    movement_key VARCHAR(120) NOT NULL,
    movement_type ENUM(
        'opening_balance',
        'order_charge',
        'payment',
        'order_cancellation',
        'credit_refund',
        'manual_adjustment'
    ) NOT NULL,
    amount_delta DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_balance_movement_key (movement_key),
    KEY idx_user_balance_movements_user_date (user_id, created_at),
    KEY idx_user_balance_movements_order (order_id),
    KEY idx_user_balance_movements_batch (payment_batch_id),
    KEY idx_user_balance_movements_admin (admin_id),
    CONSTRAINT fk_user_balance_movements_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_user_balance_movements_admin
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_user_balance_movements_order
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_user_balance_movements_batch
        FOREIGN KEY (payment_batch_id) REFERENCES payment_batches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO user_balance_movements (
    user_id,
    movement_key,
    movement_type,
    amount_delta,
    balance_after,
    description
)
SELECT
    u.id,
    CONCAT('opening-balance-user-', u.id),
    'opening_balance',
    u.note,
    u.note,
    'Reprise du solde existant avant activation du journal financier'
FROM users u;

-- ============================================================================
-- Source integree : database/migrations/20260731_improve_communications.sql
-- ============================================================================
ALTER TABLE news
    ADD COLUMN summary VARCHAR(280) NULL AFTER content,
    ADD COLUMN category VARCHAR(40) NOT NULL DEFAULT 'general' AFTER summary,
    ADD COLUMN audience ENUM('all', 'authenticated', 'staff') NOT NULL DEFAULT 'all' AFTER category,
    ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER is_published,
    ADD COLUMN author_id INT NULL AFTER is_pinned,
    ADD COLUMN updated_by_id INT NULL AFTER author_id,
    ADD COLUMN published_at DATETIME NULL AFTER updated_by_id,
    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD KEY idx_news_publication (is_published, is_pinned, published_at),
    ADD KEY idx_news_category (category),
    ADD KEY idx_news_author (author_id),
    ADD KEY idx_news_updated_by (updated_by_id),
    ADD CONSTRAINT fk_news_author
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_news_updated_by
        FOREIGN KEY (updated_by_id) REFERENCES users(id) ON DELETE SET NULL;

UPDATE news
SET published_at = created_at
WHERE is_published = 1
  AND published_at IS NULL;

ALTER TABLE tickets
    ADD COLUMN category ENUM('account', 'order', 'payment', 'shop', 'technical', 'other') NOT NULL DEFAULT 'other' AFTER subject,
    ADD COLUMN assigned_admin_id INT NULL AFTER priority,
    ADD COLUMN first_response_at DATETIME NULL AFTER assigned_admin_id,
    ADD COLUMN closed_by_admin_id INT NULL AFTER closed_at,
    ADD KEY idx_tickets_category (category),
    ADD KEY idx_tickets_assignment (assigned_admin_id, status, last_message_at),
    ADD KEY idx_tickets_closed_by (closed_by_admin_id),
    ADD CONSTRAINT fk_tickets_assigned_admin
        FOREIGN KEY (assigned_admin_id) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_tickets_closed_by_admin
        FOREIGN KEY (closed_by_admin_id) REFERENCES users(id) ON DELETE SET NULL;

UPDATE tickets t
INNER JOIN (
    SELECT ticket_id, MIN(created_at) AS first_admin_response_at
    FROM ticket_messages
    WHERE admin_id IS NOT NULL
    GROUP BY ticket_id
) first_response ON first_response.ticket_id = t.id
SET t.first_response_at = first_response.first_admin_response_at
WHERE t.first_response_at IS NULL;

-- ============================================================================
-- Source integree : database/migrations/20260801_improve_catalog_inventory.sql
-- ============================================================================
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL AFTER is_active,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD INDEX IF NOT EXISTS idx_products_archive_state (archived_at, is_active);

ALTER TABLE product_variants
    ADD COLUMN IF NOT EXISTS low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 5 AFTER stock_quantity,
    ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL AFTER is_active,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD INDEX IF NOT EXISTS idx_variants_inventory_state (archived_at, is_active, stock_quantity),
    ADD INDEX IF NOT EXISTS idx_variants_product_archive (product_id, archived_at, sort_order);

UPDATE product_variants
SET sku = CONCAT('CKS-', LPAD(product_id, 4, '0'), '-', LPAD(id, 6, '0'))
WHERE sku IS NULL OR TRIM(sku) = '';

ALTER TABLE inventory_movements
    ADD COLUMN IF NOT EXISTS admin_id INT NULL AFTER variant_id,
    ADD COLUMN IF NOT EXISTS order_id INT NULL AFTER admin_id,
    ADD COLUMN IF NOT EXISTS stock_before INT NULL AFTER qty,
    ADD COLUMN IF NOT EXISTS stock_after INT NULL AFTER stock_before,
    ADD COLUMN IF NOT EXISTS note VARCHAR(255) NULL AFTER meta,
    ADD INDEX IF NOT EXISTS idx_inventory_movements_variant_date (variant_id, created_at),
    ADD INDEX IF NOT EXISTS idx_inventory_movements_admin_date (admin_id, created_at),
    ADD INDEX IF NOT EXISTS idx_inventory_movements_order (order_id);

ALTER TABLE inventory_movements
    MODIFY reason ENUM(
        'sale',
        'refund',
        'manual',
        'adjustment',
        'loss',
        'theft',
        'order_checkout',
        'restock',
        'count',
        'correction',
        'return'
    ) NOT NULL;

ALTER TABLE order_items
    ADD COLUMN IF NOT EXISTS product_name_snapshot VARCHAR(150) NULL AFTER variant_id,
    ADD COLUMN IF NOT EXISTS variant_name_snapshot VARCHAR(180) NULL AFTER product_name_snapshot,
    ADD COLUMN IF NOT EXISTS sku_snapshot VARCHAR(64) NULL AFTER variant_name_snapshot;

UPDATE order_items oi
INNER JOIN products p ON p.id = oi.product_id
LEFT JOIN product_variants pv ON pv.id = oi.variant_id
LEFT JOIN (
    SELECT variant_id, MAX(CASE WHEN attr_name = 'flavor' THEN attr_value END) AS flavor
    FROM variant_attributes
    GROUP BY variant_id
) attrs ON attrs.variant_id = pv.id
SET
    oi.product_name_snapshot = COALESCE(NULLIF(oi.product_name_snapshot, ''), p.name),
    oi.variant_name_snapshot = COALESCE(
        NULLIF(oi.variant_name_snapshot, ''),
        NULLIF(attrs.flavor, ''),
        NULLIF(pv.name, ''),
        'Standard'
    ),
    oi.sku_snapshot = COALESCE(NULLIF(oi.sku_snapshot, ''), pv.sku)
WHERE oi.product_name_snapshot IS NULL
   OR oi.product_name_snapshot = ''
   OR oi.variant_name_snapshot IS NULL
   OR oi.variant_name_snapshot = ''
   OR (oi.sku_snapshot IS NULL AND pv.sku IS NOT NULL);

SET @schema_name = DATABASE();

SET @constraint_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = @schema_name
          AND TABLE_NAME = 'inventory_movements'
          AND CONSTRAINT_NAME = 'fk_inventory_movements_admin'
    ),
    'SELECT 1',
    'ALTER TABLE inventory_movements ADD CONSTRAINT fk_inventory_movements_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL'
);
PREPARE constraint_stmt FROM @constraint_sql;
EXECUTE constraint_stmt;
DEALLOCATE PREPARE constraint_stmt;

SET @constraint_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = @schema_name
          AND TABLE_NAME = 'inventory_movements'
          AND CONSTRAINT_NAME = 'fk_inventory_movements_order'
    ),
    'SELECT 1',
    'ALTER TABLE inventory_movements ADD CONSTRAINT fk_inventory_movements_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL'
);
PREPARE constraint_stmt FROM @constraint_sql;
EXECUTE constraint_stmt;
DEALLOCATE PREPARE constraint_stmt;

-- ============================================================================
-- Source integree : database/migrations/20260802_access_controls_and_product_audiences.sql
-- ============================================================================
ALTER TABLE products
    MODIFY COLUMN visibility ENUM('public', 'authenticated', 'admin_only') NOT NULL DEFAULT 'public';

CREATE TABLE IF NOT EXISTS access_bans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ban_type ENUM('email', 'ip') NOT NULL,
    ban_value VARCHAR(190) NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_access_bans_type_value (ban_type, ban_value),
    KEY idx_access_bans_created_at (created_at),
    KEY idx_access_bans_created_by (created_by),
    CONSTRAINT fk_access_bans_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Source integree : database/migrations/20260802_add_alert_product_refunds.sql
-- ============================================================================
ALTER TABLE alerts
    MODIFY COLUMN source_context ENUM(
        'shop_product',
        'cart',
        'order_success',
        'user_order',
        'admin_manual'
    ) NOT NULL DEFAULT 'shop_product',
    ADD COLUMN order_item_id INT NULL AFTER order_id,
    ADD KEY idx_alerts_order_item_id (order_item_id),
    ADD CONSTRAINT fk_alerts_order_item
        FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE SET NULL;

CREATE TABLE alert_refunds (
    id INT NOT NULL AUTO_INCREMENT,
    alert_id INT NOT NULL,
    refund_id INT NOT NULL,
    order_item_id INT NOT NULL,
    admin_id INT NOT NULL,
    quantity_refunded INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    stock_action ENUM('restock', 'consumed') NOT NULL DEFAULT 'consumed',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alert_refunds_alert (alert_id),
    UNIQUE KEY uq_alert_refunds_refund (refund_id),
    KEY idx_alert_refunds_order_item (order_item_id),
    KEY idx_alert_refunds_admin (admin_id),
    CONSTRAINT fk_alert_refunds_alert
        FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE,
    CONSTRAINT fk_alert_refunds_refund
        FOREIGN KEY (refund_id) REFERENCES refunds(id) ON DELETE RESTRICT,
    CONSTRAINT fk_alert_refunds_order_item
        FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_alert_refunds_admin
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Source integree : database/migrations/20260802_expand_alert_multi_items.sql
-- ============================================================================
CREATE TABLE alert_items (
    alert_id INT NOT NULL,
    order_item_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (alert_id, order_item_id),
    KEY idx_alert_items_order_item (order_item_id),
    CONSTRAINT fk_alert_items_alert
        FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE,
    CONSTRAINT fk_alert_items_order_item
        FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO alert_items (alert_id, order_item_id)
SELECT id, order_item_id
FROM alerts
WHERE order_item_id IS NOT NULL;

ALTER TABLE alert_refunds
    DROP INDEX uq_alert_refunds_alert,
    ADD UNIQUE KEY uq_alert_refunds_alert_item (alert_id, order_item_id);

-- ============================================================================
-- Source integree : database/migrations/20260802_add_integrity_constraints.sql
-- ============================================================================
DELETE FROM carts
WHERE user_id IS NULL
  AND session_id IS NULL;

ALTER TABLE product_variants
    ADD CONSTRAINT chk_product_variants_stock_nonnegative
        CHECK (stock_quantity >= 0),
    ADD CONSTRAINT chk_product_variants_price_nonnegative
        CHECK (price >= 0),
    ADD CONSTRAINT chk_product_variants_low_stock_nonnegative
        CHECK (low_stock_threshold >= 0);

ALTER TABLE cart_items
    ADD CONSTRAINT chk_cart_items_quantity_positive
        CHECK (quantity > 0);

ALTER TABLE order_items
    ADD CONSTRAINT chk_order_items_quantity_positive
        CHECK (quantity > 0),
    ADD CONSTRAINT chk_order_items_price_nonnegative
        CHECK (unit_price >= 0);

ALTER TABLE payments
    ADD CONSTRAINT chk_payments_amount_nonnegative
        CHECK (amount_paid >= 0);

ALTER TABLE refunds
    ADD CONSTRAINT chk_refunds_quantity_positive
        CHECK (quantity_refunded > 0),
    ADD CONSTRAINT chk_refunds_amount_nonnegative
        CHECK (amount >= 0);

ALTER TABLE alert_refunds
    ADD CONSTRAINT chk_alert_refunds_quantity_positive
        CHECK (quantity_refunded > 0),
    ADD CONSTRAINT chk_alert_refunds_amount_nonnegative
        CHECK (amount >= 0);

-- ============================================================================
-- Source integree : database/migrations/20260802_harden_audit_logs.sql
-- ============================================================================
ALTER TABLE logs
    ADD COLUMN ip_address VARCHAR(45) NULL AFTER details,
    ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip_address,
    ADD COLUMN request_id CHAR(32) NULL AFTER user_agent,
    ADD KEY idx_logs_request_id (request_id),
    ADD KEY idx_logs_admin_created (admin_id, created_at);

-- ============================================================================
-- CONTROLES POST-MIGRATION
-- Attendus pour le dump fourni : 33 tables, 292 colonnes, 118 contraintes et
-- 143 index nommes (index primaire et contraintes uniques inclus).
-- ============================================================================

SELECT COUNT(*)
INTO target_tables_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE';

SELECT COUNT(*)
INTO target_columns_count
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE();

SELECT COUNT(*)
INTO target_constraints_count
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE();

SELECT COUNT(DISTINCT CONCAT(TABLE_NAME, '|', INDEX_NAME))
INTO target_indexes_count
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE();

IF target_tables_count <> 33
   OR target_columns_count <> 292
   OR target_constraints_count <> 118
   OR target_indexes_count <> 143 THEN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'CKS GO : schema cible incomplet apres migration ; restaurez la sauvegarde et examinez les erreurs.';
END IF;

SELECT
    DATABASE() AS base_migree,
    target_tables_count AS tables_count,
    target_columns_count AS columns_count,
    target_constraints_count AS constraints_count,
    target_indexes_count AS indexes_count;

SELECT 'stock_negatif' AS controle, COUNT(*) AS anomalies
FROM product_variants WHERE stock_quantity < 0
UNION ALL
SELECT 'prix_variante_negatif', COUNT(*)
FROM product_variants WHERE price < 0
UNION ALL
SELECT 'quantite_panier_invalide', COUNT(*)
FROM cart_items WHERE quantity <= 0
UNION ALL
SELECT 'quantite_commande_invalide', COUNT(*)
FROM order_items WHERE quantity <= 0
UNION ALL
SELECT 'prix_commande_negatif', COUNT(*)
FROM order_items WHERE unit_price < 0
UNION ALL
SELECT 'paiement_negatif', COUNT(*)
FROM payments WHERE amount_paid < 0
UNION ALL
SELECT 'remboursement_invalide', COUNT(*)
FROM refunds WHERE quantity_refunded <= 0 OR amount < 0
UNION ALL
SELECT 'role_hors_referentiel', COUNT(*)
FROM users
WHERE role NOT IN ('user','assistant','gestionnaire','responsable','admin');

SELECT 'CKS GO : migration terminee. Verifiez les compteurs et exigez 0 anomalie.' AS resultat;

END//
DELIMITER ;

CALL `_cksgo_migrate_legacy_20260802`();
DROP PROCEDURE `_cksgo_migrate_legacy_20260802`;
