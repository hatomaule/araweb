<?php
declare(strict_types=1);

$roomScope = $user['role'] === 'owner' ? 'WHERE b.owner_user_id=:owner_id' : '';
$roomParameters = $user['role'] === 'owner' ? ['owner_id' => $user['id']] : [];
$list = rows(
    $pdo,
    "SELECT rm.*,b.name AS building_name,
        COALESCE(rm.profile_photo_path,
            (SELECT gp.photo_path FROM room_type_gallery_photos gp
             WHERE gp.building_id=rm.building_id
               AND gp.room_type=rm.room_type COLLATE utf8mb4_unicode_ci
               AND gp.category='room'
             ORDER BY gp.id LIMIT 1)
        ) AS cover_photo,
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
$buildingOptions = [];
$roomGroupsByBuilding = [];
foreach ($list as $room) {
    $buildingId = (int) $room['building_id'];
    $buildingOptions[$buildingId] = $room['building_name'];
    $roomGroupsByBuilding[$buildingId]['name'] = $room['building_name'];
    $roomGroupsByBuilding[$buildingId]['types'][$room['room_type']][] = $room;
}
?>
<div class="page-actions">
    <p>Penghuni aktif ditampilkan per kamar. Kelola akun penghuni dari menu Penghuni.</p>
    <a class="button primary" href="index.php?page=room_form">＋ Tambah kamar</a>
</div>
<section class="room-management" data-room-management data-room-view-storage-key="roomManagementView-<?= e($user['role']) ?>-<?= (int) $user['id'] ?>">
    <div class="room-management-tools">
        <label class="data-table-select"><span>Gedung kos</span><select data-room-building aria-label="Filter berdasarkan gedung kos"><option value="">Semua gedung</option><?php foreach ($buildingOptions as $buildingId => $buildingName): ?><option value="<?= $buildingId ?>"><?= e($buildingName) ?></option><?php endforeach; ?></select></label>
        <label class="data-table-select"><span>Tipe kamar</span><select data-room-type aria-label="Filter berdasarkan tipe kamar" disabled><option value="">Semua tipe</option></select></label>
        <label class="data-table-search"><span>Cari kamar</span><input type="search" data-room-search placeholder="Nomor kamar, penghuni..." aria-label="Cari kamar, penghuni, atau gedung"></label>
        <div class="room-table-pagination" data-room-pagination>
            <span data-room-summary aria-live="polite"></span>
            <label class="data-table-select"><span>Tampilkan</span><select data-room-page-size aria-label="Jumlah kamar yang ditampilkan"><option value="5" selected>5</option><option value="10">10</option><option value="all">Semua</option></select></label>
            <div class="room-pagination-buttons">
                <button class="button secondary compact-button" type="button" data-room-previous>Sebelumnya</button>
                <button class="button secondary compact-button" type="button" data-room-next>Berikutnya</button>
            </div>
        </div>
        <details class="data-table-view-switch data-view-picker room-view-switch" data-room-view-switch aria-label="Pilih tampilan kamar">
            <summary class="data-view-picker-trigger"><span class="data-view-current-icon" data-view-current-icon aria-hidden="true"><svg viewBox="0 0 20 20"><path d="M3 4.5h14M3 8h14M3 11.5h14M3 15h14"/><path d="M6 3v14"/></svg></span><span data-view-current-label>Tabel</span><span class="data-view-picker-chevron" aria-hidden="true">⌄</span></summary>
            <div class="data-view-picker-menu" role="group" aria-label="Pilihan tampilan">
                <button class="data-view-option" type="button" data-room-view="table" aria-pressed="true"><span class="data-view-option-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="M3 4.5h14M3 8h14M3 11.5h14M3 15h14"/><path d="M6 3v14"/></svg></span><span>Tabel</span><span class="data-view-option-check" aria-hidden="true">✓</span></button>
                <button class="data-view-option" type="button" data-room-view="cards" aria-pressed="false"><span class="data-view-option-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><rect x="3" y="3" width="5.5" height="5.5" rx="1"/><rect x="11.5" y="3" width="5.5" height="5.5" rx="1"/><rect x="3" y="11.5" width="5.5" height="5.5" rx="1"/><rect x="11.5" y="11.5" width="5.5" height="5.5" rx="1"/></svg></span><span>Kartu</span><span class="data-view-option-check" aria-hidden="true">✓</span></button>
            </div>
        </details>
    </div>
    <div class="room-view-content" data-room-table-view>
      <div class="panel">
    <div class="table-scroll">
        <table class="room-management-table" data-room-table>
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
                    <tr data-room-row data-building-id="<?= (int) $room['building_id'] ?>" data-room-type="<?= e($room['room_type']) ?>">
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
                <?php if (!$list): ?><tr data-room-empty><td colspan="5" class="no-data">Belum ada data kamar.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
      </div>
    </div>
    <div class="room-view-content room-card-view" data-room-card-view hidden>
        <?php $buildingAccents = ['#8a7bea', '#42a88a', '#d39a52', '#5595d5', '#cf7891', '#67a8b5'];
        $typeAccents = ['#b8a8ef', '#8bcab4', '#e3bd82', '#91b9df', '#dfa0b0', '#91c5ca'];
        $groupIndex = 0;
        foreach ($roomGroupsByBuilding as $buildingId => $buildingGroup):
            $buildingAccent = $buildingAccents[(int) $buildingId % count($buildingAccents)];
        ?>
            <section class="room-building-group" data-room-building-group="<?= (int) $buildingId ?>" style="--building-accent:<?= e($buildingAccent) ?>">
                <div class="room-building-heading"><span>Gedung kos</span><h3><?= e($buildingGroup['name']) ?></h3></div>
                <div class="room-type-grid">
                <?php $typeIndex = 0; ?>
                <?php foreach ($buildingGroup['types'] as $roomType => $groupRooms):
                    $typeAccent = $typeAccents[((int) $typeIndex + (int) $buildingId) % count($typeAccents)];
                    $isExclusiveRoomType = stripos((string) $roomType, 'eksklusif') !== false;
                ?>
                    <section class="room-carousel-section<?= $isExclusiveRoomType ? ' room-carousel-section-exclusive' : '' ?>" data-room-group data-building-id="<?= (int) $buildingId ?>" data-room-type="<?= e($roomType) ?>" style="--type-accent:<?= e($typeAccent) ?>">
                        <div class="room-carousel-heading">
                            <div><h3><?= e($roomType) ?></h3><small><?= count($groupRooms) ?> kamar</small></div>
                        </div>
                        <div class="room-carousel-controls">
                            <button class="property-carousel-arrow" type="button" data-carousel-target="room-carousel-<?= $groupIndex ?>" data-carousel-direction="left" aria-label="Geser kamar sebelumnya" disabled>‹</button>
                            <button class="property-carousel-arrow" type="button" data-carousel-target="room-carousel-<?= $groupIndex ?>" data-carousel-direction="right" aria-label="Geser kamar berikutnya">›</button>
                        </div>
                        <div class="property-carousel-track room-carousel-track" id="room-carousel-<?= $groupIndex ?>">
                            <?php foreach ($groupRooms as $room):
                                $roomStatus = $room['status'] === 'maintenance'
                                    ? 'maintenance'
                                    : ((int) $room['occupied'] > 0 ? 'occupied'
                                        : ((int) $room['approved_booking'] > 0 ? 'reserved'
                                            : ((int) $room['pending'] > 0 ? 'pending' : 'available')));
                                $roomResidents = $residentsByRoom[(int) $room['id']] ?? [];
                                $roomBookings = $bookingsByRoom[(int) $room['id']] ?? [];
                            ?>
                                <article class="room-vcard" data-room-card>
                                    <a class="room-vcard-photo" href="index.php?page=room&amp;id=<?= (int) $room['id'] ?>" aria-label="Lihat detail kamar <?= e($room['room_number']) ?>">
                                        <?php if (!empty($room['cover_photo'])): ?><img src="<?= e($room['cover_photo']) ?>" alt="Foto kamar <?= e($room['room_number']) ?>" loading="lazy"><?php else: ?><span aria-hidden="true">⌂</span><?php endif; ?>
                                        <span class="room-vcard-location-tag"><?= e($buildingGroup['name']) ?></span>
                                        <span class="badge <?= e($roomStatus) ?>"><?= e(status_label($roomStatus)) ?></span>
                                    </a>
                                    <div class="room-vcard-body">
                                        <h4>Kamar <?= e($room['room_number']) ?></h4>
                                        <p><?= e($roomType) ?><?= $room['floor'] ? ' · Lantai ' . e($room['floor']) : '' ?></p>
                                        <strong class="room-vcard-price"><?= e(money($room['monthly_rate'])) ?><small> / bulan</small></strong>
                                        <div class="room-vcard-occupant">
                                            <?php if ($roomResidents): ?><strong><?= e($roomResidents[0]['full_name']) ?></strong><small>Penghuni aktif</small>
                                            <?php elseif ($roomBookings): ?><strong><?= e($roomBookings[0]['full_name']) ?></strong><small>Booking disetujui · mulai <?= e(date_id($roomBookings[0]['start_date'])) ?></small>
                                            <?php else: ?><span><?= $roomStatus === 'maintenance' ? 'Dalam perbaikan' : 'Belum ada penghuni aktif' ?></span><?php endif; ?>
                                        </div>
                                        <a class="button secondary compact-button room-vcard-edit" href="index.php?page=room_form&amp;id=<?= (int) $room['id'] ?>">Edit kamar</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php $groupIndex++; ?>
                    <?php $typeIndex++; ?>
                <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</section>
