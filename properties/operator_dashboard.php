<?php
$currentYear = (int) date('Y');
$ownerBuildingCount = (int) one($pdo, 'SELECT COUNT(*) AS total FROM kos_buildings WHERE owner_user_id=:id', ['id' => $user['id']])['total'];
$ownerRoomCount = (int) one($pdo, 'SELECT COUNT(*) AS total FROM rooms rm JOIN kos_buildings b ON b.id=rm.building_id WHERE b.owner_user_id=:id', ['id' => $user['id']])['total'];
$ownerActiveTenantCount = (int) one(
    $pdo,
    "SELECT COUNT(DISTINCT r.tenant_id) AS total FROM rentals r
     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
     WHERE b.owner_user_id=:id AND r.status='active'
       AND r.start_date<=CURDATE() AND (r.end_date IS NULL OR r.end_date>=CURDATE())",
    ['id' => $user['id']]
)['total'];
$ownerPendingBookings = (int) one($pdo, "SELECT COUNT(*) AS total FROM rentals r JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id WHERE b.owner_user_id=:id AND r.booking_status='pending'", ['id' => $user['id']])['total'];
$ownerPaymentSummary = one(
    $pdo,
    "SELECT COALESCE(SUM(CASE WHEN p.status='confirmed'
                AND p.paid_at>=MAKEDATE(YEAR(CURDATE()),1)
                AND p.paid_at<MAKEDATE(YEAR(CURDATE())+1,1)
                THEN p.amount ELSE 0 END),0) AS collected,
            COALESCE(SUM(CASE WHEN p.status='pending'
                AND p.created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')
                AND p.created_at<DATE_ADD(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL 1 MONTH)
                THEN p.amount ELSE 0 END),0) AS awaiting
     FROM payments p JOIN invoices i ON i.id=p.invoice_id
     JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
     JOIN kos_buildings b ON b.id=rm.building_id WHERE b.owner_user_id=:id",
    ['id' => $user['id']]
);
$ownerExpenseSummary = one(
    $pdo,
    "SELECT COALESCE(SUM(CASE WHEN expense_date>=MAKEDATE(YEAR(CURDATE()),1)
                AND expense_date<MAKEDATE(YEAR(CURDATE())+1,1) THEN amount ELSE 0 END),0) AS spent,
            COALESCE(SUM(CASE WHEN expense_date>=DATE_FORMAT(CURDATE(),'%Y-%m-01')
                AND expense_date<DATE_ADD(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL 1 MONTH)
                THEN amount ELSE 0 END),0) AS month_spent
     FROM owner_expenses WHERE owner_user_id=:id",
    ['id' => $user['id']]
);
$ownerMonthIncome = (float) one(
    $pdo,
    "SELECT COALESCE(SUM(p.amount),0) AS total FROM payments p
     JOIN invoices i ON i.id=p.invoice_id JOIN rentals r ON r.id=i.rental_id
     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
     WHERE b.owner_user_id=:id AND p.status='confirmed'
       AND p.paid_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')
       AND p.paid_at<DATE_ADD(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL 1 MONTH)",
    ['id' => $user['id']]
)['total'];
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
       (SELECT COALESCE(SUM(rm.monthly_rate),0) FROM rooms rm
        WHERE rm.building_id=b.id) AS monthly_target,
       (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN invoices i ON i.id=p.invoice_id
        JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
        WHERE rm.building_id=b.id AND p.status='confirmed'
          AND p.paid_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')
          AND p.paid_at<DATE_ADD(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL 1 MONTH)) AS collected,
       (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN invoices i ON i.id=p.invoice_id
        JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
        WHERE rm.building_id=b.id AND p.status='pending'
          AND p.created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')
          AND p.created_at<DATE_ADD(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL 1 MONTH)) AS pending_payments
     FROM kos_buildings b WHERE b.owner_user_id=:id ORDER BY b.name",
    ['id' => $user['id']]
);
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
       AND p.paid_at>=MAKEDATE(YEAR(CURDATE()),1)
       AND p.paid_at<MAKEDATE(YEAR(CURDATE())+1,1)
     GROUP BY month_key ORDER BY month_key",
    ['id' => $user['id']]
);
$monthlyExpenses = rows(
    $pdo,
    "SELECT DATE_FORMAT(expense_date,'%Y-%m') AS month_key,SUM(amount) AS total
     FROM owner_expenses
     WHERE owner_user_id=:id
       AND expense_date>=MAKEDATE(YEAR(CURDATE()),1)
       AND expense_date<MAKEDATE(YEAR(CURDATE())+1,1)
     GROUP BY month_key ORDER BY month_key",
    ['id' => $user['id']]
);
$monthlyMap = [];
foreach ($monthlyIncome as $income) {
    $monthlyMap[$income['month_key']] = (float) $income['total'];
}
$monthlyExpenseMap = [];
foreach ($monthlyExpenses as $expense) {
    $monthlyExpenseMap[$expense['month_key']] = (float) $expense['total'];
}
$annualIncome = rows(
    $pdo,
    "SELECT YEAR(p.paid_at) AS year_key,SUM(p.amount) AS total
     FROM payments p JOIN invoices i ON i.id=p.invoice_id
     JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
     JOIN kos_buildings b ON b.id=rm.building_id
     WHERE b.owner_user_id=:id AND p.status='confirmed' AND p.paid_at IS NOT NULL
     GROUP BY YEAR(p.paid_at) ORDER BY year_key DESC LIMIT 5",
    ['id' => $user['id']]
);
$annualMap = [];
foreach ($annualIncome as $income) {
    $annualMap[(int) $income['year_key']] = (float) $income['total'];
}
$annualPeak = max(1, ...array_values($annualMap ?: [1]));
$monthlyPeak = max([1, ...array_merge(array_values($monthlyMap), array_values($monthlyExpenseMap))]);
$totalIncome = (float) $ownerPaymentSummary['collected'];
$totalExpense = (float) $ownerExpenseSummary['spent'];
$netIncome = $totalIncome - $totalExpense;
$monthExpense = (float) $ownerExpenseSummary['month_spent'];
$monthNet = $ownerMonthIncome - $monthExpense;
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
$monthLabels = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$currentMonthLabel = $monthLabels[(int) date('n') - 1];
?>
<section class="dashboard-group">
    <h2 class="dashboard-section-title">Properti</h2>
    <div class="stats-grid operator-finance-stats">
        <article class="stat-card"><a class="stat-icon purple stat-icon-link" href="index.php?page=buildings" aria-label="Buka menu kos saya">⌂</a><div><a class="stat-card-label-link" href="index.php?page=buildings">Kos saya</a><strong><?= $ownerBuildingCount ?></strong></div></article>
        <article class="stat-card"><a class="stat-icon blue stat-icon-link" href="index.php?page=rooms" aria-label="Buka menu kamar">▦</a><div><a class="stat-card-label-link" href="index.php?page=rooms">Jumlah kamar</a><strong><?= $ownerRoomCount ?></strong></div></article>
        <article class="stat-card"><a class="stat-icon purple stat-icon-link" href="index.php?page=tenants" aria-label="Buka daftar penghuni">♙</a><div><a class="stat-card-label-link" href="index.php?page=tenants">Penghuni aktif</a><strong><?= $ownerActiveTenantCount ?></strong></div></article>
        <article class="stat-card"><a class="stat-icon orange stat-icon-link" href="index.php?page=operator_bookings" aria-label="Buka booking menunggu">▣</a><div><a class="stat-card-label-link" href="index.php?page=operator_bookings">Booking menunggu</a><strong><?= $ownerPendingBookings ?></strong></div></article>
    </div>
