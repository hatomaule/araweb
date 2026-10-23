USE manajemen_kos;

CREATE TABLE owner_bank_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    account_name VARCHAR(150) NOT NULL,
    account_number VARCHAR(60) NOT NULL,
    updated_by BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_owner_bank_accounts_owner (owner_user_id, id),
    CONSTRAINT fk_owner_bank_accounts_owner
        FOREIGN KEY (owner_user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_owner_bank_accounts_updated_by
        FOREIGN KEY (updated_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bank_transfer_settings
    ADD COLUMN bank_account_id BIGINT UNSIGNED DEFAULT NULL AFTER building_id,
    ADD KEY idx_bank_transfer_account (bank_account_id),
    ADD CONSTRAINT fk_bank_transfer_account
        FOREIGN KEY (bank_account_id) REFERENCES owner_bank_accounts (id)
        ON UPDATE CASCADE ON DELETE SET NULL;

INSERT INTO owner_bank_accounts
    (owner_user_id, bank_name, account_name, account_number, updated_by, updated_at)
SELECT DISTINCT b.owner_user_id, s.bank_name, s.account_name, s.account_number,
       MIN(s.updated_by), MAX(s.updated_at)
FROM bank_transfer_settings s
JOIN kos_buildings b ON b.id=s.building_id
WHERE b.owner_user_id IS NOT NULL
GROUP BY b.owner_user_id, s.bank_name, s.account_name, s.account_number;

UPDATE bank_transfer_settings s
JOIN kos_buildings b ON b.id=s.building_id
JOIN owner_bank_accounts a
  ON a.owner_user_id=b.owner_user_id
 AND a.bank_name=s.bank_name
 AND a.account_name=s.account_name
 AND a.account_number=s.account_number
SET s.bank_account_id=a.id
WHERE s.bank_account_id IS NULL;
