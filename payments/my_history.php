<?php
$tenant = one($pdo, 'SELECT id FROM tenants WHERE user_id=:user_id LIMIT 1', ['user_id' => $user['id']]);
$payments = [];
$paymentSummary = ['confirmed_total' => 0, 'pending_total' => 0, 'transaction_count' => 0];
if ($tenant) {
    $payments = rows(
        $pdo,
        'SELECT p.id,p.amount,p.payment_method,p.status,p.transaction_reference,p.paid_at,
                p.payment_proof_path,i.invoice_number,i.period_start,i.period_end,i.amount_due,
                i.status AS invoice_status,
                rm.room_number,b.id AS building_id,b.name AS building_name
         FROM payments p JOIN invoices i ON i.id=p.invoice_id
         JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
         JOIN kos_buildings b ON b.id=rm.building_id
         WHERE r.tenant_id=:tenant_id
         ORDER BY r.start_date DESC,p.paid_at DESC,p.id DESC LIMIT 200',
        ['tenant_id' => $tenant['id']]
    );
    $paymentSummary = one(
        $pdo,
        "SELECT COALESCE(SUM(CASE WHEN p.status='confirmed' THEN p.amount ELSE 0 END),0) AS confirmed_total,
                COALESCE(SUM(CASE WHEN p.status='pending' THEN p.amount ELSE 0 END),0) AS pending_total,
                COUNT(p.id) AS transaction_count
         FROM payments p JOIN invoices i ON i.id=p.invoice_id
         JOIN rentals r ON r.id=i.rental_id
         WHERE r.tenant_id=:tenant_id",
        ['tenant_id' => $tenant['id']]
    ) ?? $paymentSummary;
}
$paymentMethods = ['cash' => 'Tunai', 'bank_transfer' => 'Transfer bank', 'e_wallet' => 'E-wallet', 'other' => 'Lainnya'];
?>
<div class="welcome-row"><div><h2>Riwayat pembayaran saya</h2><p>Histori berikut hanya menampilkan transaksi yang terkait dengan akun Anda.</p></div></div>
<div class="stats-grid payment-history-stats">
    <article class="stat-card"><a class="stat-icon green stat-icon-link" href="index.php?page=user_reports" aria-label="Buka laporan pembayaran">✓</a><div><span>Total pembayaran terkonfirmasi</span><strong><?= e(money($paymentSummary['confirmed_total'])) ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon orange stat-icon-link" href="index.php?page=user_reports" aria-label="Buka pembayaran menunggu verifikasi">◷</a><div><span>Menunggu verifikasi</span><strong><?= e(money($paymentSummary['pending_total'])) ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon blue stat-icon-link" href="index.php?page=user_reports" aria-label="Buka laporan transaksi">▤</a><div><span>Jumlah transaksi</span><strong><?= (int) $paymentSummary['transaction_count'] ?></strong></div></article>
</div>
<section class="panel payment-history-group">
    <div class="panel-heading"><div><h2>Semua transaksi</h2><p>Riwayat pembayaran dari seluruh kos dan kamar yang Anda sewa.</p></div></div>
    <div class="table-scroll"><table class="payment-history-table" data-building-filter><thead><tr><th>Tanggal</th><th>Kos / kamar</th><th>Tagihan / periode</th><th>Metode</th><th>Nominal</th><th>Status pembayaran</th></tr></thead><tbody>
    <?php foreach ($payments as $payment): ?><tr data-building-filter="<?= (int) $payment['building_id'] ?>" data-building-label="<?= e($payment['building_name']) ?>">
        <td><?= e($payment['paid_at'] ? date_id($payment['paid_at']) : 'Belum dibayar') ?></td>
        <td><strong><?= e($payment['building_name']) ?></strong><small class="subline">Kamar <?= e($payment['room_number']) ?></small></td>
        <td><strong><?= e($payment['invoice_number']) ?></strong><small class="subline"><?= e(date_id($payment['period_start'])) ?> – <?= e(date_id($payment['period_end'])) ?></small></td>
        <td><?= e($paymentMethods[$payment['payment_method']] ?? $payment['payment_method']) ?></td>
        <td><?= e(money($payment['amount'])) ?></td>
        <td><span class="badge <?= e($payment['status']) ?>"><?= e(status_label($payment['status'])) ?></span><?php if ($payment['payment_proof_path']): ?><small class="subline"><a class="text-link" href="index.php?page=payment_proof&amp;id=<?= (int) $payment['id'] ?>">Lihat bukti</a></small><?php endif; ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$payments): ?><tr><td colspan="6" class="no-data">Belum ada transaksi pembayaran untuk akun ini.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
