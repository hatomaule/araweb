USE manajemen_kos;

ALTER TABLE property_reviews
    ADD COLUMN operator_reply TEXT NULL AFTER review_text,
    ADD COLUMN operator_replied_at DATETIME NULL AFTER operator_reply;
