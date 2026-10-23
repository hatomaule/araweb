<?php
declare(strict_types=1);

$isAdmin = $user['role'] === 'admin';
$isOwner = $user['role'] === 'owner';
$eventType = isset($_GET['type']) && is_string($_GET['type'])
    && in_array($_GET['type'], ['visit', 'search_result', 'property_view', 'room_view'], true)
        ? $_GET['type'] : '';
$conditions = [];
$params = [];
if ($isOwner) {
    $conditions[] = 'b.owner_user_id=:owner_id';
    $params['owner_id'] = (int) $user['id'];
} elseif (!$isAdmin) {
    $conditions[] = 'ae.user_id=:user_id';
    $params['user_id'] = (int) $user['id'];
}
if ($eventType !== '') {
    $conditions[] = 'ae.event_type=:event_type';
    $params['event_type'] = $eventType;
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$events = rows(
    $pdo,
    "SELECT ae.event_type,ae.search_query,ae.occurred_at,b.name AS building_name,
            CASE WHEN ae.user_id IS NULL THEN 'Pengunjung anonim' ELSE COALESCE(u.name,'Pengguna') END AS visitor_name
     FROM property_activity_events ae
     LEFT JOIN kos_buildings b ON b.id=ae.building_id
     LEFT JOIN users u ON u.id=ae.user_id
     {$where}
     ORDER BY ae.occurred_at DESC,ae.id DESC LIMIT 200",
    $params
);
$eventLabels = [
    'visit' => 'Kunjungan',
    'search_result' => 'Hasil pencarian',
    'property_view' => 'Melihat kos',
    'room_view' => 'Melihat kamar',
];
?>
<section class="page-heading"><div><span class="eyebrow">AKTIVITAS</span><h2>Aktivitas</h2>
    <p><?= $isAdmin ? 'Pantau interaksi publik di seluruh platform.' : ($isOwner ? 'Aktivitas pencarian dan kunjungan pada kos yang Anda kelola.' : 'Aktivitas pencarian dan kunjungan dari akun Anda.') ?></p>
</div></section>
<section class="panel">
    <form method="get" class="activity-log-filters" data-auto-submit>
        <input type="hidden" name="page" value="activity">
        <label>Jenis aktivitas
            <select name="type"><option value="">Semua aktivitas</option>
                <?php foreach ($eventLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $eventType === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
    <div class="panel-heading"><div><h2>Aktivitas pengunjung</h2><p class="activity-log-summary">Maksimal 200 aktivitas terbaru. Statistik tetap bersumber dari data analitik.</p></div></div>
    <div class="table-scroll"><table><thead><tr><th>Waktu</th><th>Aktivitas</th><th>Kos</th><?php if ($isAdmin): ?><th>Pengunjung</th><?php endif; ?><th>Pencarian</th></tr></thead><tbody>
        <?php foreach ($events as $event): ?>
            <tr><td><?= e(date_id($event['occurred_at'])) ?></td><td><?= e($eventLabels[$event['event_type']] ?? $event['event_type']) ?></td><td><?= e($event['building_name'] ?: '—') ?></td><?php if ($isAdmin): ?><td><?= e($event['visitor_name']) ?></td><?php endif; ?><td><?= e($event['search_query'] ?: '—') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$events): ?><tr><td colspan="<?= $isAdmin ? 5 : 4 ?>" class="no-data">Belum ada aktivitas yang sesuai filter.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
