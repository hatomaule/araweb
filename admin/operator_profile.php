<?php
$operatorId = (int) ($_GET['id'] ?? 0);
$profileRole = $page === 'platform_user_profile' ? 'seeker' : 'operator';
$profileRoleLabel = $profileRole === 'operator' ? 'operator' : 'User';
$operator = one(
    $pdo,
    'SELECT id,name,email,phone,address,gender,profile_photo_path,avatar_key,status,created_at
     FROM users WHERE id=:id AND role=:role',
    ['id' => $operatorId, 'role' => $profileRole]
);
if (!$operator) {
    set_flash('error', 'Profil ' . $profileRoleLabel . ' tidak ditemukan.');
    redirect($profileRole === 'operator' ? 'platform_operator_accounts' : 'platform_seeker_accounts');
}
$buildings = $profileRole === 'operator' ? rows(
    $pdo,
    'SELECT id,name,address,status FROM kos_buildings WHERE owner_user_id=:id ORDER BY name',
    ['id' => $operatorId]
): [];
?>
<div class="page-actions"><a class="button secondary" href="index.php?page=<?= $profileRole === 'operator' ? 'platform_operator_accounts' : 'platform_seeker_accounts' ?>">← Kembali ke akun <?= e($profileRoleLabel) ?></a><span class="badge <?= e($operator['status']) ?>"><?= e(status_label($operator['status'])) ?></span></div>
<div class="form-panel">
    <div class="panel-heading"><div><h2>Profil <?= e($operator['name']) ?></h2><p>Informasi akun <?= e($profileRoleLabel) ?><?= $profileRole === 'operator' ? ' dan kos yang dikelola' : '' ?>. Foto profil dikelola pemilik akun.</p></div><?= avatar_markup($operator, 'profile-avatar profile-photo-preview') ?></div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="admin_update_operator_profile"><input type="hidden" name="user_id" value="<?= (int) $operator['id'] ?>"><input type="hidden" name="account_role" value="<?= e($profileRole) ?>">
        <?php field('Nama lengkap *', 'name', $operator['name'], 'text', ['required' => 'required', 'maxlength' => '100']); field('Email (opsional)', 'email', $operator['email'] ?? '', 'email', ['maxlength' => '255']); field('Nomor HP', 'phone', $operator['phone'] ?? '', 'tel', ['maxlength' => '30']); ?>
        <label class="field"><span>Jenis kelamin *</span><select name="gender" required><option value="">Pilih jenis kelamin</option><option value="male" <?= ($operator['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Pria</option><option value="female" <?= ($operator['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Wanita</option></select></label>
        <label class="field full-span"><span>Alamat</span><textarea name="address" rows="3" maxlength="1000"><?= e($operator['address'] ?? '') ?></textarea></label>
        <div class="form-actions full-span"><button class="button primary" type="submit">Simpan profil <?= e($profileRoleLabel) ?></button></div>
    </form>
</div>
<?php if ($profileRole === 'operator'): ?><div class="panel operator-profile-buildings"><div class="panel-heading"><div><h2>Kos yang dikelola</h2><p><?= count($buildings) ?> kos terhubung ke akun ini</p></div></div>
    <?php if ($buildings): ?><div class="table-scroll"><table><thead><tr><th>Nama kos</th><th>Alamat</th><th>Status tampil</th></tr></thead><tbody><?php foreach ($buildings as $building): ?><tr><td><strong><?= e($building['name']) ?></strong></td><td><?= e($building['address']) ?></td><td><?= e(status_label($building['status'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="no-data">Operator belum memiliki kos terdaftar.</p><?php endif; ?>
</div><?php endif; ?>
