<?php
declare(strict_types=1);

$portalForms = [
    'search_history' => __DIR__ . '/search_history/form.php',
];

if (!isset($portalForms[$page]) || $user['role'] !== 'seeker') {
    throw new LogicException('Portal page module received an unsupported route.');
}

require $portalForms[$page];
