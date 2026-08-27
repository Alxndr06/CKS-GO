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
