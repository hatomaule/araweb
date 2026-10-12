<?php
$platformMetrics = [
    'operators' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='operator'")->fetchColumn(),
    'bookings' => (int) $pdo->query(
        "SELECT (SELECT COUNT(*) FROM bookings)
              + (SELECT COUNT(*) FROM rentals r
                 WHERE r.booking_status<>'not_applicable'
                   AND NOT EXISTS (SELECT 1 FROM bookings bk WHERE bk.rental_id=r.id))"
    )->fetchColumn(),
    'searches' => (int) $pdo->query("SELECT COUNT(*) FROM property_activity_events WHERE event_type='search_result'")->fetchColumn(),
    'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'online' => (int) $pdo->query(
        "SELECT COUNT(*) FROM users
         WHERE role IN ('operator','seeker') AND status='active'
           AND last_seen_at>=DATE_SUB(NOW(),INTERVAL 5 MINUTE)"
    )->fetchColumn(),
    'properties' => (int) $pdo->query('SELECT COUNT(*) FROM kos_buildings')->fetchColumn(),
    'rooms' => (int) $pdo->query('SELECT COUNT(*) FROM rooms')->fetchColumn(),
    'tenants' => (int) $pdo->query("SELECT COUNT(DISTINCT tenant_id) FROM rentals WHERE status='active' AND start_date<=CURDATE() AND (end_date IS NULL OR end_date>=CURDATE())")->fetchColumn(),
    'visitors' => (int) $pdo->query("SELECT COUNT(DISTINCT visitor_key) FROM property_activity_events WHERE event_type='visit'")->fetchColumn(),
];
$monthRows = rows(
    $pdo,
    "SELECT DATE_FORMAT(month_start,'%Y-%m') AS month_key,
            COALESCE((SELECT COUNT(*) FROM bookings bk
                WHERE bk.created_at>=month_start AND bk.created_at<DATE_ADD(month_start,INTERVAL 1 MONTH)),0) AS bookings,
            COALESCE((SELECT COUNT(*) FROM property_activity_events ae
                WHERE ae.event_type='search_result' AND ae.occurred_at>=month_start
                  AND ae.occurred_at<DATE_ADD(month_start,INTERVAL 1 MONTH)),0) AS searches,
            COALESCE((SELECT COUNT(DISTINCT visitor_key) FROM property_activity_events ae
                WHERE ae.event_type='visit' AND ae.occurred_at>=month_start
                  AND ae.occurred_at<DATE_ADD(month_start,INTERVAL 1 MONTH)),0) AS visitors
     FROM (
        SELECT DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL seq MONTH) AS month_start
        FROM (
            SELECT 0 AS seq UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
            UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7
            UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11
        ) months
     ) calendar ORDER BY month_start"
);
$latestActivity = rows(
    $pdo,
    "SELECT ae.event_type,ae.search_query,ae.occurred_at,b.name AS building_name,
            u.name AS visitor_name
     FROM property_activity_events ae
     LEFT JOIN kos_buildings b ON b.id=ae.building_id
     LEFT JOIN users u ON u.id=ae.user_id
     ORDER BY ae.occurred_at DESC LIMIT 30"
);
$platformCards = [
    ['Operator', $platformMetrics['operators'], '♙', 'purple', '?page=platform_operator_accounts'],
    ['Booking tercatat', $platformMetrics['bookings'], '▣', 'purple', '?page=platform_dashboard#platform-booking-activity'],
    ['Pencarian kos', $platformMetrics['searches'], '⌕', 'blue', '?page=search'],
    ['Pengguna pencari', $platformMetrics['users'], '♙', 'green', '?page=platform_seeker_accounts'],
    ['User online · 5 menit terakhir', $platformMetrics['online'], '●', 'green', '?page=platform_dashboard'],
    ['Kos terdaftar', $platformMetrics['properties'], '⌂', 'purple', '?page=platform_listings'],
    ['Kamar terdaftar', $platformMetrics['rooms'], '▦', 'blue', '?page=platform_listings'],
    ['Penghuni aktif', $platformMetrics['tenants'], '♙', 'orange', '?page=platform_seeker_accounts'],
    ['Pengunjung unik', $platformMetrics['visitors'], '◉', 'orange', '?page=home'],
];
?>
<section class="page-heading"><div><span class="eyebrow">DASHBOARD PLATFORM</span><h2>Ringkasan aktivitas Araweb</h2><p>Akumulasi booking, pencarian, pengguna, properti, penghuni, dan pengunjung.</p></div></section>
<div class="stats-grid">
<?php foreach ($platformCards as [$label, $value, $icon, $color, $target]): ?>
    <article class="stat-card"><a class="stat-icon <?= e($color) ?> stat-icon-link" href="index.php<?= e($target) ?>" aria-label="Buka <?= e($label) ?>"><?= e($icon) ?></a><div><span><?= e($label) ?></span><strong><?= number_format($value, 0, ',', '.') ?></strong></div></article>