</section>
<section class="dashboard-group">
    <h2 class="dashboard-section-title">Keuangan</h2>
    <div class="stats-grid operator-finance-stats dashboard-period-stats">
        <article class="stat-card"><div><span>Pemasukan bulan <?= e($currentMonthLabel) ?></span><strong><?= e(money($ownerMonthIncome)) ?></strong></div></article>
        <article class="stat-card"><div><span>Pengeluaran bulan <?= e($currentMonthLabel) ?></span><strong><?= e(money($monthExpense)) ?></strong></div></article>
        <article class="stat-card"><div><span>Pendapatan Bersih bulan <?= e($currentMonthLabel) ?></span><strong><?= e(money($monthNet)) ?></strong></div></article>
        <article class="stat-card"><a class="stat-icon orange stat-icon-link" href="index.php?page=payment_confirmations" aria-label="Buka pembayaran menunggu konfirmasi">◷</a><div><a class="stat-card-label-link" href="index.php?page=payment_confirmations">Menunggu Pembayaran bulan <?= e($currentMonthLabel) ?></a><strong><?= e(money($ownerPaymentSummary['awaiting'])) ?></strong></div></article>
    </div>
    <div class="stats-grid operator-finance-stats dashboard-period-stats">
        <article class="stat-card"><a class="stat-icon green stat-icon-link" href="index.php?page=payments" aria-label="Buka histori pemasukan">↗</a><div><a class="stat-card-label-link" href="index.php?page=payments">Total Pemasukan Tahun <?= $currentYear ?></a><strong><?= e(money($totalIncome)) ?></strong></div></article>
        <article class="stat-card"><a class="stat-icon orange stat-icon-link" href="index.php?page=finance" aria-label="Buka pengeluaran">↘</a><div><a class="stat-card-label-link" href="index.php?page=finance">Total Pengeluaran Tahun <?= $currentYear ?></a><strong><?= e(money($totalExpense)) ?></strong></div></article>
        <article class="stat-card"><a class="stat-icon blue stat-icon-link" href="index.php?page=finance" aria-label="Buka ringkasan keuangan">＝</a><div><a class="stat-card-label-link" href="index.php?page=finance">Pendapatan Bersih Tahun <?= $currentYear ?></a><strong><?= e(money($netIncome)) ?></strong></div></article>
        <article class="stat-card"><a class="stat-icon orange stat-icon-link" href="index.php?page=operator_payment" aria-label="Buka tagihan jatuh tempo">!</a><div><a class="stat-card-label-link" href="index.php?page=operator_payment">Tunggakan saat ini</a><strong><?= e(money($ownerArrears)) ?></strong></div></article>
    </div>
