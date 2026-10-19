<?php
declare(strict_types=1);

$isAdmin = $user['role'] === 'admin';
$isOwner = $user['role'] === 'owner';
$roleFilter = $isAdmin && isset($_GET['role']) && is_string($_GET['role'])
    && in_array($_GET['role'], ['admin', 'owner', 'seeker', 'system'], true)
        ? $_GET['role'] : '';
$categoryFilter = isset($_GET['category']) && is_string($_GET['category'])
    && in_array($_GET['category'], ['auth', 'business', 'request', 'system'], true)
        ? $_GET['category'] : '';
$outcomeFilter = isset($_GET['outcome']) && is_string($_GET['outcome'])
    && in_array($_GET['outcome'], ['success', 'error'], true)
        ? $_GET['outcome'] : '';
$actorFilter = $isAdmin && isset($_GET['actor_id'])
    ? filter_var($_GET['actor_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;

$conditions = [];
$params = [];
if ($isAdmin && $roleFilter !== '') {
    $conditions[] = 'al.actor_role=:actor_role';
    $params['actor_role'] = $roleFilter;
}
if ($isAdmin && $actorFilter !== false) {
    $conditions[] = 'al.actor_user_id=:actor_id';
    $params['actor_id'] = $actorFilter;
}
if ($categoryFilter !== '') {
    $conditions[] = 'al.category=:category';
    $params['category'] = $categoryFilter;
}
if ($outcomeFilter !== '') {
    $conditions[] = 'al.outcome=:outcome';
    $params['outcome'] = $outcomeFilter;
}
if ($isOwner) {
    $conditions[] = "(al.building_id IN (SELECT id FROM kos_buildings WHERE owner_user_id=:owner_id)
        OR (al.actor_user_id=:operator_id AND al.building_id IS NULL
            AND al.page IN ('dashboard','buildings','building_form','rooms','room_form','gallery',
                'tenants','tenant_form','tenant_profile','tenant_payments','invoices','invoice_form',
                'operator_payment','payments','operator_bookings','payment_confirmations','bank_settings')))";
    $params['owner_id'] = (int) $user['id'];
    $params['operator_id'] = (int) $user['id'];
} elseif (!$isAdmin) {
    $conditions[] = 'al.actor_user_id=:user_id';
    $params['user_id'] = (int) $user['id'];
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$logs = rows(
    $pdo,
    "SELECT al.id,al.actor_role,al.category,al.action,al.outcome,al.page,al.summary,
            al.created_at,al.building_id,b.name AS building_name,u.name AS actor_name
     FROM activity_logs al
     LEFT JOIN users u ON u.id=al.actor_user_id
     LEFT JOIN kos_buildings b ON b.id=al.building_id
     {$where}
     ORDER BY al.created_at DESC,al.id DESC LIMIT 200",
    $params
);
$accounts = $isAdmin ? rows(
    $pdo,
    "SELECT id,name,role FROM users WHERE role IN ('admin','owner','seeker')
     ORDER BY role,name,id"
) : [];
$visitorEvents = $isOwner ? rows(
    $pdo,
    "SELECT ae.event_type,ae.search_query,ae.occurred_at,b.name AS building_name,
            CASE WHEN ae.user_id IS NULL THEN 'Pengunjung anonim' ELSE 'Pengunjung terdaftar' END AS visitor_label
     FROM property_activity_events ae
     JOIN kos_buildings b ON b.id=ae.building_id
     WHERE b.owner_user_id=:owner_id
       AND ae.event_type IN ('search_result','property_view','room_view')
     ORDER BY ae.occurred_at DESC,ae.id DESC LIMIT 50",
    ['owner_id' => (int) $user['id']]
) : [];
$roleLabels = ['admin' => 'Admin', 'owner' => 'Owner', 'seeker' => 'User', 'system' => 'Sistem'];
$categoryLabels = ['auth' => 'Autentikasi', 'business' => 'Aktivitas', 'request' => 'Akses halaman', 'system' => 'Sistem'];
?>
<section class="page-heading">
    <div><span class="eyebrow">AUDIT AKTIVITAS</span><h2>Log aktivitas</h2>
        <p><?= $isAdmin ? 'Pantau aktivitas, kegagalan, dan tindakan pada seluruh platform.' : ($isOwner ? 'Aktivitas yang berkaitan dengan kos dan penghuni yang Anda kelola.' : 'Riwayat aktivitas pada akun Anda.') ?></p>
    </div>
</section>
<section class="panel">
    <?php if ($isAdmin || $isOwner): ?>
        <form method="get" class="activity-log-filters">
            <input type="hidden" name="page" value="activity_log">
            <?php if ($isAdmin): ?>
                <label>Role
                    <select name="role"><option value="">Semua role</option>
                        <?php foreach ($roleLabels as $role => $label): ?><option value="<?= e($role) ?>" <?= $roleFilter === $role ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Akun
                    <select name="actor_id"><option value="">Semua akun</option>
                        <?php foreach ($accounts as $account): ?><option value="<?= (int) $account['id'] ?>" <?= $actorFilter !== false && (int) $actorFilter === (int) $account['id'] ? 'selected' : '' ?>><?= e($account['name']) ?> · <?= e($roleLabels[$account['role']] ?? $account['role']) ?></option><?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label>Jenis
                <select name="category"><option value="">Semua jenis</option>
                    <?php foreach ($categoryLabels as $category => $label): ?><option value="<?= e($category) ?>" <?= $categoryFilter === $category ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Hasil
                <select name="outcome"><option value="">Semua hasil</option>
                    <option value="success" <?= $outcomeFilter === 'success' ? 'selected' : '' ?>>Berhasil</option>
                    <option value="error" <?= $outcomeFilter === 'error' ? 'selected' : '' ?>>Gagal / error</option>
                </select>
            </label>
            <button class="button primary" type="submit">Terapkan filter</button>
            <a class="button secondary" href="index.php?page=activity_log">Reset</a>
        </form>
    <?php endif; ?>
    <div class="panel-heading"><div><h2>Aktivitas aplikasi</h2><p class="activity-log-summary">Menampilkan maksimal 200 aktivitas terbaru.</p></div></div>
    <div class="table-scroll"><table>
        <thead><tr><th>Waktu</th><?php if ($isAdmin): ?><th>Akun</th><th>Role</th><?php endif; ?><th>Aktivitas</th><th>Jenis</th><th>Hasil</th><th>Kos terkait</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= e(date_id($log['created_at'])) ?></td>
                <?php if ($isAdmin): ?><td><?= e($log['actor_name'] ?: 'Sistem') ?></td><td><?= e($roleLabels[$log['actor_role']] ?? $log['actor_role']) ?></td><?php endif; ?>
                <td><strong><?= e($log['action'] === 'login_failed' ? 'Login gagal' : str_replace('_', ' ', $log['action'])) ?></strong><br><small><?= e($log['summary']) ?></small></td>
                <td><?= e($categoryLabels[$log['category']] ?? $log['category']) ?></td>
                <td><span class="badge <?= $log['outcome'] === 'error' ? 'warning' : 'published' ?>"><?= $log['outcome'] === 'error' ? 'Error' : 'Berhasil' ?></span></td>
                <td><?= e($log['building_name'] ?: '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="<?= $isAdmin ? 7 : 5 ?>" class="no-data">Tidak ada aktivitas yang sesuai filter.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
<?php if ($isOwner): ?>
<section class="panel">
    <div class="panel-heading"><div><h2>Aktivitas pengunjung properti</h2><p>Informasi kunjungan dan pencarian kos milik Anda; alamat IP tidak disimpan.</p></div></div>
    <div class="table-scroll"><table><thead><tr><th>Waktu</th><th>Kos</th><th>Aktivitas</th><th>Pengunjung</th><th>Pencarian</th></tr></thead><tbody>
        <?php foreach ($visitorEvents as $event): ?>
            <tr><td><?= e(date_id($event['occurred_at'])) ?></td><td><?= e($event['building_name']) ?></td><td><?= e(['search_result' => 'Hasil pencarian', 'property_view' => 'Melihat kos', 'room_view' => 'Melihat kamar'][$event['event_type']] ?? $event['event_type']) ?></td><td><?= e($event['visitor_label']) ?></td><td><?= e($event['search_query'] ?: '—') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$visitorEvents): ?><tr><td colspan="5" class="no-data">Belum ada aktivitas pengunjung pada kos Anda.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php endif; ?>
