<?php
declare(strict_types=1);

$bookingRequests = rows(
    $pdo,
    "SELECT r.id,r.start_date,r.end_date,r.booking_status,r.booking_decision_note,
            t.full_name,t.phone,rm.room_number,rm.room_type,b.name AS building_name,
            p.id AS payment_id,p.amount AS payment_amount,p.status AS payment_status,
            p.paid_at,p.transaction_reference,p.payment_proof_path,i.amount_due,i.invoice_number
     FROM rentals r
     JOIN tenants t ON t.id=r.tenant_id
     JOIN rooms rm ON rm.id=r.room_id
     JOIN kos_buildings b ON b.id=rm.building_id
     LEFT JOIN payments p ON p.id=(
        SELECT latest_payment.id
        FROM invoices latest_invoice
        JOIN payments latest_payment ON latest_payment.invoice_id=latest_invoice.id
        WHERE latest_invoice.rental_id=r.id
        ORDER BY latest_payment.created_at DESC,latest_payment.id DESC
        LIMIT 1
     )
     LEFT JOIN invoices i ON i.id=p.invoice_id
     WHERE b.owner_user_id=:owner_id
       AND r.booking_status IN ('pending','approved','rejected')
     ORDER BY FIELD(r.booking_status,'pending','approved','rejected'),r.created_at DESC",
    ['owner_id' => $user['id']]
);
?>
<div class="panel">
    <div class="panel-heading"><div><h2>Booking dan pembayaran</h2><p>Kelola keputusan booking dan tinjau bukti pembayaran dari daftar yang sama.</p></div></div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>User</th><th>Properti / kamar</th><th>Periode</th><th>Booking</th><th>Pembayaran</th><th>Keterangan</th></tr></thead>
            <tbody>
                <?php foreach ($bookingRequests as $booking): ?>
                    <tr>
                        <td><strong><?= e($booking['full_name']) ?></strong><small class="subline"><?= e($booking['phone']) ?></small>
                            <?php if ($bookingWhatsappUrl = whatsapp_url(
                                (string) $booking['phone'],
                                'Halo ' . $booking['full_name'] . ', saya pengelola kos ingin membahas booking Anda.'
                            )): ?><a class="text-link" href="<?= e($bookingWhatsappUrl) ?>" target="_blank" rel="noopener noreferrer">WhatsApp ↗</a><?php endif; ?>
                        </td>
                        <td><?= e($booking['building_name']) ?><small class="subline"><?= e($booking['room_type']) ?> · <?= e($booking['room_number']) ?></small></td>
                        <td><?= e(date_id($booking['start_date'])) ?> – <?= e(date_id($booking['end_date'])) ?></td>
                        <td><span class="badge <?= e($booking['booking_status']) ?>"><?= e(status_label($booking['booking_status'])) ?></span></td>
                        <td>
                            <?php if (!$booking['payment_id']): ?>
                                <span class="booking-no-decision-note">Belum ada pembayaran</span>
                            <?php else: ?>
                                <span class="badge <?= e($booking['payment_status']) ?>"><?= e(status_label($booking['payment_status'])) ?></span>
                                <small class="subline"><?= e(money($booking['payment_amount'])) ?><?php if ($booking['amount_due'] !== null): ?> dari <?= e(money($booking['amount_due'])) ?><?php endif; ?></small>
                                <?php if ($booking['paid_at']): ?><small class="subline"><?= e(date_id($booking['paid_at'])) ?></small><?php endif; ?>
                                <?php if ($booking['payment_proof_path']): ?>
                                    <a class="text-link" href="index.php?page=payment_proof&amp;id=<?= (int) $booking['payment_id'] ?>">Lihat bukti transfer</a>
                                <?php endif; ?>
                                <?php if ($booking['payment_status'] === 'pending' && $booking['payment_proof_path']): ?>
                                    <div class="booking-payment-actions">
                                        <form method="post">
                                            <?= csrf_field() ?><input type="hidden" name="action" value="approve_payment_proof">
                                            <input type="hidden" name="payment_id" value="<?= (int) $booking['payment_id'] ?>">
                                            <button class="button primary" type="submit">Setujui pembayaran</button>
                                        </form>
                                        <form method="post">
                                            <?= csrf_field() ?><input type="hidden" name="action" value="reject_payment_proof">
                                            <input type="hidden" name="payment_id" value="<?= (int) $booking['payment_id'] ?>">
                                            <button class="button secondary" type="submit">Tolak pembayaran</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($booking['booking_status'] === 'pending'): ?>
                                <form method="post" class="booking-decision-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="decide_booking">
                                    <input type="hidden" name="rental_id" value="<?= (int) $booking['id'] ?>">
                                    <label class="booking-rejection-field" for="booking-rejection-note-<?= (int) $booking['id'] ?>">
                                        <span>Keterangan <small>opsional</small></span>
                                        <textarea id="booking-rejection-note-<?= (int) $booking['id'] ?>" name="decision_note" maxlength="1000" rows="2" placeholder="Tambahkan keterangan"></textarea>
                                    </label>
                                    <div class="booking-decision-actions">
                                        <button class="button primary booking-approve-button" name="decision" value="approved" type="submit">Setujui</button>
                                        <button class="button secondary booking-reject-button" name="decision" value="rejected" type="submit">Tolak</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <?php if ($booking['booking_decision_note']): ?>
                                    <div class="booking-decision-note">
                                        <strong>Keterangan</strong>
                                        <span><?= e($booking['booking_decision_note']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="booking-no-decision-note">—</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$bookingRequests): ?><tr><td colspan="6" class="no-data">Belum ada data booking pengguna.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
