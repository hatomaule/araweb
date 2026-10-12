USE manajemen_kos;

ALTER TABLE bookings
    ADD COLUMN former_user_id BIGINT UNSIGNED NULL AFTER user_id,
    DROP FOREIGN KEY fk_bookings_user,
    MODIFY user_id BIGINT UNSIGNED NULL,
    ADD CONSTRAINT fk_bookings_user_preserve_history
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE search_history
    ADD COLUMN former_user_id BIGINT UNSIGNED NULL AFTER user_id,
    DROP FOREIGN KEY fk_search_history_user,
    ADD CONSTRAINT fk_search_history_user_preserve_history
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE user_activity_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    former_user_id BIGINT UNSIGNED NULL,
    account_role ENUM('admin', 'operator', 'seeker') NOT NULL,
    occurred_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_user_activity_user_time (user_id, occurred_at),
    KEY idx_user_activity_former_user_time (former_user_id, occurred_at),
    CONSTRAINT fk_user_activity_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