<?php endforeach; ?>
</div>
<section class="panel" id="platform-booking-activity">
    <div class="panel-heading"><div><h2>Aktivitas 12 bulan terakhir</h2><p>Grafik bulanan untuk booking, hasil pencarian, dan pengunjung unik.</p></div></div>
    <div style="display:grid;grid-template-columns:repeat(12,minmax(34px,1fr));gap:8px;align-items:end;min-height:240px;padding:12px 8px 0;overflow-x:auto">
    <?php foreach ($monthRows as $monthRow):
        $peak = max(1, ...array_map(static fn(array $row): int => max((int) $row['bookings'], (int) $row['searches'], (int) $row['visitors']), $monthRows));
    ?>
        <div style="min-width:34px;display:flex;flex-direction:column;justify-content:end;align-items:center;height:210px;gap:5px" title="Booking: <?= (int) $monthRow['bookings'] ?> · Pencarian: <?= (int) $monthRow['searches'] ?> · Pengunjung: <?= (int) $monthRow['visitors'] ?>">
            <div style="display:flex;align-items:end;gap:2px;height:175px;width:100%">
                <span style="display:block;flex:1;background:#7c5ce7;height:<?= max(2, (int) round((int) $monthRow['bookings'] / $peak * 100)) ?>%" aria-label="Booking"></span>
                <span style="display:block;flex:1;background:#3185d6;height:<?= max(2, (int) round((int) $monthRow['searches'] / $peak * 100)) ?>%" aria-label="Pencarian"></span>
                <span style="display:block;flex:1;background:#36a579;height:<?= max(2, (int) round((int) $monthRow['visitors'] / $peak * 100)) ?>%" aria-label="Pengunjung"></span>
            </div>
            <small><?= e(date('M y', strtotime($monthRow['month_key'] . '-01'))) ?></small>
        </div>
    <?php endforeach; ?>
    </div>
    <p class="muted">■ Booking &nbsp; <span style="color:#3185d6">■</span> Pencarian &nbsp; <span style="color:#36a579">■</span> Pengunjung</p>
</section>
<section class="panel"><div class="panel-heading"><div><h2>Aktivitas terbaru</h2><p>Aktivitas publik dicatat tanpa menyimpan alamat IP.</p></div></div><div class="table-scroll"><table><thead><tr><th>Waktu</th><th>Aktivitas</th><th>Kos</th><th>Pengunjung</th><th>Kata pencarian</th></tr></thead><tbody>
<?php foreach ($latestActivity as $activity): ?>
<tr><td><?= e(date_id($activity['occurred_at'])) ?></td><td><?= e(['visit'=>'Kunjungan','property_view'=>'Melihat kos','room_view'=>'Melihat kamar','search_result'=>'Hasil pencarian'][$activity['event_type']] ?? $activity['event_type']) ?></td><td><?= e($activity['building_name'] ?: '—') ?></td><td><?= e($activity['visitor_name'] ?: 'Anonim') ?></td><td><?= e($activity['search_query'] ?: '—') ?></td></tr>
<?php endforeach; ?>
<?php if (!$latestActivity): ?><tr><td colspan="5" class="no-data">Belum ada aktivitas tercatat.</td></tr><?php endif; ?>
</tbody></table></div></section>
