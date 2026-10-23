USE manajemen_kos;

CREATE TABLE IF NOT EXISTS chat_conversation_user_state (
    conversation_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active', 'archived', 'trash', 'purged') NOT NULL DEFAULT 'active',
    trashed_at DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (conversation_id, user_id),
    KEY idx_chat_user_state_folder (user_id, status, trashed_at, conversation_id),
    CONSTRAINT fk_chat_user_state_conversation
        FOREIGN KEY (conversation_id) REFERENCES chat_conversations (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_chat_user_state_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO chat_conversation_user_state (conversation_id, user_id, status)
SELECT conversation_id, user_id, 'archived'
FROM chat_conversation_hidden
ON DUPLICATE KEY UPDATE
    status=IF(chat_conversation_user_state.status='active', 'archived', chat_conversation_user_state.status);

INSERT INTO chat_conversation_user_state (conversation_id, user_id, status)
SELECT conversation_id, user_id, 'archived'
FROM (
    SELECT c.id AS conversation_id, c.archived_by AS user_id
    FROM chat_conversations c
    WHERE c.archived_at IS NOT NULL AND c.archived_by IS NOT NULL
) legacy_archives
ON DUPLICATE KEY UPDATE
    status=IF(chat_conversation_user_state.status='active', 'archived', chat_conversation_user_state.status);

-- Expire each user's trash independently, then delete only conversations whose
-- participant and explicitly tracked admin copies have all been purged.
-- Enable MySQL's event_scheduler for unattended daily expiry; application chat
-- requests perform the same expiry and cleanup as a fallback.
DELIMITER //
CREATE EVENT IF NOT EXISTS chat_purge_expired_trash_event
    ON SCHEDULE EVERY 1 DAY
    STARTS CURRENT_TIMESTAMP + INTERVAL 1 DAY
    DO BEGIN
        UPDATE chat_conversation_user_state
           SET status='purged', trashed_at=NULL
           WHERE status='trash'
             AND trashed_at <= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY);

        DELETE c FROM chat_conversations c
        LEFT JOIN kos_buildings b ON b.id=c.building_id
        WHERE (
            (c.conversation_type='property'
                AND EXISTS (SELECT 1 FROM chat_conversation_user_state ss
                    WHERE ss.conversation_id=c.id AND ss.user_id=c.seeker_user_id AND ss.status='purged')
                AND EXISTS (SELECT 1 FROM chat_conversation_user_state os
                    WHERE os.conversation_id=c.id AND os.user_id=b.owner_user_id AND os.status='purged'))
            OR
            (c.conversation_type='support'
                AND EXISTS (SELECT 1 FROM chat_conversation_user_state rs
                    WHERE rs.conversation_id=c.id AND rs.user_id=c.requester_user_id AND rs.status='purged'))
        )
        AND NOT EXISTS (SELECT 1 FROM chat_conversation_user_state s
            WHERE s.conversation_id=c.id AND s.status<>'purged');
    END//
DELIMITER ;
