USE manajemen_kos;

CREATE TABLE IF NOT EXISTS room_type_gallery_photos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    building_id BIGINT UNSIGNED NOT NULL,
    room_type VARCHAR(100) NOT NULL,
    category ENUM('building', 'room', 'room_facility', 'general_facility') NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_room_type_gallery_lookup (building_id, room_type, category, id),
    KEY idx_room_type_gallery_building (building_id, category, id),
    CONSTRAINT fk_room_type_gallery_building
        FOREIGN KEY (building_id) REFERENCES kos_buildings (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
