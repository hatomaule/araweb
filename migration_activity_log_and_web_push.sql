USE manajemen_kos;

CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id BIGINT UNSIGNED NULL,
    actor_role ENUM('admin', 'operator', 'seeker', 'system') NOT NULL DEFAULT 'system',
    category VARCHAR(32) NOT NULL,
    action VARCHAR(80) NOT NULL,
    outcome ENUM('success', 'error') NOT NULL DEFAULT 'success',
    page VARCHAR(80) NULL,
    entity_type VARCHAR(40) NULL,
    entity_id BIGINT UNSIGNED NULL,
    building_id BIGINT UNSIGNED NULL,
    summary VARCHAR(240) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activity_logs_created (created_at, id),
    KEY idx_activity_logs_role_created (actor_role, created_at, id),
    KEY idx_activity_logs_actor_created (actor_user_id, created_at, id),
    KEY idx_activity_logs_building_created (building_id, created_at, id),
    KEY idx_activity_logs_outcome_created (outcome, created_at, id),
    CONSTRAINT fk_activity_logs_actor
        FOREIGN KEY (actor_user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_activity_logs_building
        FOREIGN KEY (building_id) REFERENCES kos_buildings (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS web_push_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    endpoint TEXT NOT NULL,
    endpoint_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    p256dh_key VARCHAR(128) CHARACTER SET ascii NOT NULL,
    auth_secret VARCHAR(64) CHARACTER SET ascii NOT NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_web_push_endpoint_hash (endpoint_hash),
    KEY idx_web_push_user (user_id, id),
    CONSTRAINT fk_web_push_subscription_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
