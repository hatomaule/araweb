<?php
declare(strict_types=1);

$propertyForms = [
    'dashboard' => $user['role'] === 'owner'
        ? __DIR__ . '/operator_dashboard.php'
        : __DIR__ . '/dashboard.php',
    'buildings' => __DIR__ . '/buildings.php',
    'building_form' => __DIR__ . '/building_form.php',
    'gallery' => __DIR__ . '/gallery.php',
    'tenants' => __DIR__ . '/tenants.php',
    'tenant_form' => __DIR__ . '/tenant_form.php',
    'tenant_profile' => __DIR__ . '/tenant_profile.php',
    'rooms' => __DIR__ . '/rooms.php',
    'room_form' => __DIR__ . '/room_form.php',
    'rentals' => __DIR__ . '/rentals.php',
    'rental_form' => __DIR__ . '/rental_form.php',
];

if (!isset($propertyForms[$page]) || !in_array($user['role'], ['admin', 'owner'], true)) {
    throw new LogicException('Property module received an unsupported route.');
}

require $propertyForms[$page];
