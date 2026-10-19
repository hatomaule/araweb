<?php
declare(strict_types=1);

$adminForms = [
    'platform_dashboard' => __DIR__ . '/dashboard/form.php',
    'platform_owners' => __DIR__ . '/userkost/form.php',
    'platform_accounts' => __DIR__ . '/accounts.php',
    'platform_operator_accounts' => __DIR__ . '/accounts.php',
    'platform_seeker_accounts' => __DIR__ . '/accounts.php',
    'platform_unhoused_users' => __DIR__ . '/accounts.php',
    'platform_operator_profile' => __DIR__ . '/operator_profile.php',
    'platform_user_profile' => __DIR__ . '/operator_profile.php',
    'platform_user_payments' => __DIR__ . '/user_payments.php',
    'platform_listings' => __DIR__ . '/datakost/form.php',
    'platform_property_verification' => __DIR__ . '/property_verification.php',
    'platform_reports' => dirname(__DIR__) . '/reports/overview.php',
];

if ($page === 'platform_dashboard' && $user['role'] === 'admin') {
    require __DIR__ . '/dashboard/form.php';
    return;
}

if (!isset($adminForms[$page]) || $user['role'] !== 'admin') {
    throw new LogicException('Admin page module received an unsupported route.');
}

require $adminForms[$page];
