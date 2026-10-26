<?php
$profile = one($pdo, 'SELECT * FROM users WHERE id=:id', ['id' => $user['id']]);
$ownerProfileProgress = $user['role'] === 'owner'
    ? owner_profile_completion($pdo, (int) $user['id']) : null;
?>
<div class="form-panel"><div class="panel-heading"><div><h2>Profil <?= e(['admin' => 'admin platform', 'owner' => 'Owner', 'seeker' => 'User'][$user['role']]) ?></h2><p>Perbarui username, informasi akun, alamat, jenis kelamin, dan foto profil.</p></div></div>
<?php if ($ownerProfileProgress): ?>
    <section class="owner-profile-progress-panel">
        <div><strong>Kelengkapan profil Owner: <?= $ownerProfileProgress['percent'] ?>%</strong><small><?= $ownerProfileProgress['completed'] ?> dari <?= $ownerProfileProgress['total'] ?> data profil terisi. Perubahan dapat disimpan kapan saja dan dilanjutkan kembali.</small></div>
        <progress max="100" value="<?= $ownerProfileProgress['percent'] ?>"><?= $ownerProfileProgress['percent'] ?>%</progress>
        <ul class="owner-profile-checklist">
            <?php foreach ($ownerProfileProgress['items'] as $label => $complete): ?>
                <li class="<?= $complete ? 'complete' : 'incomplete' ?>"><?= $complete ? '✓' : '○' ?> <?= e($label) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="form-grid profile-form"><?= csrf_field() ?><input type="hidden" name="action" value="save_profile">
    <div class="profile-photo-editor full-span">
        <?= avatar_markup($profile, 'profile-photo-preview') ?>
        <div class="profile-photo-controls">
            <strong>Foto profil</strong>
            <small>Opsional. JPG, PNG, atau WebP maksimal 4 MB. Tanpa foto, avatar mengikuti jenis kelamin.</small>
            <button class="button secondary compact-button" type="button" data-profile-photo-trigger>Pilih atau ganti foto</button>
            <span class="profile-photo-filename" data-profile-photo-filename aria-live="polite"><?= $profile['profile_photo_path'] ? 'Foto profil saat ini' : 'Belum ada foto profil' ?></span>
            <?php if ($profile['profile_photo_path']): ?><label class="profile-photo-remove"><input type="checkbox" name="remove_profile_photo" value="1" data-profile-photo-remove> Gunakan avatar default</label><?php endif; ?>
        </div>
        <input class="profile-photo-input" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" data-profile-photo-input tabindex="-1" aria-label="Pilih foto profil">
    </div>
    <p class="account-id-label full-span">ID akun: <strong>ID-<?= str_pad((string) $profile['id'], 6, '0', STR_PAD_LEFT) ?></strong> <span>ID dibuat sistem dan tidak dapat diubah.</span></p>
    <?php field('Nama lengkap *', 'name', $profile['name'], 'text', ['required' => 'required', 'maxlength' => '100']); field('Username *', 'username', $profile['username'], 'text', ['required' => 'required', 'maxlength' => '32', 'pattern' => '[A-Za-z]{1,32}', 'autocomplete' => 'username', 'data-username-check' => '']); field('Email *', 'email', $profile['email'], 'email', ['required' => 'required']); field('Nomor WhatsApp', 'phone', $profile['phone'] ?? '', 'tel', ['maxlength' => '30']); ?>
    <small class="field-help full-span username-feedback" data-username-feedback aria-live="polite">Username hanya boleh menggunakan huruf A-Z dan tidak boleh sama dengan username akun lain.</small>
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
    <div class="form-actions full-span"><button class="button primary">Simpan profil</button></div>
</form></div>
