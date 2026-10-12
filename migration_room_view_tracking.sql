USE manajemen_kos;

ALTER TABLE property_activity_events
    ADD COLUMN room_id BIGINT UNSIGNED NULL AFTER building_id,
    ADD KEY idx_property_activity_room_event_date (room_id, event_type, occurred_at),
    ADD CONSTRAINT fk_property_activity_room
        FOREIGN KEY (room_id) REFERENCES rooms (id)
        ON UPDATE CASCADE ON DELETE SET NULL;
