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
    <div class="panel-heading"><div><h2>Booking pengguna</h2><p>Setujui booking untuk menahan kamar. Setelah uang diterima, catat pembayarannya melalui menu Keuangan agar penghuni tercatat.</p></div></div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>User</th><th>Properti / kamar</th><th>Periode</th><th>Status booking</th><th>Keputusan</th></tr></thead>
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
                                    <button class="button primary booking-approve-button" name="decision" value="approved" type="submit">Setujui booking</button>
                                    <label class="booking-rejection-field" for="booking-rejection-note-<?= (int) $booking['id'] ?>">
                                        <span>Alasan penolakan <small>opsional</small></span>
                                        <textarea id="booking-rejection-note-<?= (int) $booking['id'] ?>" name="decision_note" maxlength="1000" rows="3" placeholder="Jelaskan alasan agar pemohon memahami keputusan Anda."></textarea>
                                        <small class="field-help">Alasan ini akan ditampilkan kepada pemohon.</small>
                                    </label>
                                    <button class="button secondary booking-reject-button" name="decision" value="rejected" type="submit">Tolak booking</button>
                                </form>
                            <?php else: ?>
                                <?php if ($booking['booking_decision_note']): ?>
                                    <div class="booking-decision-note">
                                        <strong><?= $booking['booking_status'] === 'rejected' ? 'Alasan penolakan' : 'Catatan keputusan' ?></strong>
                                        <span><?= e($booking['booking_decision_note']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="booking-no-decision-note">—</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$bookingRequests): ?><tr><td colspan="5" class="no-data">Belum ada data booking pengguna.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
