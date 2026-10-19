<?php
declare(strict_types=1);

if ($user['role'] !== 'admin') {
    throw new LogicException('Platform reports are only available to administrators.');
}

$reportMetrics = [
    'transactions' => (int) $pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn(),
    'activity' => (int) $pdo->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn(),
    'users' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='seeker'")->fetchColumn(),
    'operators' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='owner'")->fetchColumn(),
    'properties' => (int) $pdo->query('SELECT COUNT(*) FROM kos_buildings')->fetchColumn(),
    'errors' => (int) $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE outcome='error'")->fetchColumn(),
];
$reportGroups = [
    ['Laporan transaksi', number_format($reportMetrics['transactions'], 0, ',', '.') . ' pembayaran tercatat', 'Transaksi', 'blue', 'platform_dashboard#platform-booking-activity'],
    ['Laporan aktivitas', number_format($reportMetrics['activity'], 0, ',', '.') . ' aktivitas dalam log terintegrasi', 'Aktivitas', 'purple', 'activity_log'],
    ['Jumlah User dan kos', number_format($reportMetrics['users'], 0, ',', '.') . ' User · ' . number_format($reportMetrics['properties'], 0, ',', '.') . ' kos · ' . number_format($reportMetrics['operators'], 0, ',', '.') . ' Owner', 'Pengguna', 'green', 'platform_seeker_accounts'],
    ['Laporan error', number_format($reportMetrics['errors'], 0, ',', '.') . ' aktivitas berstatus gagal/error', 'Error', 'orange', 'activity_log&category=system&outcome=error'],
];
?>
<section class="page-heading"><div><span class="eyebrow">DASHBOARD PLATFORM</span><h2>Dashboard</h2><p>Ringkasan transaksi, aktivitas, pertumbuhan pengguna/properti, dan error.</p></div></section>
<div class="report-groups">
    <?php foreach ($reportGroups as [$title, $summary, $icon, $color, $target]): ?>
        <a class="report-group-card" href="index.php?page=<?= e($target) ?>">
            <span class="stat-icon <?= e($color) ?>"><?= e($icon === 'Transaksi' ? '▣' : ($icon === 'Aktivitas' ? '◷' : ($icon === 'Pengguna' ? '♙' : '!'))) ?></span>
            <span><strong><?= e($title) ?></strong><small><?= e($summary) ?></small></span>
        </a>
    <?php endforeach; ?>
</div>
