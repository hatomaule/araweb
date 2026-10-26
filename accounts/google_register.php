<?php
declare(strict_types=1);

$googleAccount = $_SESSION['google_new_account'] ?? null;
if (!is_array($googleAccount) || !isset(
    $googleAccount['subject'],
    $googleAccount['email'],
    $googleAccount['name'],
    $googleAccount['created_at']
) || time() - (int) $googleAccount['created_at'] > 900) {
    unset($_SESSION['google_new_account']);
    set_flash('error', 'Sesi pendaftaran Google kedaluwarsa. Silakan mulai lagi.');
    redirect('login');
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pilih jenis akun - KosKosan</title>
    <link rel="stylesheet" href="style.css?v=koskosan-chat-media-layout-20261023-10">
</head>
<body class="auth-page">
<main class="auth-wrap">
    <section class="auth-card">
        <div class="auth-brand-row">
            <a class="brand auth-brand" href="index.php"><span class="brand-mark">K</span><span>KosKosan<small>Manajemen kos lebih mudah</small></span></a>
            <?= theme_switcher() ?>
        </div>
        <h1>Pilih jenis akun</h1>
        <p class="muted">Google memverifikasi email ini. Pilih peran untuk akun baru Anda; peran akun yang sudah ada tidak pernah diubah lewat login Google.</p>
        <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
        <div class="panel">
            <strong><?= e($googleAccount['name']) ?></strong>
            <p class="muted"><?= e($googleAccount['email']) ?></p>
        </div>
        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="google_create_account">
            <label class="field">
                <span>Masuk sebagai</span>
                <select name="role" required>
                    <option value="">Pilih jenis akun</option>
                    <option value="seeker">User — mencari dan memesan kos</option>
                    <option value="owner">Owner — mengelola properti kos</option>
                </select>
            </label>
            <button class="button primary full" type="submit">Buat akun</button>
        </form>
        <p class="auth-switch">Email sudah terdaftar? <a href="index.php?page=login">Masuk dengan metode akun lama</a>, lalu tautkan Google dari Pengaturan akun.</p>
        <a class="auth-home-link" href="index.php">← Kembali ke KosKosan</a>
    </section>
</main>
<script src="app.js?v=koskosan-chat-media-layout-20261023-10"></script>
</body>
</html>
