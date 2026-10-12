USE manajemen_kos;

ALTER TABLE users
    MODIFY email VARCHAR(255) NULL,
    ADD COLUMN last_seen_at DATETIME NULL AFTER status,
    ADD UNIQUE KEY uq_users_phone (phone);

ALTER TABLE rentals
    ADD COLUMN next_due_date DATE NULL AFTER billing_day,
    ADD COLUMN grace_period_days SMALLINT UNSIGNED NOT NULL DEFAULT 7 AFTER next_due_date,
    ADD COLUMN initial_due_day TINYINT UNSIGNED NULL AFTER grace_period_days;

CREATE TABLE building_bank_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    building_id BIGINT UNSIGNED NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    account_name VARCHAR(150) NOT NULL,
    account_number VARCHAR(60) NOT NULL,
    instructions TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_building_bank_accounts_building_active (building_id, is_active),
    CONSTRAINT fk_building_bank_accounts_building
        FOREIGN KEY (building_id) REFERENCES kos_buildings (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_building_bank_accounts_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO building_bank_accounts
    (building_id, bank_name, account_name, account_number, instructions, is_active, created_by)
SELECT building_id, bank_name, account_name, account_number, instructions, 1, updated_by
FROM bank_transfer_settings
WHERE building_id IS NOT NULL;

CREATE TABLE bookings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    building_id BIGINT UNSIGNED NOT NULL,
    requested_room_type ENUM('Tipe Standart', 'Tipe Premium', 'Tipe Eksklusif') NOT NULL,
    selected_room_id BIGINT UNSIGNED NULL,
    bank_account_id BIGINT UNSIGNED NULL,
    bank_name_snapshot VARCHAR(100) NULL,
    account_name_snapshot VARCHAR(150) NULL,
    account_number_snapshot VARCHAR(60) NULL,
    bank_instructions_snapshot TEXT NULL,
    status ENUM(
        'awaiting_payment', 'proof_pending', 'queued', 'room_offered',
        'alternate_declined', 'converted', 'cancelled', 'expired'
    ) NOT NULL DEFAULT 'awaiting_payment',
    terms_version VARCHAR(40) NOT NULL,
    terms_accepted_at DATETIME NOT NULL,
    queue_expires_at DATETIME NOT NULL,
    rental_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bookings_user_status_created (user_id, status, created_at),
    KEY idx_bookings_building_type_status (building_id, requested_room_type, status),
    KEY idx_bookings_queue_expiration (status, queue_expires_at),
    CONSTRAINT fk_bookings_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_building
        FOREIGN KEY (building_id) REFERENCES kos_buildings (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_selected_room
        FOREIGN KEY (selected_room_id) REFERENCES rooms (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_bookings_bank_account
        FOREIGN KEY (bank_account_id) REFERENCES building_bank_accounts (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_bookings_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_room_offers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id BIGINT UNSIGNED NOT NULL,
    room_id BIGINT UNSIGNED NOT NULL,
    proposed_by BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'accepted', 'rejected', 'withdrawn') NOT NULL DEFAULT 'pending',
    proposed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_booking_room_offers_booking_status (booking_id, status),
    CONSTRAINT fk_booking_room_offers_booking
        FOREIGN KEY (booking_id) REFERENCES bookings (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_booking_room_offers_room
        FOREIGN KEY (room_id) REFERENCES rooms (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_booking_room_offers_proposed_by
        FOREIGN KEY (proposed_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    payment_method ENUM('cash', 'bank_transfer') NOT NULL,
    status ENUM('pending', 'confirmed', 'rejected') NOT NULL DEFAULT 'pending',
    transaction_reference VARCHAR(100) NULL,
    payment_proof_path VARCHAR(255) NULL,
    paid_at DATETIME NOT NULL,
    confirmed_by BIGINT UNSIGNED NULL,
    confirmed_at DATETIME NULL,
    payment_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_booking_payments_booking_status (booking_id, status),
    CONSTRAINT fk_booking_payments_booking
        FOREIGN KEY (booking_id) REFERENCES bookings (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_booking_payments_confirmer
        FOREIGN KEY (confirmed_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_booking_payments_payment
        FOREIGN KEY (payment_id) REFERENCES payments (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE property_activity_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    building_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    visitor_key CHAR(64) NOT NULL,
    event_type ENUM('search_result', 'property_view', 'room_view', 'visit') NOT NULL,
    search_query VARCHAR(255) NULL,
    occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_property_activity_building_event_date (building_id, event_type, occurred_at),
    KEY idx_property_activity_visitor_date (visitor_key, occurred_at),
    CONSTRAINT fk_property_activity_building
        FOREIGN KEY (building_id) REFERENCES kos_buildings (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_property_activity_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
