<div class="welcome-row">
    <div><h2>Halo, <?= e($user['name']) ?></h2><p>Ringkasan dan aktivitas seluruh platform kos.</p></div>
    <a class="button primary" href="index.php?page=platform_operator_accounts">Kelola akun →</a>
</div>
<?php require dirname(__DIR__, 2) . '/reports/platform.php'; ?>
