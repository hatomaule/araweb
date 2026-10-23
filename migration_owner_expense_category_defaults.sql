USE manajemen_kos;

INSERT IGNORE INTO owner_expense_categories (owner_user_id, name)
SELECT owners.id, defaults.name
FROM users owners
CROSS JOIN (
    SELECT 'Listrik' AS name
    UNION ALL SELECT 'Air'
    UNION ALL SELECT 'Wifi'
) defaults
WHERE owners.role = 'owner';
