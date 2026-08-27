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
