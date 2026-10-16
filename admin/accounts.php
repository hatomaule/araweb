<?php
$isUnhousedAccountList = $page === 'platform_unhoused_users';
$accountRole = in_array($page, ['platform_seeker_accounts', 'platform_unhoused_users'], true)
    ? 'seeker'
    : 'operator';
$unhousedAccountFilter = $isUnhousedAccountList
    ? "AND NOT EXISTS (
        SELECT 1
        FROM tenants unhoused_tenant
        JOIN rentals unhoused_rental ON unhoused_rental.tenant_id=unhoused_tenant.id
        WHERE unhoused_tenant.user_id=u.id AND unhoused_tenant.status='active'
          AND (
              (unhoused_rental.status='active'
               AND (unhoused_rental.end_date IS NULL OR unhoused_rental.end_date>=CURDATE()))
              OR (unhoused_rental.status='upcoming'
                  AND (unhoused_rental.booking_expires_at IS NULL
                       OR unhoused_rental.booking_expires_at>NOW()))
          )
    )
    AND NOT EXISTS (
        SELECT 1 FROM bookings active_booking
        WHERE active_booking.user_id=u.id
          AND active_booking.status IN ('awaiting_payment','proof_pending','queued','room_offered')
    )"
    : '';
$accounts = rows(
    $pdo,
    "SELECT u.id,u.name,u.email,u.phone,u.gender,u.profile_photo_path,u.role,u.status,u.created_at,u.last_seen_at,
            b.id AS current_building_id,b.name AS current_building_name,
            CASE WHEN b.id IS NULL THEN u.province_code ELSE b.province_code END AS province_codes,
            CASE WHEN b.id IS NULL THEN u.regency_code ELSE b.regency_code END AS regency_codes,
            CASE WHEN b.id IS NULL THEN u.district_code ELSE b.district_code END AS district_codes,
            CASE WHEN b.id IS NULL THEN u.village_code ELSE b.village_code END AS village_codes
     FROM users u
     LEFT JOIN tenants t ON t.id=(
        SELECT current_tenant.id FROM tenants current_tenant
        WHERE current_tenant.user_id=u.id AND current_tenant.status='active'
        ORDER BY current_tenant.created_at DESC,current_tenant.id DESC
        LIMIT 1
     )
     LEFT JOIN rentals r ON r.id=(
        SELECT current_rental.id FROM rentals current_rental
        WHERE current_rental.tenant_id=t.id
          AND current_rental.status='active'
          AND (current_rental.end_date IS NULL OR current_rental.end_date>=CURDATE())
        ORDER BY current_rental.start_date DESC,current_rental.id DESC
        LIMIT 1
     )
     LEFT JOIN rooms rm ON rm.id=r.room_id
     LEFT JOIN kos_buildings b ON b.id=rm.building_id
     WHERE u.role=:role {$unhousedAccountFilter}
     ORDER BY b.name IS NULL,b.name,u.name,u.id",
    ['role' => $accountRole]
);
if ($accountRole === 'operator') {
    $operatorRegions = [];
    $buildingRegions = rows(
        $pdo,
        "SELECT owner_user_id,province_code,regency_code,district_code,village_code
         FROM kos_buildings
         WHERE owner_user_id IN (SELECT id FROM users WHERE role='operator')"
    );
    foreach ($buildingRegions as $buildingRegion) {
        $operatorId = (int) $buildingRegion['owner_user_id'];
        foreach (['province', 'regency', 'district', 'village'] as $level) {
            $code = $buildingRegion[$level . '_code'];
            if ($code !== null) {
                $operatorRegions[$operatorId][$level][$code] = true;
            }
        }
    }
    foreach ($accounts as &$account) {
        foreach (['province', 'regency', 'district', 'village'] as $level) {
            $account[$level . '_codes'] = implode(
                ',',
                array_keys($operatorRegions[(int) $account['id']][$level] ?? [])
            );
        }
    }
    unset($account);
}
$accountGroups = [];
foreach ($accounts as $account) {
    $groupKey = $accountRole === 'seeker'
        ? (string) ($account['current_building_id'] ?? 'unassigned')
        : 'all';
    $accountGroups[$groupKey]['building_name'] = $account['current_building_name'] ?? null;
    $accountGroups[$groupKey]['accounts'][] = $account;
}
$heading = $accountRole === 'operator' ? 'Akun operator'
    : ($isUnhousedAccountList ? 'User pengguna tanpa hunian' : 'Akun User');
