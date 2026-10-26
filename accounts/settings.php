<?php
declare(strict_types=1);

$accountSettings = one(
    $pdo,
    'SELECT email,phone,hide_phone_public,hide_profile_photo_public,
            self_deactivated_at,deletion_requested_at,deletion_due_at
     FROM users WHERE id=:id',
    ['id' => $user['id']]
);
$notificationEnabled = notification_get_preference($pdo);
$linkedProviders = rows(
    $pdo,
    'SELECT provider,created_at FROM user_auth_providers WHERE user_id=:id ORDER BY id',
    ['id' => $user['id']]
);
$googleLinked = count(array_filter(
    $linkedProviders,
    static fn(array $provider): bool => $provider['provider'] === 'google'
)) > 0;
$activeAccountRental = (bool) one(
    $pdo,
    "SELECT r.id FROM rentals r JOIN tenants t ON t.id=r.tenant_id
     WHERE t.user_id=:user_id AND r.status='active'
       AND r.start_date<=CURDATE() AND (r.end_date IS NULL OR r.end_date>=CURDATE())
     LIMIT 1",
    ['user_id' => $user['id']]
);
?>
<div class="welcome-row">
    <div><h2>Pengaturan akun</h2><p>Atur privasi, notifikasi, kontak, dan status akun Anda.</p></div>
    <a class="button secondary" href="index.php?page=change_password">Ganti password</a>
</div>
<section class="panel account-settings-panel">
    <div class="panel-heading"><div><h2>Privasi dan notifikasi</h2><p>Perubahan berlaku untuk tampilan publik dan notifikasi akun ini.</p></div></div>
    <form method="post" class="account-settings-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_account_settings">
        <label class="field"><span>Email</span><input type="email" name="email" maxlength="255" value="<?= e($accountSettings['email'] ?? '') ?>" autocomplete="email"></label>
        <label class="field"><span>Nomor HP</span><input type="tel" name="phone" maxlength="30" value="<?= e($accountSettings['phone'] ?? '') ?>" autocomplete="tel"></label>
        <label class="settings-switch"><span><strong>Sembunyikan notifikasi</strong><small>Aktifkan untuk menonaktifkan pemberitahuan browser/perangkat. Notifikasi tetap tersimpan dan badge lonceng tetap bertambah.</small></span><input type="checkbox" name="hide_notifications" value="1" <?= !$notificationEnabled ? 'checked' : '' ?>><i></i></label>
        <label class="settings-switch"><span><strong>Sembunyikan nomor HP</strong><small>Nomor tidak ditampilkan ke publik dan WhatsApp tidak ditautkan.</small></span><input type="checkbox" name="hide_phone_public" value="1" <?= $accountSettings['hide_phone_public'] ? 'checked' : '' ?>><i></i></label>
        <label class="settings-switch"><span><strong>Sembunyikan foto profil</strong><small>Pengunjung publik melihat avatar, bukan foto profil.</small></span><input type="checkbox" name="hide_profile_photo_public" value="1" <?= $accountSettings['hide_profile_photo_public'] ? 'checked' : '' ?>><i></i></label>
        <div class="form-actions"><button class="button primary" type="submit">Simpan pengaturan</button></div>
    </form>
</section>
<section class="panel account-settings-panel">
    <div class="panel-heading"><div><h2>Pemberitahuan browser dan perangkat</h2><p>Izinkan browser mengirim notifikasi KosKosan saat ada aktivitas baru.</p></div></div>
    <div class="push-notification-controls">
        <button class="button secondary" type="button" data-enable-browser-notifications>Aktifkan notifikasi browser</button>
        <small class="subline" data-browser-notification-status aria-live="polite">Status izin browser akan diperiksa.</small>
    </div>
</section>
<section class="panel account-settings-panel">
    <div class="panel-heading"><div><h2>Akun tertaut</h2><p>Metode masuk yang terhubung dengan akun ini.</p></div></div>
    <?php if ($linkedProviders): ?>
        <ul class="linked-provider-list"><?php foreach ($linkedProviders as $provider): ?><li><strong><?= e(ucfirst($provider['provider'])) ?></strong><span>Tertaut sejak <?= e(date_id($provider['created_at'])) ?></span></li><?php endforeach; ?></ul>
    <?php else: ?><p class="muted">Belum ada akun pihak ketiga yang tertaut.</p><?php endif; ?>
    <?php if (!$googleLinked && google_oauth_config()): ?>
        <a class="button secondary" href="index.php?page=google_link">Tautkan akun Google</a>
        <small class="subline">Google hanya ditautkan setelah Anda masuk ke akun KosKosan ini. Role dan email akun tidak akan diganti.</small>
    <?php elseif (!$googleLinked): ?>
        <small class="subline">Penautan Google tersedia setelah administrator mengatur OAuth Google di server.</small>
    <?php endif; ?>
</section>
<?php if ($user['role'] === 'seeker'): ?>
<section class="panel account-settings-panel account-lifecycle-panel">
    <div class="panel-heading"><div><h2>Status akun</h2><p><?= $activeAccountRental ? 'Akun tidak dapat dinonaktifkan selama masa hunian aktif.' : 'Anda dapat menonaktifkan akun sementara atau meminta penghapusan.' ?></p></div></div>
    <?php if ($accountSettings['deletion_due_at']): ?>
        <p class="alert warning">Akun dijadwalkan dihapus pada <?= e(date_id($accountSettings['deletion_due_at'])) ?>. Masuk kembali dan batalkan permintaan sebelum waktu tersebut untuk mempertahankan akun.</p>
    <?php elseif ($accountSettings['self_deactivated_at']): ?>
        <p class="alert warning">Akun Anda sedang dinonaktifkan. Akun tidak tampil pada pencarian dan tidak dapat melakukan booking.</p>
    <?php endif; ?>
    <?php if ($accountSettings['self_deactivated_at'] || $accountSettings['deletion_due_at']): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cancel_account_deactivation"><button class="button secondary" type="submit">Aktifkan kembali akun</button></form>
    <?php else: ?>
        <div class="account-lifecycle-actions">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="request_account_deactivation"><label class="confirmation-check"><input type="checkbox" name="confirm_account_lifecycle" value="yes" required><span>Saya memahami akun tidak tampil di pencarian dan tidak dapat melakukan booking.</span></label><button class="button secondary" type="submit" <?= $activeAccountRental ? 'disabled' : '' ?>>Nonaktifkan akun</button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="request_account_deletion"><label class="confirmation-check"><input type="checkbox" name="confirm_account_lifecycle" value="yes" required><span>Saya memahami akun akan dinonaktifkan sekarang dan identitas dianonimkan setelah 7 hari. Histori transaksi tetap disimpan.</span></label><button class="button danger" type="submit" <?= $activeAccountRental ? 'disabled' : '' ?>>Hapus akun</button></form>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
