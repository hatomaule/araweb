<?php
declare(strict_types=1);

$tenantUserId = max(0, (int) ($_GET['id'] ?? 0));
$profile = one(
    $pdo,
    "SELECT u.id,u.name,u.email,u.phone,u.gender,u.profile_photo_path,u.avatar_key,
            t.id AS tenant_id,t.full_name,t.identity_number,t.address,rm.room_number,
            b.name AS building_name,r.start_date,r.next_due_date,r.monthly_rate
     FROM users u
     JOIN tenants t ON t.user_id=u.id
     JOIN rentals r ON r.tenant_id=t.id
     JOIN rooms rm ON rm.id=r.room_id
     JOIN kos_buildings b ON b.id=rm.building_id
     WHERE u.id=:user_id AND u.role='seeker'
       AND r.status='active' AND r.start_date<=CURDATE()
       AND (r.end_date IS NULL OR r.end_date>=CURDATE())
       AND (:is_admin=1 OR b.owner_user_id=:owner_id)
     ORDER BY r.id DESC LIMIT 1",
    ['user_id' => $tenantUserId, 'is_admin' => $user['role'] === 'admin' ? 1 : 0, 'owner_id' => $user['id']]
);
if (!$profile) {
    http_response_code(404);
    echo '<div class="alert error">Profil penghuni tidak ditemukan atau bukan bagian dari kos yang Anda kelola.</div>';
} else {
?>
<div class="panel tenant-profile-panel">
    <div class="tenant-profile-heading">
        <?= avatar_markup($profile, 'profile-avatar large') ?>
        <div><span class="section-eyebrow">PROFIL PENGHUNI</span><h2><?= e($profile['full_name']) ?></h2><p><?= e($profile['building_name']) ?> · Kamar <?= e($profile['room_number']) ?></p></div>
    </div>
    <dl class="profile-detail-grid">
        <div><dt>Email</dt><dd><?= e($profile['email'] ?: '—') ?></dd></div>
        <div><dt>Nomor telepon</dt><dd><?= e($profile['phone'] ?: '—') ?></dd></div>
        <div><dt>Jenis kelamin</dt><dd><?= e(['male' => 'Laki-laki', 'female' => 'Perempuan'][$profile['gender']] ?? '—') ?></dd></div>
        <div><dt>No. identitas</dt><dd><?= e($profile['identity_number'] ?: '—') ?></dd></div>
        <div><dt>Mulai menghuni</dt><dd><?= e(date_id($profile['start_date'])) ?></dd></div>
        <div><dt>Tagihan berikutnya</dt><dd><?= e($profile['next_due_date'] ? date_id($profile['next_due_date']) : '—') ?></dd></div>
        <div><dt>Tarif bulanan</dt><dd><?= e(money($profile['monthly_rate'])) ?></dd></div>
        <div><dt>Alamat</dt><dd><?= e($profile['address'] ?: '—') ?></dd></div>
    </dl>
    <div class="form-actions"><a class="button secondary" href="index.php?page=tenant_payments&amp;id=<?= (int) $profile['tenant_id'] ?>">Histori pembayaran</a><a class="button secondary" href="index.php?page=rooms">Kembali ke Kamar</a></div>
</div>
<?php } ?>
