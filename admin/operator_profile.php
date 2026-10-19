<?php
$operatorId = (int) ($_GET['id'] ?? 0);
$profileRole = $page === 'platform_user_profile' ? 'seeker' : 'owner';
$profileRoleLabel = $profileRole === 'owner' ? 'Owner' : 'User';
$operator = one(
    $pdo,
    'SELECT id,name,username,email,phone,address,province_code,regency_code,district_code,village_code,
            street_name,address_rt,address_rw,address_block,gender,profile_photo_path,avatar_key,status,created_at
     FROM users WHERE id=:id AND role=:role',
    ['id' => $operatorId, 'role' => $profileRole]
);
if (!$operator) {
    set_flash('error', 'Profil ' . $profileRoleLabel . ' tidak ditemukan.');
    redirect($profileRole === 'owner' ? 'platform_operator_accounts' : 'platform_seeker_accounts');
}
$buildings = $profileRole === 'owner' ? rows(
    $pdo,
    'SELECT id,name,address,status FROM kos_buildings WHERE owner_user_id=:id ORDER BY name',
    ['id' => $operatorId]
): [];
?>
<div class="page-actions"><div class="profile-page-actions"><a class="button secondary" href="index.php?page=<?= $profileRole === 'owner' ? 'platform_operator_accounts' : 'platform_seeker_accounts' ?>">← Kembali ke akun <?= e($profileRoleLabel) ?></a><?php if ($profileRole === 'seeker'): ?><a class="button primary" href="index.php?page=platform_user_payments&amp;id=<?= (int) $operator['id'] ?>">Profil pembayaran</a><?php endif; ?></div><span class="badge <?= e($operator['status']) ?>"><?= e(status_label($operator['status'])) ?></span></div>
<div class="form-panel">
    <div class="panel-heading"><div><h2>Profil <?= e($operator['name']) ?></h2><p>Informasi akun <?= e($profileRoleLabel) ?><?= $profileRole === 'owner' ? ' dan kos yang dikelola' : '' ?>. Foto profil dikelola pemilik akun.</p></div><?= avatar_markup($operator, 'profile-avatar profile-photo-preview') ?></div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="admin_update_operator_profile"><input type="hidden" name="user_id" value="<?= (int) $operator['id'] ?>"><input type="hidden" name="account_role" value="<?= e($profileRole) ?>">
        <?php field('Nama lengkap *', 'name', $operator['name'], 'text', ['required' => 'required', 'maxlength' => '100']); field('Username *', 'username', $operator['username'], 'text', ['required' => 'required', 'maxlength' => '32', 'pattern' => '[A-Za-z]{1,32}', 'autocomplete' => 'username']); field('Email (opsional)', 'email', $operator['email'] ?? '', 'email', ['maxlength' => '255']); field('Nomor HP', 'phone', $operator['phone'] ?? '', 'tel', ['maxlength' => '30']); ?>
        <label class="field"><span>Jenis kelamin *</span><select name="gender" required><option value="">Pilih jenis kelamin</option><option value="male" <?= ($operator['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Pria</option><option value="female" <?= ($operator['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Wanita</option></select></label>
        <?= indonesia_region_fields($pdo, [
            'province' => $operator['province_code'] ?? '',
            'regency' => $operator['regency_code'] ?? '',
            'district' => $operator['district_code'] ?? '',
            'village' => $operator['village_code'] ?? '',
        ]) ?>
        <?php field('Nama jalan / alamat detail', 'street_name', $operator['street_name'] ?? $operator['address'] ?? '', 'text', ['maxlength' => '1000']); ?>
        <?php field('RT (opsional)', 'address_rt', $operator['address_rt'] ?? '', 'text', ['maxlength' => '3', 'inputmode' => 'numeric', 'pattern' => '[0-9]{1,3}']); ?>
        <?php field('RW (opsional)', 'address_rw', $operator['address_rw'] ?? '', 'text', ['maxlength' => '3', 'inputmode' => 'numeric', 'pattern' => '[0-9]{1,3}']); ?>
        <?php field('Detail Alamat (opsional)', 'address_block', $operator['address_block'] ?? '', 'text', ['maxlength' => '50']); ?>
        <p class="field-help full-span">Jika memilih wilayah, lengkapi seluruh pilihan dari Provinsi hingga Kelurahan/Desa.</p>
        <div class="form-actions full-span"><button class="button primary" type="submit">Simpan profil <?= e($profileRoleLabel) ?></button></div>
    </form>
</div>
<?php if ($profileRole === 'owner'): ?><div class="panel operator-profile-buildings"><div class="panel-heading"><div><h2>Kos yang dikelola</h2><p><?= count($buildings) ?> kos terhubung ke akun ini</p></div></div>
    <?php if ($buildings): ?><div class="table-scroll"><table><thead><tr><th>Nama kos</th><th>Alamat</th><th>Status tampil</th></tr></thead><tbody><?php foreach ($buildings as $building): ?><tr><td><strong><?= e($building['name']) ?></strong></td><td><?= e($building['address']) ?></td><td><?= e(status_label($building['status'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="no-data">Owner belum memiliki kos terdaftar.</p><?php endif; ?>
</div><?php endif; ?>
