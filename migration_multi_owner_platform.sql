USE manajemen_kos;

ALTER TABLE users
    MODIFY role ENUM('admin', 'operator', 'tenant', 'seeker') NOT NULL DEFAULT 'seeker',
    ADD COLUMN phone VARCHAR(30) NULL AFTER email,
    ADD COLUMN profile_photo_path VARCHAR(255) NULL AFTER phone,
    ADD COLUMN avatar_key VARCHAR(40) NULL AFTER profile_photo_path;

UPDATE users SET role='seeker' WHERE role='tenant';

ALTER TABLE users
    MODIFY role ENUM('admin', 'operator', 'seeker') NOT NULL DEFAULT 'seeker';

ALTER TABLE kos_buildings
    ADD COLUMN owner_user_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN description TEXT NULL AFTER address,
    ADD COLUMN gender_policy ENUM('mixed', 'male', 'female') NOT NULL DEFAULT 'mixed' AFTER description,
    ADD COLUMN general_facilities TEXT NULL AFTER gender_policy,
    ADD COLUMN house_rules TEXT NULL AFTER general_facilities,
    ADD COLUMN approval_status ENUM('pending', 'revision', 'approved', 'rejected') NOT NULL DEFAULT 'pending' AFTER status,
    ADD COLUMN approval_note TEXT NULL AFTER approval_status,
    ADD COLUMN reviewed_by BIGINT UNSIGNED NULL AFTER approval_note,
    ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by,
    ADD KEY idx_kos_buildings_owner_status (owner_user_id, status, approval_status),
    ADD CONSTRAINT fk_kos_buildings_owner
        FOREIGN KEY (owner_user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_kos_buildings_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL;

UPDATE kos_buildings SET approval_status='approved', reviewed_at=NOW();

ALTER TABLE bank_transfer_settings
    ADD COLUMN building_id BIGINT UNSIGNED NULL AFTER id,
    MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    DROP CONSTRAINT chk_bank_transfer_settings_singleton,
    ADD UNIQUE KEY uq_bank_transfer_building (building_id),
    ADD CONSTRAINT fk_bank_transfer_building
        FOREIGN KEY (building_id) REFERENCES kos_buildings (id)
        ON UPDATE CASCADE ON DELETE CASCADE;

UPDATE bank_transfer_settings
SET building_id=(SELECT id FROM kos_buildings WHERE owner_user_id IS NULL ORDER BY id LIMIT 1)
WHERE building_id IS NULL;

ALTER TABLE rentals
    ADD COLUMN booking_status ENUM('not_applicable', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'not_applicable' AFTER booking_expires_at,
    ADD COLUMN booking_decided_by BIGINT UNSIGNED NULL AFTER booking_status,
    ADD COLUMN booking_decided_at DATETIME NULL AFTER booking_decided_by,
    ADD COLUMN booking_decision_note TEXT NULL AFTER booking_decided_at,
    ADD KEY idx_rentals_booking_status (booking_status, status),
    ADD CONSTRAINT fk_rentals_booking_decider
        FOREIGN KEY (booking_decided_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS property_photos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    building_id BIGINT UNSIGNED NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_property_photos_building_order (building_id, display_order, id),
    CONSTRAINT fk_property_photos_building
        FOREIGN KEY (building_id) REFERENCES kos_buildings (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    building_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    rental_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    review_text TEXT NOT NULL,
    status ENUM('published', 'hidden') NOT NULL DEFAULT 'published',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_property_reviews_rental (rental_id),
    KEY idx_property_reviews_building_status (building_id, status, created_at),
    CONSTRAINT fk_property_reviews_building
        FOREIGN KEY (building_id) REFERENCES kos_buildings (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_property_reviews_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_property_reviews_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_property_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_auth_providers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(30) NOT NULL,
    provider_user_id VARCHAR(191) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_provider_subject (provider, provider_user_id),
    UNIQUE KEY uq_auth_user_provider (user_id, provider),
    CONSTRAINT fk_auth_provider_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS search_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    search_query VARCHAR(255) NOT NULL,
    searched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_search_history_user_time (user_id, searched_at),
    CONSTRAINT fk_search_history_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
