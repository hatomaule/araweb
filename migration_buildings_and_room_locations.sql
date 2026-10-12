USE manajemen_kos;

CREATE TABLE IF NOT EXISTS kos_buildings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    address TEXT NOT NULL,
    latitude DECIMAL(10, 7) NULL,
    longitude DECIMAL(10, 7) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kos_buildings_name (name),
    CONSTRAINT chk_kos_buildings_latitude
        CHECK (latitude IS NULL OR latitude BETWEEN -90 AND 90),
    CONSTRAINT chk_kos_buildings_longitude
        CHECK (longitude IS NULL OR longitude BETWEEN -180 AND 180),
    CONSTRAINT chk_kos_buildings_coordinates_pair
        CHECK ((latitude IS NULL AND longitude IS NULL) OR (latitude IS NOT NULL AND longitude IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO kos_buildings (name, address)
SELECT 'Tiara Kos - Lengkapi lokasi', 'Alamat dan titik peta gedung lama belum diatur.'
WHERE NOT EXISTS (
    SELECT 1 FROM kos_buildings WHERE name = 'Tiara Kos - Lengkapi lokasi'
);

ALTER TABLE rooms
    ADD COLUMN building_id BIGINT UNSIGNED NULL AFTER id,
    ADD KEY idx_rooms_building_status (building_id, status);

UPDATE rooms
SET building_id = (SELECT id FROM kos_buildings WHERE name = 'Tiara Kos - Lengkapi lokasi')
WHERE building_id IS NULL;

ALTER TABLE rooms
    MODIFY building_id BIGINT UNSIGNED NOT NULL,
    DROP INDEX uq_rooms_room_number,
    ADD UNIQUE KEY uq_rooms_building_room_number (building_id, room_number),
    ADD CONSTRAINT fk_rooms_building
        FOREIGN KEY (building_id) REFERENCES kos_buildings (id)
        ON UPDATE CASCADE ON DELETE RESTRICT;
