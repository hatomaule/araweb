USE manajemen_kos;

ALTER TABLE users
    ADD COLUMN gender ENUM('male', 'female') NULL AFTER address;
