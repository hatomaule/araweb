USE manajemen_kos;

CREATE TABLE IF NOT EXISTS room_photos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_id BIGINT UNSIGNED NOT NULL,
    category ENUM('room', 'facility') NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_room_photos_room_category (room_id, category, id),
    CONSTRAINT fk_room_photos_room
        FOREIGN KEY (room_id) REFERENCES rooms (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