$roleLabel = $accountRole === 'operator' ? 'Operator' : 'User';
?>
<div class="page-actions"><p><?= $isUnhousedAccountList
    ? 'Kelola akun User yang tidak memiliki sewa atau booking aktif saat ini. Akun dapat kembali menghuni kos melalui booking atau penambahan penghuni.'
    : 'Kelola akun ' . ($accountRole === 'operator' ? 'operator' : 'User') . '. Riwayat sewa dan pembayaran dipertahankan saat akun dihapus.' ?></p></div>
<div class="panel account-management-panel">
    <div class="panel-heading"><div><h2><?= e($heading) ?></h2><p><?= count($accounts) ?> akun terdaftar</p></div></div>
    <?php if ($accountGroups): ?>
    <div class="table-scroll"><table class="account-management-table" data-region-filter <?= $accountRole === 'seeker' && !$isUnhousedAccountList ? 'data-building-filter' : '' ?>><thead><tr><th>Pengguna</th><th>ID</th><th>Peran</th><th>Status</th><th>Aktivitas terakhir</th><th>Kelola akun</th></tr></thead><tbody>
    <?php foreach ($accountGroups as $groupKey => $group): ?>
    <?php if ($accountRole === 'seeker'): ?>
        <tr class="account-group-heading-row" data-table-group-heading data-table-group-key="<?= e((string) $groupKey) ?>">
            <td colspan="6"><div class="account-group-heading"><h3><?= e($isUnhousedAccountList
                ? 'Tidak sedang menghuni kos'
                : ($group['building_name'] ?: 'Belum tercatat sebagai penghuni aktif')) ?></h3><span data-table-group-count><?= count($group['accounts']) ?> user</span></div></td>
        </tr>
    <?php endif; ?>
    <?php foreach ($group['accounts'] as $account): ?>
        <tr data-province-codes="<?= e($account['province_codes'] ?? '') ?>"
            data-regency-codes="<?= e($account['regency_codes'] ?? '') ?>"
            data-district-codes="<?= e($account['district_codes'] ?? '') ?>"
            data-village-codes="<?= e($account['village_codes'] ?? '') ?>"
            <?= $accountRole === 'seeker' && !$isUnhousedAccountList
            ? 'data-building-filter="' . e((string) ($account['current_building_id'] ?? '__none')) . '" data-building-label="' . e($account['current_building_name'] ?: 'Belum tercatat sebagai penghuni aktif') . '" data-table-group-key="' . e((string) ($account['current_building_id'] ?? 'unassigned')) . '"'
            : ($accountRole === 'seeker' ? 'data-table-group-key="' . e((string) $groupKey) . '"' : '') ?>>
            <td><div class="account-person"><a class="account-profile-avatar" href="index.php?page=<?= $accountRole === 'operator' ? 'platform_operator_profile' : 'platform_user_profile' ?>&amp;id=<?= (int) $account['id'] ?>" aria-label="Buka profil <?= e($account['name']) ?>" title="Buka profil"><?= avatar_markup($account, 'profile-avatar small') ?></a><div><strong><?= e($account['name']) ?></strong><small class="subline"><?= e($account['email'] ?: 'Email belum diisi') ?></small><small class="subline"><?= e($account['phone'] ?: 'Nomor telepon belum diisi') ?></small></div></div></td>
            <td>ID-<?= str_pad((string) $account['id'], 6, '0', STR_PAD_LEFT) ?></td>
            <td><?= e($roleLabel) ?></td>
            <td><span class="badge <?= e($account['status']) ?>"><?= e(status_label($account['status'])) ?></span></td>
            <td><?= $account['last_seen_at'] ? e(date('d-m-Y H:i', strtotime($account['last_seen_at']))) : 'Belum pernah aktif' ?></td>
            <td><div class="account-row-actions">
                <button class="button secondary" type="button" data-dialog-open="reset-account-<?= (int) $account['id'] ?>">Reset password</button>
                <button class="button secondary" type="button" data-dialog-open="status-account-<?= (int) $account['id'] ?>"><?= $account['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                <?php if ($accountRole === 'seeker'): ?><button class="button danger" type="button" data-dialog-open="delete-account-<?= (int) $account['id'] ?>">Hapus</button><?php endif; ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    <?php if (!$accounts): ?><div class="table-scroll"><table><tbody><tr><td class="no-data">Belum ada <?= $accountRole === 'operator' ? 'akun operator' : 'akun User' ?>.</td></tr></tbody></table></div><?php endif; ?>
    <?php foreach ($accounts as $account):
        $id = (int) $account['id'];
    ?>
        <dialog class="action-dialog" id="reset-account-<?= $id ?>">
            <form method="post" class="action-dialog-form"><?= csrf_field() ?><input type="hidden" name="action" value="admin_reset_account_password"><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="account_list_page" value="<?= $isUnhousedAccountList ? 'platform_unhoused_users' : ($accountRole === 'seeker' ? 'platform_seeker_accounts' : 'platform_operator_accounts') ?>">
                <button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button>
                <span class="dialog-eyebrow">KEAMANAN AKUN</span><h2>Reset password</h2><p>Buat password baru untuk <strong><?= e($account['name']) ?></strong>.</p>
                <label class="field"><span>Password baru</span><input type="password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password" placeholder="Minimal 8 karakter" required></label>
                <label class="confirmation-check"><input type="checkbox" name="confirm_account_action" value="yes" required><span>Saya memahami password akun ini akan diganti.</span></label>
                <div class="dialog-actions"><button class="button secondary" type="button" data-dialog-close>Batal</button><button class="button primary" type="submit">Simpan password</button></div>
            </form>
        </dialog>
        <dialog class="action-dialog" id="status-account-<?= $id ?>">
            <form method="post" class="action-dialog-form"><?= csrf_field() ?><input type="hidden" name="action" value="admin_set_account_status"><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="status" value="<?= $account['status'] === 'active' ? 'inactive' : 'active' ?>"><input type="hidden" name="account_list_page" value="<?= $isUnhousedAccountList ? 'platform_unhoused_users' : ($accountRole === 'seeker' ? 'platform_seeker_accounts' : 'platform_operator_accounts') ?>">
                <button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button>
                <span class="dialog-eyebrow">KONFIRMASI AKUN</span><h2><?= $account['status'] === 'active' ? 'Nonaktifkan akun?' : 'Aktifkan akun?' ?></h2><p><?= $account['status'] === 'active' ? 'Akun ' . e($account['name']) . ' tidak dapat masuk selama dinonaktifkan.' : 'Akun ' . e($account['name']) . ' dapat masuk kembali setelah diaktifkan.' ?></p>
                <label class="confirmation-check"><input type="checkbox" name="confirm_account_action" value="yes" required><span>Saya memahami akun ini akan <?= $account['status'] === 'active' ? 'dinonaktifkan' : 'diaktifkan' ?>.</span></label>
                <div class="dialog-actions"><button class="button secondary" type="button" data-dialog-close>Batal</button><button class="button <?= $account['status'] === 'active' ? 'danger' : 'primary' ?>" type="submit"><?= $account['status'] === 'active' ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?></button></div>
            </form>
        </dialog>
        <?php if ($accountRole === 'seeker'): ?>
        <dialog class="action-dialog" id="delete-account-<?= $id ?>">
            <form method="post" class="action-dialog-form"><?= csrf_field() ?><input type="hidden" name="action" value="admin_delete_account"><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="account_list_page" value="<?= $isUnhousedAccountList ? 'platform_unhoused_users' : 'platform_seeker_accounts' ?>">
                <button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button>
                <span class="dialog-eyebrow">KONFIRMASI AKUN USER</span><h2>Hapus akun pengguna?</h2><p>Akun <strong><?= e($account['name']) ?></strong> akan dihapus permanen dan tidak dapat digunakan untuk masuk. Histori sewa, booking, pencarian, aktivitas, dan pembayaran tetap disimpan; relasi ke akun akan dilepas. Akun dengan sewa aktif atau booking berjalan tidak dapat dihapus.</p>
                <label class="confirmation-check"><input type="checkbox" name="confirm_account_action" value="yes" required><span>Saya memahami akun pengguna akan dihapus permanen dan histori transaksinya tetap disimpan.</span></label>
                <div class="dialog-actions"><button class="button secondary" type="button" data-dialog-close>Batal</button><button class="button danger" type="submit">Hapus akun</button></div>
            </form>
        </dialog>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
