<?php
declare(strict_types=1);

$isAdmin = $user['role'] === 'admin';
$isOwner = $user['role'] === 'owner';
$filters = activity_log_filter_query($_GET, $user);
[$where, $params] = $filters;
$roleFilter = isset($_GET['role']) && is_string($_GET['role'])
    && in_array($_GET['role'], ['admin', 'owner', 'seeker', 'system'], true) ? $_GET['role'] : '';
$categoryFilter = isset($_GET['category']) && is_string($_GET['category'])
    && in_array($_GET['category'], ['auth', 'business', 'request', 'system'], true) ? $_GET['category'] : '';
$outcomeFilter = isset($_GET['outcome']) && is_string($_GET['outcome'])
    && in_array($_GET['outcome'], ['success', 'error'], true) ? $_GET['outcome'] : '';
$actorFilter = $isAdmin && isset($_GET['actor_id'])
    ? filter_var($_GET['actor_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
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
$roleLabels = ['admin' => 'Admin', 'owner' => 'Owner', 'seeker' => 'User', 'system' => 'Sistem'];
$categoryLabels = ['auth' => 'Autentikasi', 'business' => 'Aktivitas', 'request' => 'Akses halaman', 'system' => 'Sistem'];
?>
<section class="page-heading">
    <div><span class="eyebrow">LOG</span><h2>Log</h2>
        <p><?= $isAdmin ? 'Pantau tindakan dan kegagalan pada seluruh platform.' : ($isOwner ? 'Log tindakan yang berkaitan dengan kos dan penghuni yang Anda kelola.' : 'Log tindakan pada akun Anda.') ?></p>
    </div>
</section>
<section class="panel">
    <?php if ($isAdmin || $isOwner): ?>
        <form method="get" class="activity-log-filters" data-auto-submit>
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
            <?php if ($isAdmin): ?>
                <input type="hidden" name="q" value="<?= e(isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '') ?>">
            <?php endif; ?>
            <?php if ($isAdmin): ?><button class="button danger" type="button" data-dialog-open="delete-activity-logs">Hapus log</button><?php endif; ?>
        </form>
    <?php endif; ?>
    <div class="panel-heading"><div><h2>Log aplikasi</h2><p class="activity-log-summary">Menampilkan maksimal 200 catatan terbaru.</p></div></div>
    <div class="table-scroll"><table id="activity-log-table">
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
<?php if ($isAdmin): ?>
<dialog class="action-dialog" id="delete-activity-logs">
    <form method="post" class="action-dialog-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete_activity_logs">
        <?php foreach (['role', 'actor_id', 'category', 'outcome'] as $filterName): ?>
            <?php if (isset($_GET[$filterName]) && is_string($_GET[$filterName]) && $_GET[$filterName] !== ''): ?>
                <input type="hidden" name="<?= e($filterName) ?>" value="<?= e($_GET[$filterName]) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <input type="hidden" name="q" value="<?= e(isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '') ?>" data-log-delete-search>
        <button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button>
        <span class="dialog-eyebrow">KONFIRMASI PENGHAPUSAN LOG</span>
        <h2>Hapus log yang cocok?</h2>
        <p>Log yang cocok dengan filter dan pencarian saat ini akan dihapus. Statistik kunjungan dan pencarian tetap tersimpan.</p>
        <label class="confirmation-check"><input type="checkbox" name="confirm_delete_logs" value="yes" required><span>Saya memahami tindakan ini menghapus log audit yang cocok.</span></label>
        <div class="dialog-actions"><button class="button secondary" type="button" data-dialog-close>Batal</button><button class="button danger" type="submit">Hapus log</button></div>
    </form>
</dialog>
<?php endif; ?>
