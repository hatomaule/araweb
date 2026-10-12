<?php
declare(strict_types=1);

$paymentForms = [
    'operator_payment' => __DIR__ . '/operator_input.php',
    'invoices' => __DIR__ . '/invoices.php',
    'invoice_form' => __DIR__ . '/invoice_form.php',
    'payments' => $user['role'] === 'operator'
        ? __DIR__ . '/operator_history.php'
        : __DIR__ . '/history.php',
    'my_payments' => __DIR__ . '/my_history.php',
    'tenant_payments' => __DIR__ . '/tenant_history.php',
    'bank_settings' => __DIR__ . '/bank_settings.php',
];

if (!isset($paymentForms[$page])
    || ($page === 'my_payments' && $user['role'] !== 'seeker')
    || ($page === 'tenant_payments' && !in_array($user['role'], ['admin', 'operator'], true))
    || (in_array($page, ['operator_payment', 'payments', 'invoices', 'invoice_form', 'bank_settings'], true)
        && !in_array($user['role'], ['admin', 'operator'], true))
    ) {
    throw new LogicException('Payment module received an unsupported route.');
}

require $paymentForms[$page];
