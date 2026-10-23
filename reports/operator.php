<?php
declare(strict_types=1);

$ownerId = (int) $user['id'];
$propertyActivity = rows(
    $pdo,
    "SELECT b.id,b.name,
       COUNT(CASE WHEN ae.event_type='search_result' THEN 1 END) AS searches,
       COUNT(CASE WHEN ae.event_type='property_view' THEN 1 END) AS property_views,
       COUNT(CASE WHEN ae.event_type='room_view' THEN 1 END) AS room_views,
       COUNT(DISTINCT CASE WHEN ae.event_type IN ('property_view','room_view') THEN ae.visitor_key END) AS unique_visitors
     FROM kos_buildings b LEFT JOIN property_activity_events ae ON ae.building_id=b.id
     WHERE b.owner_user_id=:id GROUP BY b.id,b.name ORDER BY b.name",
    ['id' => $ownerId]
);
?>
<section class="page-heading">
    <div><span class="eyebrow">LAPORAN PROPERTI</span><h2>Performa properti</h2><p>Pantau hasil pencarian dan kunjungan pada properti yang Anda kelola.</p></div>
</section>
<section class="panel">
    <div class="panel-heading"><div><h2>Pencarian dan pengunjung per kos</h2><p>Jumlah pengunjung unik dihitung dari aktivitas melihat kos atau kamar.</p></div><a class="text-link" href="index.php?page=activity">Buka aktivitas →</a></div>
    <div class="table-scroll">
        <table><thead><tr><th>Nama kos</th><th>Hasil pencarian</th><th>Dilihat</th><th>Kamar dilihat</th><th>Pengunjung unik</th></tr></thead>
            <tbody>
                <?php foreach ($propertyActivity as $activity): ?>
                    <tr><td><strong><?= e($activity['name']) ?></strong></td><td><?= (int) $activity['searches'] ?></td><td><?= (int) $activity['property_views'] ?></td><td><?= (int) $activity['room_views'] ?></td><td><?= (int) $activity['unique_visitors'] ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$propertyActivity): ?><tr><td colspan="5" class="no-data">Belum ada kos terdaftar.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
