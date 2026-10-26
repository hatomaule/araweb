USE manajemen_kos;

ALTER TABLE kos_buildings
    ADD COLUMN publication_status ENUM('draft','pending','published','revision','rejected','suspended')
        NOT NULL DEFAULT 'published',
    ADD COLUMN listing_review_note TEXT NULL,
    ADD COLUMN listing_reviewed_by BIGINT UNSIGNED NULL,
    ADD COLUMN listing_reviewed_at DATETIME NULL,
    ADD COLUMN gold_verification_status ENUM('not_requested','pending','verified','rejected')
        NOT NULL DEFAULT 'not_requested',
    ADD KEY idx_building_publication (publication_status, status),
    ADD KEY idx_building_gold_verification (gold_verification_status, owner_user_id),
    ADD CONSTRAINT fk_building_listing_reviewer
        FOREIGN KEY (listing_reviewed_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS owner_gold_verification_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    building_id BIGINT UNSIGNED NOT NULL,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    evidence_file VARCHAR(255) NOT NULL,
    evidence_mime VARCHAR(100) NOT NULL,
    evidence_original_name VARCHAR(255) NOT NULL,
    owner_note TEXT NULL,
    admin_note TEXT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_owner_gold_status (status, created_at),
    KEY idx_owner_gold_building (building_id, created_at),
    KEY idx_owner_gold_owner (owner_user_id, created_at),
    CONSTRAINT fk_owner_gold_building
        FOREIGN KEY (building_id) REFERENCES kos_buildings (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_owner_gold_owner
        FOREIGN KEY (owner_user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_owner_gold_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
