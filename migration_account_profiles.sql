USE manajemen_kos;

ALTER TABLE users
    ADD COLUMN address TEXT NULL AFTER phone;
