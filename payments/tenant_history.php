<?php
$tenantId = max(0, (int) ($_GET['id'] ?? 0));
if (!$tenantId || !can_manage_tenant($pdo, $user, $tenantId)) {
    set_flash('error', 'Histori pembayaran tidak ditemukan atau berada di luar kos yang Anda kelola.');
    redirect($user['role'] === 'admin' ? 'platform_seeker_accounts' : 'tenants');
}
$tenant = one(
    $pdo,
    'SELECT t.id,t.full_name,t.email,t.phone
     FROM tenants t WHERE t.id=:id',
    ['id' => $tenantId]
);
if (!$tenant) {
    set_flash('error', 'Data penghuni tidak ditemukan.');
    redirect('tenants');
}
$buildingScope = $user['role'] === 'operator' ? 'AND b.owner_user_id=:owner_id' : '';
$queryParams = ['tenant_id' => $tenantId];
if ($user['role'] === 'operator') {
    $queryParams['owner_id'] = $user['id'];
}
$payments = rows(
    $pdo,
    "SELECT p.id,p.amount,p.payment_method,p.status,p.transaction_reference,p.paid_at,
            p.payment_proof_path,i.invoice_number,i.period_start,i.period_end,i.amount_due,
            i.status AS invoice_status,rm.room_number,b.name AS building_name
     FROM payments p JOIN invoices i ON i.id=p.invoice_id
     JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
     JOIN kos_buildings b ON b.id=rm.building_id
     WHERE r.tenant_id=:tenant_id {$buildingScope}
     ORDER BY p.paid_at DESC,p.id DESC LIMIT 500",
    $queryParams
);
$summary = one(
    $pdo,
    "SELECT COALESCE(SUM(CASE WHEN p.status='confirmed' THEN p.amount ELSE 0 END),0) AS confirmed_total,
            COALESCE(SUM(CASE WHEN p.status='pending' THEN p.amount ELSE 0 END),0) AS pending_total,
            COUNT(p.id) AS transaction_count
     FROM payments p JOIN invoices i ON i.id=p.invoice_id
     JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
     JOIN kos_buildings b ON b.id=rm.building_id
     WHERE r.tenant_id=:tenant_id {$buildingScope}",
    $queryParams
);
$balance = one(
    $pdo,
    "SELECT COALESCE(SUM(GREATEST(i.amount_due-COALESCE(p.paid,0),0)),0) AS balance
     FROM invoices i JOIN rentals r ON r.id=i.rental_id
     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
     LEFT JOIN (SELECT invoice_id,SUM(amount) AS paid FROM payments
                WHERE status='confirmed' GROUP BY invoice_id) p ON p.invoice_id=i.id
     WHERE r.tenant_id=:tenant_id AND i.status='open' {$buildingScope}",
    $queryParams
);
$paymentMethods = ['cash' => 'Tunai', 'bank_transfer' => 'Transfer bank', 'e_wallet' => 'E-wallet', 'other' => 'Lainnya'];
?>
<div class="welcome-row"><div><h2><?= e($tenant['full_name']) ?></h2><p><?= e($tenant['email'] ?: $tenant['phone']) ?> · Histori pembayaran <?= $user['role'] === 'operator' ? 'di kos Anda' : 'seluruh platform' ?></p></div><a class="button secondary" href="index.php?page=tenants">← Kembali ke penghuni</a></div>
<div class="stats-grid payment-history-stats">
    <article class="stat-card"><div class="stat-icon green">✓</div><div><span>Total diterima</span><strong><?= e(money($summary['confirmed_total'])) ?></strong></div></article>
    <article class="stat-card"><div class="stat-icon orange">◷</div><div><span>Menunggu verifikasi</span><strong><?= e(money($summary['pending_total'])) ?></strong></div></article>
    <article class="stat-card"><div class="stat-icon blue">▤</div><div><span>Jumlah transaksi</span><strong><?= (int) $summary['transaction_count'] ?></strong></div></article>
    <article class="stat-card"><div class="stat-icon purple">!</div><div><span>Sisa tunggakan</span><strong><?= e(money($balance['balance'])) ?></strong></div></article>
</div>
<div class="panel"><div class="panel-heading"><div><h2>Riwayat transaksi</h2><p>Maksimal 500 transaksi terbaru untuk penghuni ini.</p></div></div><div class="table-scroll"><table><thead><tr><th>Tanggal</th><th>Tagihan / periode</th><th>Kos / kamar</th><th>Metode</th><th>Nominal</th><th>Status</th></tr></thead><tbody>
<?php foreach ($payments as $payment): ?><tr><td><?= e($payment['paid_at'] ? date_id($payment['paid_at']) : 'Belum dibayar') ?></td><td><strong><?= e($payment['invoice_number']) ?></strong><small class="subline"><?= e(date_id($payment['period_start'])) ?> – <?= e(date_id($payment['period_end'])) ?></small></td><td><?= e($payment['building_name']) ?><small class="subline">Kamar <?= e($payment['room_number']) ?></small></td><td><?= e($paymentMethods[$payment['payment_method']] ?? $payment['payment_method']) ?></td><td><?= e(money($payment['amount'])) ?></td><td><span class="badge <?= e($payment['status']) ?>"><?= e(status_label($payment['status'])) ?></span><?php if ($payment['payment_proof_path']): ?><small class="subline"><a class="text-link" href="index.php?page=payment_proof&amp;id=<?= (int) $payment['id'] ?>">Lihat bukti</a></small><?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$payments): ?><tr><td colspan="6" class="no-data">Belum ada transaksi pembayaran untuk penghuni ini.</td></tr><?php endif; ?>
</tbody></table></div></div>
