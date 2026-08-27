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
