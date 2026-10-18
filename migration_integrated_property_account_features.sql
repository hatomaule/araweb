USE manajemen_kos;

ALTER TABLE users
    ADD COLUMN hide_phone_public TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN hide_profile_photo_public TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN self_deactivated_at DATETIME NULL,
    ADD COLUMN deletion_requested_at DATETIME NULL,
    ADD COLUMN deletion_due_at DATETIME NULL,
    ADD KEY idx_users_deletion_due (deletion_due_at);

ALTER TABLE kos_buildings
    ADD COLUMN verification_tier ENUM('basic','premium','exclusive') NOT NULL DEFAULT 'basic',
    ADD COLUMN verification_status ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified',
    ADD KEY idx_building_verification (verification_status, verification_tier);

ALTER TABLE room_type_gallery_photos
    ADD COLUMN room_id BIGINT UNSIGNED NULL AFTER room_type,
    ADD KEY idx_room_type_gallery_room (room_id, category, id),
    ADD CONSTRAINT fk_room_type_gallery_room
        FOREIGN KEY (room_id) REFERENCES rooms (id)
        ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE property_reviews
    MODIFY COLUMN tenant_id BIGINT UNSIGNED NULL,
    MODIFY COLUMN rental_id BIGINT UNSIGNED NULL,
    ADD COLUMN reviewer_user_id BIGINT UNSIGNED NULL AFTER building_id,
    ADD KEY idx_property_reviews_reviewer (reviewer_user_id, building_id),
    ADD CONSTRAINT fk_property_reviews_reviewer
        FOREIGN KEY (reviewer_user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL;

UPDATE property_reviews pr
JOIN tenants t ON t.id=pr.tenant_id
SET pr.reviewer_user_id=t.user_id
WHERE t.user_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS property_verification_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    building_id BIGINT UNSIGNED NOT NULL,
    operator_user_id BIGINT UNSIGNED NOT NULL,
    requested_tier ENUM('premium','exclusive') NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    operator_note TEXT NULL,
    admin_note TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_property_verification_status (status, created_at),
    KEY idx_property_verification_building (building_id, created_at),
    CONSTRAINT fk_property_verification_building
        FOREIGN KEY (building_id) REFERENCES kos_buildings (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_property_verification_operator
        FOREIGN KEY (operator_user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_property_verification_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_user_actions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    building_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    action_type ENUM('saved','reported') NOT NULL,
    report_reason VARCHAR(80) NULL,
    report_details TEXT NULL,
    status ENUM('open','reviewed','resolved','dismissed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_property_user_action (building_id, user_id, action_type),
    KEY idx_property_reports_status (action_type, status, created_at),
    CONSTRAINT fk_property_user_actions_building
        FOREIGN KEY (building_id) REFERENCES kos_buildings (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_property_user_actions_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
