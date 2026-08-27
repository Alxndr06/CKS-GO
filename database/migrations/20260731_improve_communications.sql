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
