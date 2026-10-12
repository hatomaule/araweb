<?php
$ownerBuildingCount = (int) one($pdo, 'SELECT COUNT(*) AS total FROM kos_buildings WHERE owner_user_id=:id', ['id' => $user['id']])['total'];
$ownerRoomCount = (int) one($pdo, 'SELECT COUNT(*) AS total FROM rooms rm JOIN kos_buildings b ON b.id=rm.building_id WHERE b.owner_user_id=:id', ['id' => $user['id']])['total'];
$ownerPendingBookings = (int) one($pdo, "SELECT COUNT(*) AS total FROM rentals r JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id WHERE b.owner_user_id=:id AND r.booking_status='pending'", ['id' => $user['id']])['total'];
$ownerListings = rows($pdo, 'SELECT id,name,status FROM kos_buildings WHERE owner_user_id=:id ORDER BY created_at DESC', ['id' => $user['id']]);
$ownerPaymentSummary = one(
    $pdo,
    "SELECT COALESCE(SUM(CASE WHEN p.status='confirmed' THEN p.amount ELSE 0 END),0) AS collected,
            COALESCE(SUM(CASE WHEN p.status='pending' THEN p.amount ELSE 0 END),0) AS awaiting,
            COUNT(CASE WHEN p.status='confirmed' THEN 1 END) AS confirmed_count
     FROM payments p JOIN invoices i ON i.id=p.invoice_id
     JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
     JOIN kos_buildings b ON b.id=rm.building_id WHERE b.owner_user_id=:id",
    ['id' => $user['id']]
);
$ownerArrears = (float) one(
    $pdo,
    "SELECT COALESCE(SUM(GREATEST(i.amount_due-COALESCE(p.paid,0),0)),0) AS total
     FROM invoices i JOIN rentals r ON r.id=i.rental_id
     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
     LEFT JOIN (SELECT invoice_id,SUM(amount) AS paid FROM payments
                WHERE status='confirmed' GROUP BY invoice_id) p ON p.invoice_id=i.id
     WHERE b.owner_user_id=:id AND i.status='open' AND i.due_date<CURDATE()",
    ['id' => $user['id']]
)['total'];
$propertySummaries = rows(
    $pdo,
    "SELECT b.id,b.name,
       (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN invoices i ON i.id=p.invoice_id
        JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
        WHERE rm.building_id=b.id AND p.status='confirmed') AS collected,
       (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN invoices i ON i.id=p.invoice_id
        JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
        WHERE rm.building_id=b.id AND p.status='pending') AS pending_payments,
       (SELECT COALESCE(SUM(GREATEST(i.amount_due-COALESCE(paid.paid,0),0)),0)
        FROM invoices i JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
        LEFT JOIN (SELECT invoice_id,SUM(amount) AS paid FROM payments
                   WHERE status='confirmed' GROUP BY invoice_id) paid ON paid.invoice_id=i.id
        WHERE rm.building_id=b.id AND i.status='open' AND i.due_date<CURDATE()) AS arrears
     FROM kos_buildings b WHERE b.owner_user_id=:id ORDER BY b.name",
    ['id' => $user['id']]
);
$monthlyIncome = rows(
    $pdo,
    "SELECT DATE_FORMAT(p.paid_at,'%Y-%m') AS month_key,SUM(p.amount) AS total
     FROM payments p JOIN invoices i ON i.id=p.invoice_id
     JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
     JOIN kos_buildings b ON b.id=rm.building_id
     WHERE b.owner_user_id=:id AND p.status='confirmed'
       AND p.paid_at>=DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL 11 MONTH)
     GROUP BY month_key ORDER BY month_key",
    ['id' => $user['id']]
);
$monthlyMap = [];
foreach ($monthlyIncome as $income) {
    $monthlyMap[$income['month_key']] = (float) $income['total'];
}
$annualIncome = rows(
    $pdo,
    "SELECT YEAR(p.paid_at) AS year_key,SUM(p.amount) AS total
     FROM payments p JOIN invoices i ON i.id=p.invoice_id
     JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
     JOIN kos_buildings b ON b.id=rm.building_id
     WHERE b.owner_user_id=:id AND p.status='confirmed'
     GROUP BY YEAR(p.paid_at) ORDER BY year_key DESC LIMIT 5",
    ['id' => $user['id']]
);
$annualMap = [];
foreach ($annualIncome as $income) {
    $annualMap[(int) $income['year_key']] = (float) $income['total'];
}
$currentYear = (int) date('Y');
$annualPeak = max(1, ...array_values($annualMap ?: [1]));
?>
<div class="welcome-row"><div><h2>Laporan properti</h2><p>Rekap properti, kamar, dan keuangan kos yang terdaftar pada akun Anda.</p></div><div class="actions"><a class="button secondary" href="index.php?page=rooms">Kelola kamar</a><a class="button primary" href="index.php?page=building_form">＋ Tambah gedung</a></div></div>
<div class="stats-grid operator-finance-stats">
    <article class="stat-card"><a class="stat-icon purple stat-icon-link" href="index.php?page=buildings" aria-label="Buka menu kos saya">⌂</a><div><span>Kos saya</span><strong><?= $ownerBuildingCount ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon blue stat-icon-link" href="index.php?page=rooms" aria-label="Buka menu kamar">▦</a><div><span>Jumlah kamar</span><strong><?= $ownerRoomCount ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon green stat-icon-link" href="index.php?page=payments" aria-label="Buka histori pembayaran">✓</a><div><span>Total pemasukan terkonfirmasi</span><strong><?= e(money($ownerPaymentSummary['collected'])) ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon orange stat-icon-link" href="index.php?page=payment_confirmations" aria-label="Buka pembayaran menunggu konfirmasi">◷</a><div><span>Menunggu verifikasi</span><strong><?= e(money($ownerPaymentSummary['awaiting'])) ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon orange stat-icon-link" href="index.php?page=operator_payment" aria-label="Buka input pembayaran">!</a><div><span>Tunggakan jatuh tempo</span><strong><?= e(money($ownerArrears)) ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon purple stat-icon-link" href="index.php?page=payments" aria-label="Buka histori pembayaran terkonfirmasi">▤</a><div><span>Pembayaran terkonfirmasi</span><strong><?= (int) $ownerPaymentSummary['confirmed_count'] ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon orange stat-icon-link" href="index.php?page=operator_bookings" aria-label="Buka booking menunggu">▣</a><div><span>Booking menunggu</span><strong><?= $ownerPendingBookings ?></strong></div></article>
