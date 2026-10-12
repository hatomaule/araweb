<?php
declare(strict_types=1);

if ($page !== 'profile' || !$user) {
    throw new LogicException('Account module received an unsupported route.');
}

require __DIR__ . '/profile_form.php';
