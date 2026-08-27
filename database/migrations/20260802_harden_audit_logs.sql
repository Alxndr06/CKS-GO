ALTER TABLE logs
    ADD COLUMN ip_address VARCHAR(45) NULL AFTER details,
    ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip_address,
    ADD COLUMN request_id CHAR(32) NULL AFTER user_agent,
    ADD KEY idx_logs_request_id (request_id),
    ADD KEY idx_logs_admin_created (admin_id, created_at);