</section>
<div class="income-chart-grid">
<section class="panel income-chart-panel dashboard-chart-shell dashboard-monthly-summary">
    <div class="panel-heading"><div><h2>Ringkasan Bulanan · <?= $currentYear ?></h2></div></div>
    <div class="monthly-recap-chart" role="group" aria-label="Diagram perbandingan pemasukan dan pengeluaran per bulan tahun <?= $currentYear ?>">
        <?php for ($monthOffset = 0; $monthOffset < 12; $monthOffset++):
            $monthDate = (new DateTimeImmutable($currentYear . '-01-01'))->modify('+' . $monthOffset . ' months');
            $monthKey = $monthDate->format('Y-m');
            $hasIncome = array_key_exists($monthKey, $monthlyMap);
            $hasExpense = array_key_exists($monthKey, $monthlyExpenseMap);
            $incomeAmount = $monthlyMap[$monthKey] ?? 0.0;
            $expenseAmount = $monthlyExpenseMap[$monthKey] ?? 0.0;
            $netAmount = $incomeAmount - $expenseAmount;
        ?>
            <div class="monthly-recap-column" role="group" aria-label="<?= e($monthLabels[(int) $monthDate->format('n') - 1] . ' ' . $currentYear . ' · Pemasukan: ' . ($hasIncome ? money($incomeAmount) : 'tidak ada data') . ' · Pengeluaran: ' . ($hasExpense ? money($expenseAmount) : 'tidak ada data') . ' · Pendapatan bersih: ' . money($netAmount)) ?>" title="<?= e($monthLabels[(int) $monthDate->format('n') - 1] . ' ' . $currentYear . ' · Pemasukan: ' . ($hasIncome ? money($incomeAmount) : '—') . ' · Pengeluaran: ' . ($hasExpense ? money($expenseAmount) : '—') . ' · Pendapatan bersih: ' . money($netAmount)) ?>">
                <strong class="monthly-recap-net"><?= ($hasIncome || $hasExpense) ? e($compactMoney($netAmount)) : '' ?></strong>
                <div class="monthly-recap-bars">
                    <span class="monthly-recap-income" style="height:<?= $hasIncome && $incomeAmount > 0 ? max(2, (int) round($incomeAmount / $monthlyPeak * 100)) : 0 ?>%"></span>
                    <span class="monthly-recap-expense" style="height:<?= $hasExpense && $expenseAmount > 0 ? max(2, (int) round($expenseAmount / $monthlyPeak * 100)) : 0 ?>%"></span>
                </div>
                <span class="monthly-recap-month"><?= e(substr($monthLabels[(int) $monthDate->format('n') - 1], 0, 3)) ?></span>
                <span class="visually-hidden">Pemasukan: <?= $hasIncome ? e(money($incomeAmount)) : 'tidak ada data' ?>, Pengeluaran: <?= $hasExpense ? e(money($expenseAmount)) : 'tidak ada data' ?>, Pendapatan bersih: <?= e(money($netAmount)) ?></span>
            </div>
        <?php endfor; ?>
    </div>
    <div class="monthly-recap-legend"><span><i></i>Pemasukan</span><span><i></i>Pengeluaran</span></div>
