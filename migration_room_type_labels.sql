USE manajemen_kos;

ALTER TABLE rooms
    MODIFY room_type ENUM(
        'Silver', 'Diamond', 'Platinum',
        'Tipe Standart', 'Tipe Premium', 'Tipe Eksklusif'
    ) NOT NULL DEFAULT 'Tipe Standart';

UPDATE rooms
SET room_type = CASE LOWER(TRIM(room_type))
    WHEN 'diamond' THEN 'Tipe Premium'
    WHEN 'tipe premium' THEN 'Tipe Premium'
    WHEN 'platinum' THEN 'Tipe Eksklusif'
    WHEN 'tipe eksklusif' THEN 'Tipe Eksklusif'
    WHEN 'silver' THEN 'Tipe Standart'
    WHEN 'tipe standart' THEN 'Tipe Standart'
    ELSE 'Tipe Standart'
END;

ALTER TABLE rooms
    MODIFY room_type ENUM(
        'Tipe Standart', 'Tipe Premium', 'Tipe Eksklusif'
    ) NOT NULL DEFAULT 'Tipe Standart';

UPDATE kos_buildings
SET name = 'Tiara Kost'
WHERE name = 'Tiara Kos -Gambesi';