</div>
<div class="panel"><div class="panel-heading"><div><h2>Ringkasan tiap kos</h2><p>Pemasukan dihitung dari pembayaran yang telah dikonfirmasi.</p></div><a class="text-link" href="index.php?page=payments">Semua transaksi →</a></div><div class="table-scroll"><table><thead><tr><th>Nama kos</th><th>Pemasukan</th><th>Menunggu verifikasi</th><th>Tunggakan</th></tr></thead><tbody>
<?php foreach ($propertySummaries as $summary): ?><tr><td><strong><?= e($summary['name']) ?></strong><small class="subline"><a class="text-link" href="index.php?page=payments">Lihat histori transaksi</a></small></td><td><?= e(money($summary['collected'])) ?></td><td><?= e(money($summary['pending_payments'])) ?></td><td><?= e(money($summary['arrears'])) ?></td></tr><?php endforeach; ?>
<?php if (!$propertySummaries): ?><tr><td colspan="4" class="no-data">Belum ada kos pada akun ini.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="income-chart-grid">
<section class="panel income-chart-panel">
    <div class="panel-heading"><div><h2>Rekap pemasukan bulanan</h2><p>Pembayaran terkonfirmasi · 12 bulan terakhir.</p></div></div>
    <?php $incomePeak = max(1, ...array_values($monthlyMap ?: [1])); ?>
    <div class="income-chart income-chart-monthly">
        <?php for ($offset = 11; $offset >= 0; $offset--):
            $monthDate = (new DateTimeImmutable('first day of this month'))->modify('-' . $offset . ' months');
            $monthKey = $monthDate->format('Y-m');
            $amount = $monthlyMap[$monthKey] ?? 0.0;
        ?>
            <div class="income-chart-month" title="<?= e(money($amount)) ?>">
                <span style="height:<?= max(2, (int) round($amount / $incomePeak * 100)) ?>%"></span>
                <small><?= e($monthDate->format('M y')) ?></small>
            </div>
        <?php endfor; ?>
    </div>
</section>
<section class="panel income-chart-panel">
    <div class="panel-heading"><div><h2>Rekap pemasukan tahunan</h2><p>Pembayaran terkonfirmasi · 5 tahun terakhir.</p></div></div>
    <div class="income-chart income-chart-annual">
        <?php for ($year = $currentYear - 4; $year <= $currentYear; $year++):
            $amount = $annualMap[$year] ?? 0.0;
        ?>
            <div class="income-chart-year" title="<?= e(money($amount)) ?>">
                <span style="height:<?= max(2, (int) round($amount / $annualPeak * 100)) ?>%"></span>
                <small><?= $year ?></small>
            </div>
        <?php endfor; ?>
    </div>
</section>
</div>
<?php foreach ($ownerListings as $ownerListing): ?><div class="alert <?= $ownerListing['status'] === 'active' ? 'success' : 'info' ?>"><strong><?= e($ownerListing['name']) ?>:</strong> <?= $ownerListing['status'] === 'active' ? 'Tampil di pencarian pengguna.' : 'Listing sedang disembunyikan.' ?></div><?php endforeach; ?>
<?php require __DIR__ . '/../reports/operator.php'; ?>
