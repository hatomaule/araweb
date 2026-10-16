<?php
declare(strict_types=1);

if ($page !== 'platform_reports' || $user['role'] !== 'admin') {
    throw new LogicException('Report module received an unsupported route.');
}

require __DIR__ . '/platform.php';