</section>
<section class="panel income-chart-panel dashboard-chart-shell">
    <div class="panel-heading"><div><h2>Rekap Pemasukan Tahunan</h2><p><?= $currentYear ?> · <?= e(money($currentYearTotal)) ?></p></div></div>
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
<section class="panel dashboard-chart-shell">
    <div class="panel-heading"><div><h2>Pemasukan per kos · <?= e($currentMonthLabel) ?> <?= $currentYear ?></h2></div><a class="text-link" href="index.php?page=payments">Semua transaksi →</a></div>
    <?php if ($propertySummaries): ?><div class="property-finance-chart-grid">
        <?php foreach ($propertySummaries as $summary): ?>
            <?php
            $propertyTarget = (float) $summary['monthly_target'];
            $propertyCollected = (float) $summary['collected'];
            $propertyProgress = $propertyTarget > 0
                ? min(100, (int) round($propertyCollected / $propertyTarget * 100))
                : 0;
            ?>
            <article class="property-finance-chart">
                <h3><?= e($summary['name']) ?></h3>
                <div class="property-finance-values"><strong><?= e(money($propertyCollected)) ?></strong><span><?= e(money($propertyTarget)) ?></span></div>
                <div class="property-finance-bar" role="img" aria-label="<?= e($summary['name']) ?>: pembayaran <?= e(money($propertyCollected)) ?> dari target <?= e(money($propertyTarget)) ?>">
                    <span style="width:<?= $propertyProgress ?>%"></span>
                </div>
                <div class="property-finance-scale"><span>Rp 0</span><span><?= e(money($propertyTarget)) ?></span></div>
                <div class="property-finance-details"><span>Menunggu bulan ini <?= e(money($summary['pending_payments'])) ?></span></div>
            </article>
        <?php endforeach; ?>
    </div><?php else: ?><p class="no-data">Belum ada kos pada akun ini.</p><?php endif; ?>
</section>
<h2 class="dashboard-section-title">Akun dan aktivitas</h2>
<section class="panel dashboard-chart-shell dashboard-activity-section">
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
