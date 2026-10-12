<?php
declare(strict_types=1);

$bookingRequests = rows(
    $pdo,
    "SELECT r.id,r.start_date,r.end_date,r.booking_status,r.booking_decision_note,
            t.full_name,t.phone,rm.room_number,rm.room_type,b.name AS building_name
     FROM rentals r
     JOIN tenants t ON t.id=r.tenant_id
     JOIN rooms rm ON rm.id=r.room_id
     JOIN kos_buildings b ON b.id=rm.building_id
     WHERE b.owner_user_id=:owner_id
       AND r.booking_status IN ('pending','approved','rejected')
     ORDER BY FIELD(r.booking_status,'pending','approved','rejected'),r.created_at DESC",
    ['owner_id' => $user['id']]
);
?>
<div class="panel">
    <div class="panel-heading"><div><h2>Booking pengguna</h2><p>Tinjau dan konfirmasi permintaan booking kamar pada properti Anda.</p></div></div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>User</th><th>Properti / kamar</th><th>Periode</th><th>Status</th><th>Konfirmasi</th></tr></thead>
            <tbody>
                <?php foreach ($bookingRequests as $booking): ?>
                    <tr>
                        <td><strong><?= e($booking['full_name']) ?></strong><small class="subline"><?= e($booking['phone']) ?></small></td>
                        <td><?= e($booking['building_name']) ?><small class="subline"><?= e($booking['room_type']) ?> · <?= e($booking['room_number']) ?></small></td>
                        <td><?= e(date_id($booking['start_date'])) ?> – <?= e(date_id($booking['end_date'])) ?></td>
                        <td><span class="badge <?= e($booking['booking_status']) ?>"><?= e(status_label($booking['booking_status'])) ?></span></td>
                        <td>
                            <?php if ($booking['booking_status'] === 'pending'): ?>
                                <form method="post" class="booking-decision-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="decide_booking">
                                    <input type="hidden" name="rental_id" value="<?= (int) $booking['id'] ?>">
                                    <button class="button primary" name="decision" value="approved" type="submit">Konfirmasi</button>
                                    <input name="decision_note" maxlength="1000" placeholder="Alasan jika ditolak">
                                    <button class="button secondary" name="decision" value="rejected" type="submit">Tolak</button>
                                </form>
                            <?php else: ?>
                                <?= e($booking['booking_decision_note'] ?: '—') ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$bookingRequests): ?><tr><td colspan="5" class="no-data">Belum ada data booking pengguna.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
