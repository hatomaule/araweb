USE manajemen_kos;

ALTER TABLE users
    ADD COLUMN username VARCHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci NULL AFTER name,
    MODIFY role ENUM('admin', 'operator', 'owner', 'seeker') NOT NULL DEFAULT 'seeker';

UPDATE users
SET username = CONCAT(
    'user',
    CHAR(97 + MOD(id DIV 2481152873203736576, 26)),
    CHAR(97 + MOD(id DIV 95428956661682176, 26)),
    CHAR(97 + MOD(id DIV 3670344487757004, 26)),
    CHAR(97 + MOD(id DIV 141167095653376, 26)),
    CHAR(97 + MOD(id DIV 5429503678976, 26)),
    CHAR(97 + MOD(id DIV 208827064576, 26)),
    CHAR(97 + MOD(id DIV 8031810176, 26)),
    CHAR(97 + MOD(id DIV 308915776, 26)),
    CHAR(97 + MOD(id DIV 11881376, 26)),
    CHAR(97 + MOD(id DIV 456976, 26)),
    CHAR(97 + MOD(id DIV 17576, 26)),
    CHAR(97 + MOD(id DIV 676, 26)),
    CHAR(97 + MOD(id DIV 26, 26)),
    CHAR(97 + MOD(id, 26))
)
WHERE username IS NULL;

UPDATE users SET role='owner' WHERE role='operator';

ALTER TABLE users
    MODIFY username VARCHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    ADD UNIQUE KEY uq_users_username (username),
    MODIFY role ENUM('admin', 'owner', 'seeker') NOT NULL DEFAULT 'seeker';

ALTER TABLE activity_logs
    MODIFY actor_role ENUM('admin', 'operator', 'owner', 'seeker', 'system') NOT NULL DEFAULT 'system';

UPDATE activity_logs SET actor_role='owner' WHERE actor_role='operator';

ALTER TABLE activity_logs
    MODIFY actor_role ENUM('admin', 'owner', 'seeker', 'system') NOT NULL DEFAULT 'system';

ALTER TABLE user_activity_events
    MODIFY account_role ENUM('admin', 'operator', 'owner', 'seeker') NOT NULL;

UPDATE user_activity_events SET account_role='owner' WHERE account_role='operator';

ALTER TABLE user_activity_events
    MODIFY account_role ENUM('admin', 'owner', 'seeker') NOT NULL;
