USE manajemen_kos;

UPDATE rooms
SET status = 'available'
WHERE status = 'inactive';

UPDATE rooms
SET room_type = CASE LOWER(TRIM(COALESCE(room_type, '')))
    WHEN 'diamond' THEN 'Diamond'
    WHEN 'platinum' THEN 'Platinum'
    ELSE 'Silver'
END;

ALTER TABLE rooms
    MODIFY room_type ENUM('Silver', 'Diamond', 'Platinum') NOT NULL DEFAULT 'Silver',
    MODIFY status ENUM('available', 'occupied', 'pending', 'maintenance') NOT NULL DEFAULT 'available';
