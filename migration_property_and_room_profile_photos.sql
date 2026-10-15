USE manajemen_kos;

ALTER TABLE kos_buildings
    ADD COLUMN profile_photo_path VARCHAR(255) NULL AFTER description;

ALTER TABLE rooms
    ADD COLUMN profile_photo_path VARCHAR(255) NULL AFTER room_type;
