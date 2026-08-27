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
