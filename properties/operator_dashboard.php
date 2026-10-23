<?php
$ownerBuildingCount = (int) one($pdo, 'SELECT COUNT(*) AS total FROM kos_buildings WHERE owner_user_id=:id', ['id' => $user['id']])['total'];
$ownerRoomCount = (int) one($pdo, 'SELECT COUNT(*) AS total FROM rooms rm JOIN kos_buildings b ON b.id=rm.building_id WHERE b.owner_user_id=:id', ['id' => $user['id']])['total'];
$ownerPendingBookings = (int) one($pdo, "SELECT COUNT(*) AS total FROM rentals r JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id WHERE b.owner_user_id=:id AND r.booking_status='pending'", ['id' => $user['id']])['total'];
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
$propertyCollectedPeak = max(1, ...array_map(
    static fn(array $summary): float => (float) $summary['collected'],
    $propertySummaries ?: [['collected' => 1]]
));
$propertyActivity = rows(
    $pdo,
    "SELECT b.id,b.name,
       COUNT(CASE WHEN ae.event_type='search_result' THEN 1 END) AS searches,
       COUNT(CASE WHEN ae.event_type='property_view' THEN 1 END) AS property_views,
       COUNT(CASE WHEN ae.event_type='room_view' THEN 1 END) AS room_views,
       COUNT(DISTINCT CASE WHEN ae.event_type IN ('property_view','room_view') THEN ae.visitor_key END) AS unique_visitors
     FROM kos_buildings b LEFT JOIN property_activity_events ae ON ae.building_id=b.id
     WHERE b.owner_user_id=:id GROUP BY b.id,b.name ORDER BY b.name",
    ['id' => $user['id']]
);
$propertyActivityPeak = max(1, ...array_map(
    static fn(array $activity): int => max(
        (int) $activity['searches'],
        (int) $activity['property_views'],
        (int) $activity['room_views'],
        (int) $activity['unique_visitors']
    ),
    $propertyActivity ?: [['searches' => 1, 'property_views' => 1, 'room_views' => 1, 'unique_visitors' => 1]]
));
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
$monthlyTotal = array_sum($monthlyMap);
$currentYearTotal = $annualMap[$currentYear] ?? 0.0;
$compactMoney = static function (float $amount): string {
    if ($amount >= 1_000_000_000) {
        return 'Rp' . rtrim(rtrim(number_format($amount / 1_000_000_000, 1, ',', '.'), '0'), ',') . ' M';
    }
    if ($amount >= 1_000_000) {
        return 'Rp' . rtrim(rtrim(number_format($amount / 1_000_000, 1, ',', '.'), '0'), ',') . ' jt';
    }
    if ($amount >= 1_000) {
        return 'Rp' . rtrim(rtrim(number_format($amount / 1_000, 1, ',', '.'), '0'), ',') . ' rb';
    }
    return 'Rp' . number_format($amount, 0, ',', '.');
};
?>
<div class="welcome-row"><div><h2>Dashboard</h2><p>Rekap properti, kamar, dan keuangan kos yang terdaftar pada akun Anda.</p></div></div>
<div class="stats-grid operator-finance-stats">
    <article class="stat-card"><a class="stat-icon purple stat-icon-link" href="index.php?page=buildings" aria-label="Buka menu kos saya">⌂</a><div><span>Kos saya</span><strong><?= $ownerBuildingCount ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon blue stat-icon-link" href="index.php?page=rooms" aria-label="Buka menu kamar">▦</a><div><span>Jumlah kamar</span><strong><?= $ownerRoomCount ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon green stat-icon-link" href="index.php?page=payments" aria-label="Buka histori pembayaran">✓</a><div><span>Total pemasukan terkonfirmasi</span><strong><?= e(money($ownerPaymentSummary['collected'])) ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon orange stat-icon-link" href="index.php?page=payment_confirmations" aria-label="Buka pembayaran menunggu konfirmasi">◷</a><div><span>Menunggu verifikasi</span><strong><?= e(money($ownerPaymentSummary['awaiting'])) ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon orange stat-icon-link" href="index.php?page=operator_payment" aria-label="Buka input pembayaran">!</a><div><span>Tunggakan jatuh tempo</span><strong><?= e(money($ownerArrears)) ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon purple stat-icon-link" href="index.php?page=payments" aria-label="Buka histori pembayaran terkonfirmasi">▤</a><div><span>Pembayaran terkonfirmasi</span><strong><?= (int) $ownerPaymentSummary['confirmed_count'] ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon orange stat-icon-link" href="index.php?page=operator_bookings" aria-label="Buka booking menunggu">▣</a><div><span>Booking menunggu</span><strong><?= $ownerPendingBookings ?></strong></div></article>
