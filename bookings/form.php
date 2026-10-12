<?php
declare(strict_types=1);

$bookingForms = [
    'booking_form' => ['seeker', __DIR__ . '/seeker_form.php'],
    'my_bookings' => ['seeker', __DIR__ . '/seeker_list.php'],
    'operator_bookings' => ['operator', __DIR__ . '/operator_list.php'],
];

if (!isset($bookingForms[$page]) || $user['role'] !== $bookingForms[$page][0]) {
    throw new LogicException('Booking module received an unsupported route.');
}

require $bookingForms[$page][1];
