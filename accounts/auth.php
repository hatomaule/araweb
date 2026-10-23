<?php
if (!$user && in_array($page, ['login', 'setup', 'register', 'forgot_password', 'reset_password'], true)) {
    $isSetup = $page === 'setup';
    $isRegister = $page === 'register';
    $isForgot = $page === 'forgot_password';
    $isReset = $page === 'reset_password';
    $registerRole = isset($_GET['role']) && in_array($_GET['role'], ['owner', 'operator'], true) ? 'owner' : 'seeker';
    $registerLabel = $registerRole === 'owner' ? 'Owner kos' : 'User';
    $facebookConfigured = facebook_oauth_config() !== null;
    $nextBookingId = ($isRegister && ($_GET['next'] ?? '') === 'booking')
        ? max(0, (int) ($_GET['id'] ?? 0)) : 0;
    $authTitle = $isForgot ? 'Lupa password' : ($isReset ? 'Atur password baru'
        : ($isSetup ? 'Buat Admin' : ($isRegister ? 'Daftar ' . e($registerLabel) : 'Masuk')));
    ?><!doctype html>
    <html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($authTitle) ?> - KosKosan</title><link rel="stylesheet" href="style.css?v=koskosan-hide-empty-cards-20261023-1"></head>
    <body class="auth-page"><main class="auth-wrap"><section class="auth-card">
        <div class="auth-brand-row"><a class="brand auth-brand" href="index.php"><span class="brand-mark">K</span><span>KosKosan<small>Manajemen kos lebih mudah</small></span></a><?= theme_switcher() ?></div>
        <h1><?= $isForgot ? 'Lupa password?' : ($isReset ? 'Buat password baru' : ($isSetup ? 'Siapkan akun admin' : ($isRegister ? 'Daftar sebagai ' . e($registerLabel) : 'Selamat datang kembali'))) ?></h1>
        <p class="muted"><?= $isForgot ? 'Masukkan email akun User. Kami akan mengirim kode reset yang berlaku 15 menit.' : ($isReset ? 'Masukkan email dan kode enam digit dari email KosKosan.' : ($isSetup ? 'Buat akun admin pertama untuk mulai mengelola kos.' : ($isRegister ? ($registerRole === 'owner' ? 'Daftarkan akun Owner untuk mengelola dan mempromosikan kos Anda.' : 'Buat akun User untuk memesan kamar dan memantau booking Anda.') : 'Masuk untuk mengelola akun atau pesanan kamar.'))) ?></p>
        <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
        <form method="post" class="form-stack" <?= $isRegister ? 'enctype="multipart/form-data"' : '' ?>><?= csrf_field() ?><input type="hidden" name="action" value="<?= $isForgot ? 'request_password_reset' : ($isReset ? 'complete_password_reset' : ($isSetup ? 'setup' : ($isRegister ? 'register_account' : 'login'))) ?>"><?php if ($isRegister): ?><input type="hidden" name="role" value="<?= e($registerRole) ?>"><input type="hidden" name="next" value="<?= $nextBookingId ? 'booking' : '' ?>"><input type="hidden" name="next_room_id" value="<?= $nextBookingId ?>"><?php endif; ?>
            <?php if ($isSetup || $isRegister) { field('Nama lengkap', 'name', '', 'text', ['required' => 'required', 'maxlength' => '100', 'autocomplete' => 'name']); field('Username *', 'username', '', 'text', ['required' => 'required', 'maxlength' => '32', 'pattern' => '[A-Za-z]{1,32}', 'autocomplete' => 'username', 'data-username-check' => '']); ?>
                <small class="field-help username-feedback" data-username-feedback aria-live="polite">Username hanya boleh berisi huruf A-Z dan harus unik.</small>
            <?php } ?>
            <?php if ($isRegister) { field('Email (opsional)', 'email', '', 'email', ['maxlength' => '255', 'autocomplete' => 'email']); field('Nomor telepon (opsional)', 'phone', '', 'tel', ['maxlength' => '30', 'autocomplete' => 'tel']); ?>
                <small class="field-help">Isi minimal salah satu: email atau nomor telepon. Keduanya dapat digunakan untuk login.</small>
                <label class="field"><span>Jenis kelamin *</span><select name="gender" required><option value="">Pilih jenis kelamin</option><option value="male">Pria</option><option value="female">Wanita</option></select></label>
                <label class="field"><span>Foto profil (opsional)</span><input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"><small class="field-help">Jika dikosongkan, avatar pria/wanita akan digunakan. JPG/PNG/WebP maksimal 4 MB.</small></label>
            <?php } ?>
            <?php if ($isForgot): ?>
                <?php field('Email akun User', 'email', '', 'email', ['required' => 'required', 'autocomplete' => 'email']); ?>
                <button class="button primary full" type="submit">Kirim kode reset</button>
            <?php elseif ($isReset): ?>
                <?php field('Email akun User', 'email', '', 'email', ['required' => 'required', 'autocomplete' => 'email']); field('Kode reset 6 digit', 'code', '', 'text', ['required' => 'required', 'inputmode' => 'numeric', 'pattern' => '[0-9]{6}', 'maxlength' => '6', 'autocomplete' => 'one-time-code']); field('Password baru', 'password', '', 'password', ['required' => 'required', 'minlength' => '8', 'maxlength' => '72', 'autocomplete' => 'new-password']); ?>
                <button class="button primary full" type="submit">Simpan password baru</button>
            <?php else: ?>
                <?php if (!$isRegister && !$isSetup) { field('Username, email, atau nomor telepon', 'login_identifier', '', 'text', ['required' => 'required', 'autocomplete' => 'username']); } elseif ($isSetup) { field('Email', 'email', '', 'email', ['required' => 'required', 'autocomplete' => 'email']); } ?>
                <?php if ($page === 'login'): ?>
                    <label class="field" for="login-password"><span>Kata sandi</span>
                        <span class="password-input-wrap">
                            <input id="login-password" type="password" name="password" required autocomplete="current-password">
                            <button class="password-visibility-toggle" type="button" data-password-toggle="login-password"
                                aria-label="Tampilkan kata sandi" aria-pressed="false">
                                <svg class="password-eye-icon password-eye-visible" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="password-eye-icon password-eye-hidden" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                                    <path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15.8 15.8 0 0 1-3.1 3.9M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7c1.1 0 2.2-.2 3.1-.6"></path>
                                </svg>
                            </button>
                        </span>
                    </label>
                <?php else: ?>
                    <?php field('Kata sandi', 'password', '', 'password', ['required' => 'required', 'minlength' => $isSetup || $isRegister ? '8' : '1', 'autocomplete' => $isSetup || $isRegister ? 'new-password' : 'current-password']); ?>
                <?php endif; ?>
                <button class="button primary full" type="submit"><?= $isSetup ? 'Buat akun admin' : ($isRegister ? 'Daftar dan lanjutkan' : 'Masuk') ?></button>
            <?php endif; ?>
        </form>
        <?php if (!$isSetup && !$isForgot && !$isReset && (!$isRegister || $registerRole === 'seeker')): ?>
            <?php if ($facebookConfigured): ?><a class="button secondary full facebook-login-button" href="index.php?page=facebook_login">Lanjutkan dengan Facebook</a>
            <?php else: ?><p class="auth-facebook-note">Login Facebook tersedia setelah pengaturan aplikasi Meta dikonfigurasi di server.</p><?php endif; ?>
        <?php endif; ?>
        <?php if (!$isSetup && !$isForgot && !$isReset): ?><p class="auth-switch"><?= $isRegister ? 'Sudah punya akun?' : 'Belum punya akun User?' ?> <a href="index.php?page=<?= $isRegister ? 'login' : 'register' ?>"><?= $isRegister ? 'Masuk' : 'Daftar sekarang' ?></a></p><?php endif; ?>
        <?php if ($page === 'login'): ?><p class="auth-switch"><a href="index.php?page=forgot_password">Lupa password?</a></p><?php elseif ($isForgot): ?><p class="auth-switch">Sudah menerima kode? <a href="index.php?page=reset_password">Masukkan kode reset</a></p><?php elseif ($isReset): ?><p class="auth-switch">Belum menerima kode? <a href="index.php?page=forgot_password">Kirim ulang kode</a></p><?php endif; ?>
        <a class="auth-home-link" href="index.php">← Kembali ke KosKosan</a>
    </section></main><script src="app.js?v=koskosan-hide-empty-cards-20261023-1"></script></body></html><?php
    exit;
}
