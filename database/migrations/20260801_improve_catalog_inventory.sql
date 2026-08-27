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
