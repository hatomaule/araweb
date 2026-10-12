<?php
$tenantScope = $user['role'] === 'operator'
    ? 'WHERE EXISTS (
        SELECT 1 FROM rentals scope_r
        JOIN rooms scope_rm ON scope_rm.id=scope_r.room_id
        JOIN kos_buildings scope_b ON scope_b.id=scope_rm.building_id
        WHERE scope_r.tenant_id=t.id AND scope_b.owner_user_id=:owner_scope_id
          AND scope_r.status=\'active\' AND scope_r.start_date<=CURDATE()
          AND (scope_r.end_date IS NULL OR scope_r.end_date>=CURDATE())
    )'
    : '';
$activeRentalScope = $user['role'] === 'operator'
    ? 'AND EXISTS (
        SELECT 1 FROM rooms active_rm
        JOIN kos_buildings active_b ON active_b.id=active_rm.building_id
        WHERE active_rm.id=r.room_id AND active_b.owner_user_id=:owner_active_id
    )'
    : '';
$activeRentalRoomScope = $user['role'] === 'operator'
    ? 'AND EXISTS (
        SELECT 1 FROM rooms active_room_rm
        JOIN kos_buildings active_room_b ON active_room_b.id=active_room_rm.building_id
        WHERE active_room_rm.id=r.room_id AND active_room_b.owner_user_id=:owner_room_scope_id
    )'
    : '';
$tenantParameters = $user['role'] === 'operator'
    ? [
        'owner_scope_id' => $user['id'],
        'owner_active_id' => $user['id'],
        'owner_room_scope_id' => $user['id'],
    ]
    : [];
$list = rows(
    $pdo,
    "SELECT t.*,u.id AS account_user_id,u.status AS account_status,u.last_seen_at AS account_last_seen_at,
     u.gender,u.profile_photo_path,u.avatar_key,
     (SELECT COUNT(*) FROM rentals r
      WHERE r.tenant_id=t.id AND r.status='active'
        AND r.start_date<=CURDATE() AND (r.end_date IS NULL OR r.end_date>=CURDATE())
        {$activeRentalScope}) AS active_rentals,
     (SELECT r.room_id FROM rentals r
      WHERE r.tenant_id=t.id AND r.status='active'
        AND r.start_date<=CURDATE() AND (r.end_date IS NULL OR r.end_date>=CURDATE())
        {$activeRentalRoomScope} ORDER BY r.id DESC LIMIT 1) AS active_room_id
     FROM tenants t LEFT JOIN users u ON u.id=t.user_id {$tenantScope}
     ORDER BY t.created_at DESC",
    $tenantParameters
);
$availableRooms = $user['role'] === 'operator'
    ? rows(
        $pdo,
        "SELECT rm.id,rm.room_number,b.name AS building_name
         FROM rooms rm JOIN kos_buildings b ON b.id=rm.building_id
         WHERE b.owner_user_id=:owner_id AND b.status='active'
           AND b.approval_status='approved' AND rm.status='available'
           AND NOT EXISTS (
               SELECT 1 FROM rentals r WHERE r.room_id=rm.id
                 AND r.status IN ('active','upcoming')
                 AND (r.status='active' OR r.booking_expires_at IS NULL OR r.booking_expires_at>NOW())
           )
         ORDER BY b.name,rm.room_number",
        ['owner_id' => $user['id']]
    )
    : [];
?>
<div class="page-actions">
    <p><?= $user['role'] === 'operator'
        ? 'Kelola akun penghuni, lihat histori, ganti password, pindahkan kamar, atau akhiri sewa.'
        : 'Kelola informasi pelanggan dan kontak darurat.' ?></p>
    <a class="button primary" href="index.php?page=tenant_form">
        ＋ <?= $user['role'] === 'operator' ? 'Tambah penghuni' : 'Tambah pelanggan' ?>
    </a>