</div>
<section class="panel dashboard-chart-shell">
    <div class="panel-heading"><div><h2>Pemasukan per kos</h2><p>Total adalah pembayaran terkonfirmasi; skala batang dibandingkan dengan kos Anda yang memiliki pemasukan terbesar.</p></div><a class="text-link" href="index.php?page=payments">Semua transaksi →</a></div>
    <?php if ($propertySummaries): ?><div class="property-finance-chart-grid">
        <?php foreach ($propertySummaries as $summary): ?>
            <article class="property-finance-chart">
                <h3><?= e($summary['name']) ?></h3>
                <div class="property-finance-bar" role="img" aria-label="<?= e($summary['name']) ?>: pemasukan terkonfirmasi <?= e(money($summary['collected'])) ?>">
                    <span style="width:<?= max(1, (int) round((float) $summary['collected'] / $propertyCollectedPeak * 100)) ?>%"></span>
                </div>
                <strong><?= e(money($summary['collected'])) ?></strong>
                <div class="property-finance-details"><span>Menunggu <?= e(money($summary['pending_payments'])) ?></span><span>Tunggakan <?= e(money($summary['arrears'])) ?></span></div>
            </article>
        <?php endforeach; ?>
    </div><?php else: ?><p class="no-data">Belum ada kos pada akun ini.</p><?php endif; ?>
</section>
<section class="panel dashboard-chart-shell">
    <div class="panel-heading"><div><h2>Aktivitas pengunjung per kos</h2><p>Grafik jumlah pencarian, kunjungan, dan pengunjung unik. Rincian aktivitas tersedia di menu Aktivitas.</p></div><a class="text-link" href="index.php?page=activity">Buka aktivitas →</a></div>
    <?php if ($propertyActivity): ?><div class="property-activity-chart-list">
        <?php foreach ($propertyActivity as $activity): ?>
            <article class="property-activity-chart"><h3><?= e($activity['name']) ?></h3>
                <?php foreach ([
                    'Hasil pencarian' => (int) $activity['searches'],
                    'Dilihat' => (int) $activity['property_views'],
                    'Kamar dilihat' => (int) $activity['room_views'],
                    'Pengunjung unik' => (int) $activity['unique_visitors'],
                ] as $label => $count): ?>
                    <div class="property-activity-row"><span><?= e($label) ?></span><div><i style="width:<?= max(1, (int) round($count / $propertyActivityPeak * 100)) ?>%"></i></div><strong><?= $count ?></strong></div>
                <?php endforeach; ?>
            </article>
        <?php endforeach; ?>
    </div><?php else: ?><p class="no-data">Belum ada kos untuk diringkas.</p><?php endif; ?>
</section>
<div class="income-chart-grid">
<section class="panel income-chart-panel dashboard-chart-shell">
    <div class="panel-heading"><div><h2>Rekap pemasukan bulanan</h2><p>Pembayaran terkonfirmasi · 12 bulan terakhir · total <?= e(money($monthlyTotal)) ?>.</p></div></div>
    <?php $incomePeak = max(1, ...array_values($monthlyMap ?: [1])); ?>
    <div class="income-chart-list">
        <?php for ($offset = 11; $offset >= 0; $offset--):
            $monthDate = (new DateTimeImmutable('first day of this month'))->modify('-' . $offset . ' months');
            $monthKey = $monthDate->format('Y-m');
            $amount = $monthlyMap[$monthKey] ?? 0.0;
        ?>
            <div class="income-chart-row" title="<?= e($monthDate->format('F Y') . ': ' . money($amount)) ?>">
                <span><?= e($monthDate->format('M y')) ?></span>
                <div role="img" aria-label="<?= e($monthDate->format('F Y') . ': ' . money($amount)) ?>"><i style="width:<?= $amount > 0 ? max(1, (int) round($amount / $incomePeak * 100)) : 0 ?>%"></i></div>
                <strong><?= e($compactMoney($amount)) ?></strong>
            </div>
        <?php endfor; ?>
    </div>
</section>
<section class="panel income-chart-panel dashboard-chart-shell">
    <div class="panel-heading"><div><h2>Rekap pemasukan tahunan</h2><p>Pembayaran terkonfirmasi · tahun ini <?= e(money($currentYearTotal)) ?>.</p></div></div>
    <div class="income-chart-list annual">
        <?php for ($year = $currentYear - 4; $year <= $currentYear; $year++):
            $amount = $annualMap[$year] ?? 0.0;
        ?>
            <div class="income-chart-row" title="<?= e((string) $year . ': ' . money($amount)) ?>">
                <span><?= $year ?></span>
                <div role="img" aria-label="<?= e((string) $year . ': ' . money($amount)) ?>"><i style="width:<?= $amount > 0 ? max(1, (int) round($amount / $annualPeak * 100)) : 0 ?>%"></i></div>
                <strong><?= e($compactMoney($amount)) ?></strong>
            </div>
        <?php endfor; ?>
    </div>
</section>
</div>
