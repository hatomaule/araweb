USE manajemen_kos;

ALTER TABLE chat_conversations
    MODIFY seeker_user_id BIGINT UNSIGNED NULL,
    MODIFY building_id BIGINT UNSIGNED NULL,
    ADD COLUMN conversation_type ENUM('property', 'support') NOT NULL DEFAULT 'property' AFTER id,
    ADD COLUMN requester_user_id BIGINT UNSIGNED NULL AFTER seeker_user_id,
    ADD COLUMN archived_at DATETIME NULL AFTER last_message_at,
    ADD COLUMN archived_by BIGINT UNSIGNED NULL AFTER archived_at,
    ADD UNIQUE KEY uq_chat_conversations_support_requester (conversation_type, requester_user_id),
    ADD KEY idx_chat_conversations_admin_review (conversation_type, archived_at, last_message_at, id),
    ADD KEY idx_chat_conversations_requester (requester_user_id, archived_at, last_message_at),
    ADD CONSTRAINT fk_chat_conversations_requester
        FOREIGN KEY (requester_user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    ADD CONSTRAINT fk_chat_conversations_archived_by
        FOREIGN KEY (archived_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL;
