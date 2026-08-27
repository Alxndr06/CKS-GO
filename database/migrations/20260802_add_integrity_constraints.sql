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
