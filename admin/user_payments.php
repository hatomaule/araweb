<?php
declare(strict_types=1);

$userId = max(0, (int) ($_GET['id'] ?? 0));
$account = one(
    $pdo,
    "SELECT id,name,email,phone,status FROM users WHERE id=:id AND role='seeker'",
    ['id' => $userId]
);
if (!$account) {
    set_flash('error', 'Profil User tidak ditemukan.');
    redirect('platform_seeker_accounts');
}

$summary = one(
    $pdo,
    "SELECT
        (SELECT COALESCE(SUM(p.amount),0)
         FROM payments p JOIN invoices i ON i.id=p.invoice_id
         JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id
         WHERE t.user_id=:confirmed_user AND p.status='confirmed') AS confirmed_total,
        (SELECT COALESCE(SUM(p.amount),0)
         FROM payments p JOIN invoices i ON i.id=p.invoice_id
         JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id
         WHERE t.user_id=:pending_user AND p.status='pending') AS pending_total,
        (SELECT COUNT(p.id)
         FROM payments p JOIN invoices i ON i.id=p.invoice_id
         JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id
         WHERE t.user_id=:count_user) AS transaction_count,
        (SELECT COALESCE(SUM(GREATEST(i.amount_due-COALESCE(paid.confirmed_total,0),0)),0)
         FROM invoices i JOIN rentals r ON r.id=i.rental_id
         JOIN tenants t ON t.id=r.tenant_id
         LEFT JOIN (
             SELECT invoice_id,SUM(amount) AS confirmed_total
             FROM payments WHERE status='confirmed' GROUP BY invoice_id
         ) paid ON paid.invoice_id=i.id
         WHERE t.user_id=:balance_user AND i.status='open') AS outstanding",
    [
        'confirmed_user' => $userId,
        'pending_user' => $userId,
        'count_user' => $userId,
        'balance_user' => $userId,
    ]
) ?? ['confirmed_total' => 0, 'pending_total' => 0, 'transaction_count' => 0, 'outstanding' => 0];

$invoices = rows(
    $pdo,
    "SELECT i.invoice_number,i.period_start,i.period_end,i.due_date,i.amount_due,i.status,
            b.name AS building_name,rm.room_number,COALESCE(paid.confirmed_total,0) AS paid_total,
            GREATEST(i.amount_due-COALESCE(paid.confirmed_total,0),0) AS remaining
     FROM invoices i JOIN rentals r ON r.id=i.rental_id
     JOIN tenants t ON t.id=r.tenant_id
     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
     LEFT JOIN (
         SELECT invoice_id,SUM(amount) AS confirmed_total
         FROM payments WHERE status='confirmed' GROUP BY invoice_id
     ) paid ON paid.invoice_id=i.id
     WHERE t.user_id=:user_id AND i.status<>'void'
     ORDER BY i.due_date DESC,i.id DESC LIMIT 500",
    ['user_id' => $userId]
);

$payments = rows(
    $pdo,
    "SELECT p.id,p.amount,p.payment_method,p.status,p.transaction_reference,p.paid_at,
            p.payment_proof_path,i.invoice_number,i.period_start,i.period_end,
            b.name AS building_name,rm.room_number
     FROM payments p JOIN invoices i ON i.id=p.invoice_id
     JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id
     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
     WHERE t.user_id=:user_id
     ORDER BY p.paid_at DESC,p.id DESC LIMIT 500",
    ['user_id' => $userId]
);
$paymentMethods = [
    'cash' => 'Tunai',
    'bank_transfer' => 'Transfer bank',
    'e_wallet' => 'E-wallet',
    'other' => 'Lainnya',
];
?>
<div class="welcome-row">
    <div><h2>Profil pembayaran <?= e($account['name']) ?></h2><p><?= e($account['email'] ?: $account['phone'] ?: 'Kontak belum diisi') ?> · Ringkasan seluruh transaksi akun User ini.</p></div>
    <a class="button secondary" href="index.php?page=platform_user_profile&amp;id=<?= $userId ?>">← Kembali ke profil User</a>
</div>
<div class="stats-grid payment-history-stats">
    <article class="stat-card"><div class="stat-icon green">✓</div><div><span>Total terkonfirmasi</span><strong><?= e(money($summary['confirmed_total'])) ?></strong></div></article>
    <article class="stat-card"><div class="stat-icon orange">◷</div><div><span>Menunggu verifikasi</span><strong><?= e(money($summary['pending_total'])) ?></strong></div></article>
    <article class="stat-card"><div class="stat-icon blue">▤</div><div><span>Jumlah transaksi</span><strong><?= (int) $summary['transaction_count'] ?></strong></div></article>
    <article class="stat-card"><div class="stat-icon purple">!</div><div><span>Sisa tagihan terbuka</span><strong><?= e(money($summary['outstanding'])) ?></strong></div></article>
</div>
<div class="panel">
    <div class="panel-heading"><div><h2>Tagihan User</h2><p>Maksimal 500 tagihan terbaru, termasuk sisa pembayaran yang telah dikonfirmasi.</p></div></div>
    <div class="table-scroll"><table><thead><tr><th>Tagihan</th><th>Kos / kamar</th><th>Periode</th><th>Jatuh tempo</th><th>Nominal</th><th>Dibayar</th><th>Sisa</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($invoices as $invoice): ?>
        <tr><td><strong><?= e($invoice['invoice_number']) ?></strong></td><td><?= e($invoice['building_name']) ?><small class="subline">Kamar <?= e($invoice['room_number']) ?></small></td><td><?= e(date_id($invoice['period_start'])) ?> – <?= e(date_id($invoice['period_end'])) ?></td><td><?= e(date_id($invoice['due_date'])) ?></td><td><?= e(money($invoice['amount_due'])) ?></td><td><?= e(money($invoice['paid_total'])) ?></td><td><strong><?= e(money($invoice['remaining'])) ?></strong></td><td><span class="badge <?= e($invoice['status']) ?>"><?= e(status_label($invoice['status'])) ?></span></td></tr>
    <?php endforeach; ?>
    <?php if (!$invoices): ?><tr><td colspan="8" class="no-data">Belum ada tagihan untuk User ini.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<div class="panel">
    <div class="panel-heading"><div><h2>Riwayat transaksi</h2><p>Maksimal 500 transaksi terbaru dari seluruh riwayat akun.</p></div></div>
    <div class="table-scroll"><table><thead><tr><th>Tanggal</th><th>Tagihan / periode</th><th>Kos / kamar</th><th>Metode</th><th>Nominal</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($payments as $payment): ?>
        <tr><td><?= e($payment['paid_at'] ? date_id($payment['paid_at']) : 'Belum dibayar') ?></td><td><strong><?= e($payment['invoice_number']) ?></strong><small class="subline"><?= e(date_id($payment['period_start'])) ?> – <?= e(date_id($payment['period_end'])) ?></small></td><td><?= e($payment['building_name']) ?><small class="subline">Kamar <?= e($payment['room_number']) ?></small></td><td><?= e($paymentMethods[$payment['payment_method']] ?? $payment['payment_method']) ?></td><td><?= e(money($payment['amount'])) ?></td><td><span class="badge <?= e($payment['status']) ?>"><?= e(status_label($payment['status'])) ?></span><?php if ($payment['payment_proof_path']): ?><small class="subline"><a class="text-link" href="index.php?page=payment_proof&amp;id=<?= (int) $payment['id'] ?>">Lihat bukti</a></small><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$payments): ?><tr><td colspan="6" class="no-data">Belum ada transaksi pembayaran untuk User ini.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
