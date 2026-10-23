USE manajemen_kos;

CREATE TABLE IF NOT EXISTS chat_conversation_hidden (
    conversation_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    hidden_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (conversation_id, user_id),
    KEY idx_chat_hidden_user (user_id, conversation_id),
    CONSTRAINT fk_chat_hidden_conversation
        FOREIGN KEY (conversation_id) REFERENCES chat_conversations (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_chat_hidden_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
