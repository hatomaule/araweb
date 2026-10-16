<?php
declare(strict_types=1);

$roomScope = $user['role'] === 'operator' ? 'WHERE b.owner_user_id=:owner_id' : '';
$roomParameters = $user['role'] === 'operator' ? ['owner_id' => $user['id']] : [];
$list = rows(
    $pdo,
    "SELECT rm.*,b.name AS building_name,
        (SELECT COUNT(*) FROM rentals r WHERE r.room_id=rm.id
            AND r.status='active' AND r.start_date<=CURDATE()
            AND (r.end_date IS NULL OR r.end_date>=CURDATE())) AS occupied,
        (SELECT COUNT(*) FROM rentals r WHERE r.room_id=rm.id
            AND r.status='upcoming' AND (r.end_date IS NULL OR r.end_date>=CURDATE())
            AND (r.booking_expires_at IS NULL OR r.booking_expires_at>NOW()
                 OR EXISTS (SELECT 1 FROM invoices i JOIN payments p ON p.invoice_id=i.id
                            WHERE i.rental_id=r.id AND p.status='pending'))) AS pending,
        (SELECT COUNT(*) FROM rentals r WHERE r.room_id=rm.id
            AND r.status='upcoming' AND r.booking_status='approved'
            AND (r.end_date IS NULL OR r.end_date>=CURDATE())
            AND (r.booking_expires_at IS NULL OR r.booking_expires_at>NOW()
                 OR EXISTS (SELECT 1 FROM invoices i JOIN payments p ON p.invoice_id=i.id
                            WHERE i.rental_id=r.id AND p.status='pending'))) AS approved_booking
     FROM rooms rm JOIN kos_buildings b ON b.id=rm.building_id
     {$roomScope}
     ORDER BY b.name,rm.room_number",
    $roomParameters
);

$residentsByRoom = [];
$activeResidents = rows(
    $pdo,
    "SELECT r.id AS rental_id,r.room_id,t.id AS tenant_id,t.full_name,t.phone,
            u.id AS user_id,u.gender,u.profile_photo_path,u.avatar_key
     FROM rentals r
     JOIN tenants t ON t.id=r.tenant_id
     LEFT JOIN users u ON u.id=t.user_id AND u.role='seeker'
     JOIN rooms rm ON rm.id=r.room_id
     JOIN kos_buildings b ON b.id=rm.building_id
     WHERE r.status='active' AND r.start_date<=CURDATE()
       AND (r.end_date IS NULL OR r.end_date>=CURDATE())
       AND (:is_admin=1 OR b.owner_user_id=:owner_id)
     ORDER BY t.full_name",
    ['is_admin' => $user['role'] === 'admin' ? 1 : 0, 'owner_id' => $user['id']]
);
foreach ($activeResidents as $resident) {
    $residentsByRoom[(int) $resident['room_id']][] = $resident;
}
$bookingsByRoom = [];
$approvedBookings = rows(
    $pdo,
    "SELECT r.id AS rental_id,r.room_id,r.start_date,t.full_name,t.phone,
            u.id AS user_id,u.gender,u.profile_photo_path,u.avatar_key,
            (SELECT p.status FROM invoices i JOIN payments p ON p.invoice_id=i.id
             WHERE i.rental_id=r.id
             ORDER BY p.created_at DESC,p.id DESC LIMIT 1) AS latest_payment_status
     FROM rentals r
     JOIN tenants t ON t.id=r.tenant_id
     LEFT JOIN users u ON u.id=t.user_id AND u.role='seeker'
     JOIN rooms rm ON rm.id=r.room_id
     JOIN kos_buildings b ON b.id=rm.building_id
     WHERE r.status='upcoming' AND r.booking_status='approved'
       AND (r.end_date IS NULL OR r.end_date>=CURDATE())
       AND (r.booking_expires_at IS NULL OR r.booking_expires_at>NOW()
            OR EXISTS (SELECT 1 FROM invoices i JOIN payments p ON p.invoice_id=i.id
                       WHERE i.rental_id=r.id AND p.status='pending'))
       AND (:is_admin=1 OR b.owner_user_id=:owner_id)
     ORDER BY r.start_date,t.full_name",
    ['is_admin' => $user['role'] === 'admin' ? 1 : 0, 'owner_id' => $user['id']]
);
foreach ($approvedBookings as $booking) {
    $bookingsByRoom[(int) $booking['room_id']][] = $booking;
}
?>
<div class="page-actions">
    <p>Penghuni aktif ditampilkan per kamar. Kelola akun penghuni dari menu Penghuni.</p>
    <a class="button primary" href="index.php?page=room_form">＋ Tambah kamar</a>
