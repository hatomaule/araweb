<?php
declare(strict_types=1);

$verificationRequests = rows(
    $pdo,
    "SELECT vr.id,vr.requested_tier,vr.operator_note,vr.created_at,b.id AS building_id,
            b.name AS building_name,b.address,u.name AS operator_name
     FROM property_verification_requests vr
     JOIN kos_buildings b ON b.id=vr.building_id
     JOIN users u ON u.id=vr.operator_user_id
     WHERE vr.status='pending' ORDER BY vr.created_at"
);
$propertyReports = rows(
    $pdo,
    "SELECT pa.id,pa.report_reason,pa.report_details,pa.status,pa.created_at,
            b.id AS building_id,b.name AS building_name,u.name AS reporter_name
     FROM property_user_actions pa
     JOIN kos_buildings b ON b.id=pa.building_id
     JOIN users u ON u.id=pa.user_id
     WHERE pa.action_type='reported' AND pa.status IN ('open','reviewed')
     ORDER BY pa.created_at"
);
$reportReasons = [
    'misleading' => 'Informasi tidak sesuai',
    'fake' => 'Properti tidak ditemukan',
    'inappropriate' => 'Konten tidak pantas',
    'other' => 'Lainnya',
];
?>
<div class="welcome-row"><div><h2>Verifikasi properti</h2><p>Tinjau klaim keaslian dan laporan properti. Klasifikasi adalah verifikasi, bukan peringkat kemewahan.</p></div></div>
<section class="panel verification-admin-panel">
    <div class="panel-heading"><div><h2>Pengajuan verifikasi</h2><p><?= count($verificationRequests) ?> pengajuan menunggu keputusan.</p></div></div>
    <?php if ($verificationRequests): foreach ($verificationRequests as $request): ?>
        <article class="verification-request-card">
            <div><span class="badge pending">Menunggu</span><h3><?= e($request['building_name']) ?> · <?= e(ucfirst($request['requested_tier'])) ?></h3><p><?= e($request['address']) ?></p><small>Owner: <?= e($request['operator_name']) ?> · <?= e(date_id($request['created_at'])) ?></small><?php if ($request['operator_note']): ?><p><?= nl2br(e($request['operator_note'])) ?></p><?php endif; ?><a class="text-link" href="index.php?page=property&amp;id=<?= (int) $request['building_id'] ?>" target="_blank" rel="noopener noreferrer">Lihat profil kos ↗</a></div>
            <form method="post" class="verification-decision-form"><?= csrf_field() ?><input type="hidden" name="action" value="review_property_verification"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><label class="field"><span>Catatan admin</span><textarea name="admin_note" rows="2" maxlength="2000"></textarea></label><div class="form-actions"><button class="button primary" name="decision" value="approved" type="submit">Verifikasi</button><button class="button danger" name="decision" value="rejected" type="submit">Tolak</button></div></form>
        </article>
    <?php endforeach; else: ?><p class="empty-state">Belum ada pengajuan verifikasi yang menunggu.</p><?php endif; ?>
</section>
<section class="panel verification-admin-panel">
    <div class="panel-heading"><div><h2>Laporan properti</h2><p>Periksa informasi yang dilaporkan pengguna.</p></div></div>
    <?php if ($propertyReports): foreach ($propertyReports as $report): ?>
        <article class="verification-request-card"><div><span class="badge <?= e($report['status']) ?>"><?= e(ucfirst($report['status'])) ?></span><h3><?= e($report['building_name']) ?></h3><p><?= e($reportReasons[$report['report_reason']] ?? 'Laporan lain') ?> · <?= e($report['reporter_name']) ?></p><?php if ($report['report_details']): ?><p><?= nl2br(e($report['report_details'])) ?></p><?php endif; ?><a class="text-link" href="index.php?page=property&amp;id=<?= (int) $report['building_id'] ?>" target="_blank" rel="noopener noreferrer">Lihat profil kos ↗</a></div><form method="post" class="verification-decision-form"><?= csrf_field() ?><input type="hidden" name="action" value="review_property_report"><input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>"><label class="field"><span>Status laporan</span><select name="report_status"><option value="reviewed">Sedang ditinjau</option><option value="resolved">Selesai</option><option value="dismissed">Ditolak</option></select></label><button class="button secondary" type="submit">Simpan status</button></form></article>
    <?php endforeach; else: ?><p class="empty-state">Belum ada laporan properti.</p><?php endif; ?>
</section>