</div>
<div class="panel">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th><?= $user['role'] === 'operator' ? 'Penghuni' : 'Pelanggan' ?></th><th>ID</th><th>Telepon</th>
                    <th>Sewa aktif</th><th>Status</th><th>Aksi</th>
                    <?php if ($user['role'] === 'operator'): ?><th>Aktivitas akun</th><th>Akun pengguna</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($list as $row): ?>
                    <tr>
                        <td>
                            <?php if ($user['role'] === 'operator' && $row['account_user_id']): ?>
                                <a class="tenant-avatar-link" href="index.php?page=tenant_profile&amp;id=<?= (int) $row['account_user_id'] ?>">
                                    <?= avatar_markup([
                                        'name' => $row['full_name'],
                                        'gender' => $row['gender'] ?? null,
                                        'profile_photo_path' => $row['profile_photo_path'] ?? null,
                                        'avatar_key' => $row['avatar_key'] ?? null,
                                    ], 'profile-avatar small') ?>
                                    <strong><?= e($row['full_name']) ?></strong>
                                </a>
                            <?php else: ?>
                                <strong><?= e($row['full_name']) ?></strong>
                            <?php endif; ?>
                            <small class="subline"><?= e($row['email'] ?: '—') ?></small>
                        </td>
                        <td><?= $row['account_user_id'] ? 'ID-' . str_pad((string) $row['account_user_id'], 6, '0', STR_PAD_LEFT) : '—' ?></td>
                        <td><?= e($row['phone']) ?></td>
                        <td><?= (int) $row['active_rentals'] ?></td>
                        <td><span class="badge <?= e($row['status']) ?>"><?= e(status_label($row['status'])) ?></span></td>
                        <td class="actions">
                            <?php if ($user['role'] === 'admin'): ?>
                                <a class="text-link" href="index.php?page=tenant_form&amp;id=<?= (int) $row['id'] ?>">Edit</a>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="tenant_status">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $row['status'] === 'active' ? 'inactive' : 'active' ?>">
                                    <button class="text-button" type="submit"><?= $row['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                                </form>
                            <?php endif; ?>
                            <a class="button secondary compact-button" href="index.php?page=tenant_payments&amp;id=<?= (int) $row['id'] ?>">Histori</a>
                        </td>
                        <?php if ($user['role'] === 'operator'): ?>
                            <td><?= $row['account_user_id'] && $row['account_last_seen_at']
                                ? e(date('d-m-Y H:i', strtotime($row['account_last_seen_at'])))
                                : ($row['account_user_id'] ? 'Belum pernah aktif' : '—') ?></td>
                            <td>
                                <?php if ($row['account_user_id']): ?>
                                    <div class="tenant-management-actions">
                                        <button class="button secondary" type="button"
                                            data-dialog-open="reset-tenant-password-<?= (int) $row['id'] ?>">
                                            Reset password
                                        </button>
                                        <?php if ((int) $row['active_rentals'] > 0): ?>
                                        <button class="button secondary" type="button"
                                            data-dialog-open="move-tenant-<?= (int) $row['id'] ?>">
                                            Pindahkan
                                        </button>
                                        <button class="button danger" type="button"
                                            data-dialog-open="remove-tenant-<?= (int) $row['id'] ?>">
                                            Keluarkan
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <small class="subline">Tidak ada akun pengguna terkait</small>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$list): ?>
                    <tr>
                        <td colspan="<?= $user['role'] === 'operator' ? '8' : '6' ?>" class="no-data">
                            <?= $user['role'] === 'operator' ? 'Belum ada penghuni aktif di kos ini.' : 'Belum ada data pelanggan.' ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($user['role'] === 'operator'): ?>
    <?php foreach ($list as $row): ?>
        <?php if ($row['account_user_id']): ?>
            <dialog class="action-dialog" id="reset-tenant-password-<?= (int) $row['id'] ?>">
                <form method="post" class="action-dialog-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="operator_reset_tenant_password">
                    <input type="hidden" name="tenant_id" value="<?= (int) $row['id'] ?>">
                    <button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button>
                    <span class="dialog-eyebrow">KEAMANAN AKUN</span>
                    <h2>Reset password</h2>
                    <p>Buat password baru untuk <strong><?= e($row['full_name']) ?></strong>.</p>
                    <label class="field">
                        <span>Password baru</span>
                        <input type="password" name="new_password" minlength="8" maxlength="72"
                            autocomplete="new-password" placeholder="Minimal 8 karakter" required>
                    </label>
                    <label class="confirmation-check">
                        <input type="checkbox" name="confirm_account_action" value="yes" required>
                        <span>Saya memahami password akun penghuni ini akan diganti.</span>
                    </label>
                    <div class="dialog-actions">
                        <button class="button secondary" type="button" data-dialog-close>Batal</button>
                        <button class="button primary" type="submit">Simpan password</button>
                    </div>
                </form>
            </dialog>
        <?php endif; ?>
        <?php if ($user['role'] === 'operator' && $row['account_user_id'] && (int) $row['active_rentals'] > 0): ?>
            <dialog class="action-dialog" id="move-tenant-<?= (int) $row['id'] ?>">
                <form method="post" class="action-dialog-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="operator_move_tenant_room">
                    <input type="hidden" name="tenant_id" value="<?= (int) $row['id'] ?>">
                    <button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button>
                    <span class="dialog-eyebrow">PINDAHKAN PENGHUNI</span>
                    <h2>Pindahkan <?= e($row['full_name']) ?>?</h2>
                    <p>Sewa akan terhubung ke kamar tujuan. Tagihan dan histori pembayaran tetap mengikuti penghuni.</p>
                    <label class="field"><span>Kamar tujuan *</span>
                        <select name="target_room_id" required>
                            <option value="">Pilih kamar tersedia</option>
                            <?php foreach ($availableRooms as $targetRoom): ?>
                                <?php if ((int) $targetRoom['id'] !== (int) $row['active_room_id']): ?>
                                    <option value="<?= (int) $targetRoom['id'] ?>"><?= e($targetRoom['building_name']) ?> · Kamar <?= e($targetRoom['room_number']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="confirmation-check">
                        <input type="checkbox" name="confirm_move" value="yes" required>
                        <span>Saya memahami penghuni akan dipindahkan.</span>
                    </label>
                    <div class="dialog-actions">
                        <button class="button secondary" type="button" data-dialog-close>Batal</button>
                        <button class="button primary" type="submit">Pindahkan penghuni</button>
                    </div>
                </form>
            </dialog>
            <dialog class="action-dialog" id="remove-tenant-<?= (int) $row['id'] ?>">
                <form method="post" class="action-dialog-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="operator_remove_tenant_from_building">
                    <input type="hidden" name="tenant_id" value="<?= (int) $row['id'] ?>">
                    <button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button>
                    <span class="dialog-eyebrow">KONFIRMASI PENGHUNI</span>
                    <h2>Keluarkan penghuni?</h2>
                    <p>
                        Sewa aktif <strong><?= e($row['full_name']) ?></strong> di kos yang Anda kelola
                        akan diakhiri dan kamar yang kosong akan dibuka kembali. Akun pengguna dan statusnya
                        tidak diubah; riwayat sewa dan pembayaran tidak dihapus. Jika tidak ada sewa aktif di kos lain,
                        akun akan tampil pada menu Admin <strong>User pengguna</strong>.
                    </p>
                    <label class="confirmation-check">
                        <input type="checkbox" name="confirm_remove" value="yes" required>
                        <span>Saya memahami penghuni ini akan dikeluarkan.</span>
                    </label>
                    <div class="dialog-actions">
                        <button class="button secondary" type="button" data-dialog-close>Batal</button>
                        <button class="button danger" type="submit">Ya, keluarkan penghuni</button>
                    </div>
                </form>
            </dialog>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>