</div>
<div class="panel">
    <div class="table-scroll">
        <table class="room-management-table" data-building-filter>
            <thead><tr><th>Gedung / kamar</th><th>Tipe / lantai</th><th>Tarif bulanan</th><th>Penghuni</th><th>Aksi kamar</th></tr></thead>
            <tbody>
                <?php foreach ($list as $room):
                    $roomStatus = $room['status'] === 'maintenance'
                        ? 'maintenance'
                        : ((int) $room['occupied'] > 0 ? 'occupied'
                            : ((int) $room['approved_booking'] > 0 ? 'reserved'
                                : ((int) $room['pending'] > 0 ? 'pending' : 'available')));
                    $roomResidents = $residentsByRoom[(int) $room['id']] ?? [];
                    $roomBookings = $bookingsByRoom[(int) $room['id']] ?? [];
                ?>
                    <tr data-building-filter="<?= (int) $room['building_id'] ?>" data-building-label="<?= e($room['building_name']) ?>">
                        <td>
                            <div class="room-heading">
                                <a class="room-number-profile" href="index.php?page=room&amp;id=<?= (int) $room['id'] ?>" aria-label="Lihat profil kamar <?= e($room['room_number']) ?>"><?= e($room['room_number']) ?></a>
                                <strong>Kamar <?= e($room['room_number']) ?></strong>
                            </div>
                            <small class="subline"><?= e($room['building_name']) ?> · <span class="badge <?= e($roomStatus) ?>"><?= e(status_label($roomStatus)) ?></span></small>
                        </td>
                        <td><?= e($room['room_type']) ?><?= $room['floor'] ? '<small class="subline">Lantai ' . e($room['floor']) . '</small>' : '' ?></td>
                        <td><?= e(money($room['monthly_rate'])) ?></td>
                        <td class="room-residents-cell">
                            <?php if (!$roomResidents && !$roomBookings): ?><span class="muted-copy">Belum ada penghuni aktif</span><?php endif; ?>
                            <?php foreach ($roomResidents as $resident): ?>
                                <article class="room-resident">
                                    <?php if ($resident['user_id']): ?><a class="tenant-avatar-link" href="index.php?page=tenant_profile&amp;id=<?= (int) $resident['user_id'] ?>"><?php else: ?><span class="tenant-avatar-link"><?php endif; ?>
                                        <?= avatar_markup([
                                            'name' => $resident['full_name'],
                                            'gender' => $resident['gender'],
                                            'profile_photo_path' => $resident['profile_photo_path'],
                                            'avatar_key' => $resident['avatar_key'],
                                        ], 'profile-avatar small') ?>
                                        <span><strong><?= e($resident['full_name']) ?></strong><small><?= e($resident['phone']) ?></small></span>
                                    <?php if ($resident['user_id']): ?></a><?php else: ?></span><?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                            <?php if (!$roomResidents): foreach ($roomBookings as $booking):
                                $bookingPaymentLabel = $booking['latest_payment_status'] === 'pending'
                                    ? 'Bukti pembayaran menunggu verifikasi'
                                    : ($booking['latest_payment_status'] === 'rejected'
                                        ? 'Bukti ditolak · menunggu unggahan ulang'
                                        : 'Booking disetujui · menunggu pembayaran');
                            ?>
                                <article class="room-resident">
                                    <?php if ($booking['user_id']): ?><a class="tenant-avatar-link" href="index.php?page=tenant_profile&amp;id=<?= (int) $booking['user_id'] ?>"><?php else: ?><span class="tenant-avatar-link"><?php endif; ?>
                                        <?= avatar_markup([
                                            'name' => $booking['full_name'],
                                            'gender' => $booking['gender'],
                                            'profile_photo_path' => $booking['profile_photo_path'],
                                            'avatar_key' => $booking['avatar_key'],
                                        ], 'profile-avatar small') ?>
                                        <span><strong><?= e($booking['full_name']) ?></strong><small><?= e($bookingPaymentLabel) ?> · mulai <?= e(date_id($booking['start_date'])) ?></small></span>
                                    <?php if ($booking['user_id']): ?></a><?php else: ?></span><?php endif; ?>
                                </article>
                            <?php endforeach; endif; ?>
                        </td>
                        <td class="room-actions">
                            <a class="button secondary compact-button" href="index.php?page=room_form&amp;id=<?= (int) $room['id'] ?>">Edit kamar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$list): ?><tr><td colspan="5" class="no-data">Belum ada data kamar.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
