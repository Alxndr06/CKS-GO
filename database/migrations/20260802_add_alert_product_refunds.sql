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
