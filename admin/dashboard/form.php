<div class="welcome-row">
    <div><h2>Halo, <?= e($user['name']) ?></h2><p>Ringkasan dan aktivitas seluruh platform kos.</p></div>
</div>
<?php require dirname(__DIR__, 2) . '/reports/platform.php'; ?>
