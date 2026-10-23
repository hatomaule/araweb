USE manajemen_kos;

ALTER TABLE chat_messages
    ADD COLUMN attachment_path VARCHAR(255) NULL AFTER body,
    ADD COLUMN attachment_type ENUM('image','video') NULL AFTER attachment_path,
    ADD COLUMN attachment_mime VARCHAR(80) NULL AFTER attachment_type,
    ADD COLUMN attachment_name VARCHAR(160) NULL AFTER attachment_mime,
    ADD COLUMN attachment_size BIGINT UNSIGNED NULL AFTER attachment_name,
    ADD KEY idx_chat_messages_attachment (attachment_type, id);
