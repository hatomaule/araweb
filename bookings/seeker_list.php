<?php
$tenant = one($pdo, 'SELECT id FROM tenants WHERE user_id=:user_id', ['user_id' => $user['id']]);
$bookings = $tenant ? rows(
    $pdo,
    "SELECT r.id, r.start_date, r.end_date, r.status AS rental_status,
            r.booking_expires_at, rm.room_number, rm.room_type, rm.facilities,
            b.name AS building_name, b.address AS building_address,
            bank.bank_name, bank.account_name, bank.account_number, bank.instructions,
            r.booking_status, r.booking_decision_note,
            rm.monthly_rate, i.id AS invoice_id, i.invoice_number, i.amount_due,
            i.status AS invoice_status, i.due_date,
            COALESCE((SELECT SUM(pc.amount) FROM payments pc
                      WHERE pc.invoice_id=i.id AND pc.status='confirmed'),0) AS amount_paid,
            (SELECT p.status FROM payments p WHERE p.invoice_id=i.id
             ORDER BY p.created_at DESC, p.id DESC LIMIT 1) AS latest_payment_status,
            (SELECT p.id FROM payments p WHERE p.invoice_id=i.id
             ORDER BY p.created_at DESC, p.id DESC LIMIT 1) AS latest_payment_id
     FROM rentals r JOIN rooms rm ON rm.id=r.room_id
     JOIN kos_buildings b ON b.id=rm.building_id
     LEFT JOIN bank_transfer_settings bank ON bank.building_id=b.id
     LEFT JOIN invoices i ON i.rental_id=r.id
     WHERE r.tenant_id=:tenant_id
     ORDER BY r.created_at DESC, r.id DESC",
    ['tenant_id' => $tenant['id']]
) : [];
?>
<div class="welcome-row"><div><h2>Halo, <?= e(explode(' ', $user['name'])[0]) ?>!</h2><p>Pilih kamar, selesaikan pembayaran, dan pantau status pesanan Anda di sini.</p></div><a class="button primary" href="index.php?page=home#kamar">＋ Cari kamar</a></div>
<?php if (!$bookings): ?><div class="empty-state"><span>⌂</span><strong>Belum ada pesanan kamar</strong><p>Jelajahi kamar kos dan pesan kamar yang sesuai kebutuhan Anda.</p><a class="button primary" href="index.php?page=home#kamar">Lihat kamar tersedia</a></div><?php endif; ?>
<div class="customer-bookings">
    <?php foreach ($bookings as $booking):
        $expired = $booking['rental_status'] === 'upcoming'
            && $booking['booking_expires_at']
            && strtotime($booking['booking_expires_at']) <= time();
        $bookingLabel = $booking['booking_status'] === 'pending'
            ? 'Menunggu persetujuan kos'
            : ($booking['booking_status'] === 'rejected'
                ? 'Booking ditolak'
                : ($expired ? 'Booking kedaluwarsa'
                    : status_label($booking['latest_payment_status']
                        ?: ($booking['invoice_status'] === 'paid'
                            ? $booking['rental_status'] : $booking['invoice_status']))));
        $remainingAmount = max(0, (float) $booking['amount_due'] - (float) $booking['amount_paid']);
        $canPay = in_array($booking['rental_status'], ['upcoming', 'active'], true)
            && $booking['booking_status'] === 'approved'
            && $booking['invoice_status'] === 'open'
            && !$expired
            && $remainingAmount > 0
            && $booking['latest_payment_status'] !== 'pending';
    ?>
        <article class="customer-booking-card">
            <div class="customer-booking-header"><div><span class="section-eyebrow"><?= e($booking['building_name']) ?> · KAMAR <?= e($booking['room_number']) ?> · <?= e($booking['room_type']) ?></span><h2><?= e(date_id($booking['start_date'])) ?> – <?= e(date_id($booking['end_date'])) ?></h2></div><span class="badge <?= $booking['booking_status'] === 'rejected' || $expired ? 'rejected' : e($booking['booking_status'] === 'pending' ? 'pending' : ($booking['latest_payment_status'] ?: $booking['rental_status'])) ?>"><?= e($bookingLabel) ?></span></div>
            <?php if ($booking['booking_status'] === 'pending'): ?><div class="alert info">Permintaan Anda sedang ditinjau pemilik kos. Pembayaran baru tersedia setelah booking disetujui.</div>
            <?php elseif ($booking['booking_status'] === 'rejected'): ?><div class="alert error">Pemilik kos menolak permintaan booking ini. <?= e($booking['booking_decision_note'] ?? '') ?></div><?php endif; ?>
            <?php if ($booking['invoice_id']): ?><div class="booking-invoice"><span>Tagihan <?= e($booking['invoice_number']) ?></span><strong><?= e(money($remainingAmount)) ?></strong><small>Sisa pembayaran · jatuh tempo <?= e(date_id($booking['due_date'])) ?> · <?= e(status_label($booking['invoice_status'])) ?></small></div><?php endif; ?>
            <?php if ($booking['latest_payment_status'] === 'pending'): ?><div class="alert info">Bukti pembayaran sudah diterima dan sedang menunggu verifikasi admin.</div>
            <?php elseif ($booking['latest_payment_status'] === 'rejected' && !$expired): ?><div class="alert error">Bukti sebelumnya ditolak. Periksa kembali transfer Anda, lalu unggah bukti yang benar.</div><?php endif; ?>
            <?php if ($canPay && $booking['invoice_id']): ?>
                <?php if ($booking['bank_name']): ?><div class="customer-bank-card"><div><span>TRANSFER KE REKENING <?= e(strtoupper($booking['building_name'])) ?></span><strong><?= e($booking['bank_name']) ?> · <?= e($booking['account_number']) ?></strong><small>a.n. <?= e($booking['account_name']) ?></small></div><?php if ($booking['instructions']): ?><p><?= nl2br(e($booking['instructions'])) ?></p><?php endif; ?></div>
                    <form method="post" enctype="multipart/form-data" class="form-grid booking-proof-form"><?= csrf_field() ?><input type="hidden" name="action" value="submit_booking_proof"><input type="hidden" name="invoice_id" value="<?= (int) $booking['invoice_id'] ?>"><label class="field"><span>Bukti transfer JPG, PNG, WebP (maks. 4 MB) *</span><input type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp" required></label><?php field('Referensi transaksi (opsional)', 'transaction_reference', '', 'text', ['maxlength' => '100']); ?><div class="form-actions full-span"><button class="button primary" type="submit">Kirim bukti pembayaran</button><small>Transfer sebesar sisa tagihan<?php if ($booking['rental_status'] === 'upcoming'): ?>. Booking ditahan 24 jam atau sampai bukti transfer diverifikasi<?php endif; ?>.</small></div></form>
                <?php else: ?><div class="alert error">Rekening transfer kos ini belum diatur. Hubungi pemilik kos untuk informasi pembayaran.</div><?php endif; ?>
            <?php elseif ($booking['invoice_id'] && $booking['invoice_status'] === 'open' && !$expired && $booking['latest_payment_status'] === 'pending'): ?><div class="alert info">Pembayaran masih menunggu pemeriksaan pengelola.</div><?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
