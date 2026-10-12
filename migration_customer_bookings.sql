USE manajemen_kos;

ALTER TABLE users
    MODIFY role ENUM('admin', 'operator', 'tenant') NOT NULL DEFAULT 'operator';

ALTER TABLE tenants
    ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER id,
    ADD UNIQUE KEY uq_tenants_user_id (user_id),
    ADD CONSTRAINT fk_tenants_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE rentals
    ADD COLUMN booking_expires_at DATETIME NULL AFTER created_by;

ALTER TABLE payments
    ADD COLUMN payment_proof_path VARCHAR(255) NULL AFTER transaction_reference;

CREATE TABLE IF NOT EXISTS bank_transfer_settings (
    id TINYINT UNSIGNED NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    account_name VARCHAR(150) NOT NULL,
    account_number VARCHAR(60) NOT NULL,
    instructions TEXT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT chk_bank_transfer_settings_singleton CHECK (id = 1),
    CONSTRAINT fk_bank_transfer_settings_updated_by
        FOREIGN KEY (updated_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
