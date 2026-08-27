-- CKS GO 2.0
-- Autorise les lignes de facturation libres, sans produit ni variante du catalogue.
-- À exécuter une seule fois sur la base de production après la migration du 02/08/2026.

SET @order_items_product_id_type = (
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'order_items'
      AND COLUMN_NAME = 'product_id'
    LIMIT 1
);

SET @alter_order_items_product_id = CONCAT(
    'ALTER TABLE order_items MODIFY COLUMN product_id ',
    @order_items_product_id_type,
    ' NULL'
);

PREPARE alter_order_items_product_id_stmt FROM @alter_order_items_product_id;
EXECUTE alter_order_items_product_id_stmt;
DEALLOCATE PREPARE alter_order_items_product_id_stmt;

ALTER TABLE order_items
    ADD COLUMN line_type VARCHAR(20) NOT NULL DEFAULT 'product' AFTER variant_id,
    ADD CONSTRAINT chk_order_items_line_type
        CHECK (line_type IN ('product', 'custom')),
    ADD CONSTRAINT chk_order_items_line_source
        CHECK (
            (line_type = 'product' AND product_id IS NOT NULL)
            OR
            (line_type = 'custom' AND product_id IS NULL AND variant_id IS NULL)
        );

-- Contrôle attendu : 0 ligne incohérente.
SELECT COUNT(*) AS invalid_billing_lines
FROM order_items
WHERE line_type NOT IN ('product', 'custom')
   OR (line_type = 'product' AND product_id IS NULL)
   OR (line_type = 'custom' AND (product_id IS NOT NULL OR variant_id IS NOT NULL));

