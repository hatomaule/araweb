<?php
$profile = one($pdo, 'SELECT * FROM users WHERE id=:id', ['id' => $user['id']]);
?>
<div class="form-panel"><div class="panel-heading"><div><h2>Profil <?= e(['admin' => 'admin platform', 'operator' => 'operator', 'seeker' => 'User'][$user['role']]) ?></h2><p>Perbarui nama, nomor HP, email, alamat, jenis kelamin, dan foto profil.</p></div></div>
<p class="account-id-label">ID akun: <strong>ID-<?= str_pad((string) $profile['id'], 6, '0', STR_PAD_LEFT) ?></strong></p>
<form method="post" enctype="multipart/form-data" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="save_profile">
    <?php field('Nama lengkap *', 'name', $profile['name'], 'text', ['required' => 'required', 'maxlength' => '100']); field('Email *', 'email', $profile['email'], 'email', ['required' => 'required']); field('Nomor WhatsApp', 'phone', $profile['phone'] ?? '', 'tel', ['maxlength' => '30']); ?>
    <label class="field"><span>Jenis kelamin<?= $user['role'] === 'admin' ? '' : ' *' ?></span><select name="gender" <?= $user['role'] === 'admin' ? '' : 'required' ?>><option value="">Pilih jenis kelamin</option><option value="male" <?= ($profile['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Pria</option><option value="female" <?= ($profile['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Wanita</option></select></label>
    <?= indonesia_region_fields($pdo, [
        'province' => $profile['province_code'] ?? '',
        'regency' => $profile['regency_code'] ?? '',
        'district' => $profile['district_code'] ?? '',
        'village' => $profile['village_code'] ?? '',
    ]) ?>
    <?php field('Nama jalan / alamat detail', 'street_name', $profile['street_name'] ?? $profile['address'] ?? '', 'text', ['maxlength' => '1000']); ?>
    <?php field('RT (opsional)', 'address_rt', $profile['address_rt'] ?? '', 'text', ['maxlength' => '3', 'inputmode' => 'numeric', 'pattern' => '[0-9]{1,3}']); ?>
    <?php field('RW (opsional)', 'address_rw', $profile['address_rw'] ?? '', 'text', ['maxlength' => '3', 'inputmode' => 'numeric', 'pattern' => '[0-9]{1,3}']); ?>
    <?php field('Detail Alamat (opsional)', 'address_block', $profile['address_block'] ?? '', 'text', ['maxlength' => '50']); ?>
    <p class="field-help full-span">Alamat wilayah bersifat opsional. Jika memilih salah satu wilayah, lengkapi seluruh pilihan dari Provinsi hingga Kelurahan/Desa.</p>
    <label class="field"><span>Foto profil (opsional)</span><input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"><small class="field-help">JPG/PNG/WebP maksimal 4 MB. Tanpa foto, avatar mengikuti jenis kelamin.</small></label>
    <?php if ($profile['profile_photo_path']): ?><img class="profile-photo-preview" src="<?= e($profile['profile_photo_path']) ?>" alt="Foto profil"><?php endif; ?>
    <div class="form-actions full-span"><button class="button primary">Simpan profil</button></div>
</form></div>
