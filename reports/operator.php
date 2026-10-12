<?php
declare(strict_types=1);

$ownerId = (int) $user['id'];
$propertyActivity = rows(
    $pdo,
    "SELECT b.id,b.name,
       COUNT(CASE WHEN ae.event_type='search_result' THEN 1 END) AS searches,
       COUNT(CASE WHEN ae.event_type='property_view' THEN 1 END) AS property_views,
       COUNT(CASE WHEN ae.event_type='room_view' THEN 1 END) AS room_views,
       COUNT(DISTINCT CASE WHEN ae.event_type IN ('property_view','room_view') THEN ae.visitor_key END) AS unique_visitors
     FROM kos_buildings b LEFT JOIN property_activity_events ae ON ae.building_id=b.id
     WHERE b.owner_user_id=:id GROUP BY b.id,b.name ORDER BY b.name",
    ['id' => $ownerId]
);
$visitorEvents = rows(
    $pdo,
    "SELECT ae.event_type,ae.search_query,ae.occurred_at,b.name AS building_name,
            CASE WHEN ae.user_id IS NULL THEN 'Pengunjung anonim' ELSE 'Pengunjung terdaftar' END AS visitor_label
     FROM property_activity_events ae
     JOIN kos_buildings b ON b.id=ae.building_id
     WHERE b.owner_user_id=:id AND ae.event_type IN ('search_result','property_view','room_view')
     ORDER BY ae.occurred_at DESC,ae.id DESC LIMIT 50",
    ['id' => $ownerId]
);
$reviewReplyColumn = $pdo->query(
    "SHOW COLUMNS FROM property_reviews LIKE 'operator_reply'"
)->fetch();
$reviewReplyFields = $reviewReplyColumn
    ? 'pr.operator_reply,pr.operator_replied_at'
    : 'NULL AS operator_reply,NULL AS operator_replied_at';
$reviews = rows(
    $pdo,
    "SELECT pr.id,pr.building_id,pr.rating,pr.review_text,pr.status,pr.created_at,
            {$reviewReplyFields},b.name AS building_name,u.name AS reviewer_name
     FROM property_reviews pr
     JOIN kos_buildings b ON b.id=pr.building_id
     JOIN tenants t ON t.id=pr.tenant_id
     LEFT JOIN users u ON u.id=t.user_id
     WHERE b.owner_user_id=:id
     ORDER BY pr.created_at DESC,pr.id DESC LIMIT 100",
    ['id' => $ownerId]
);
?>
<section class="page-heading">
    <div><span class="eyebrow">LAPORAN PROPERTI</span><h2>Pengunjung dan ulasan</h2><p>Pantau pencarian, kunjungan, serta tanggapi ulasan pada properti yang Anda kelola.</p></div>
</section>
<section class="panel">
    <div class="panel-heading"><div><h2>Pencarian dan pengunjung per kos</h2><p>Jumlah pengunjung unik dihitung dari aktivitas melihat kos atau kamar.</p></div></div>
    <div class="table-scroll">
        <table><thead><tr><th>Nama kos</th><th>Hasil pencarian</th><th>Dilihat</th><th>Kamar dilihat</th><th>Pengunjung unik</th></tr></thead>
            <tbody>
                <?php foreach ($propertyActivity as $activity): ?>
                    <tr><td><strong><?= e($activity['name']) ?></strong></td><td><?= (int) $activity['searches'] ?></td><td><?= (int) $activity['property_views'] ?></td><td><?= (int) $activity['room_views'] ?></td><td><?= (int) $activity['unique_visitors'] ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$propertyActivity): ?><tr><td colspan="5" class="no-data">Belum ada kos terdaftar.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="panel">
    <div class="panel-heading"><div><h2>Aktivitas pengunjung terbaru</h2><p>Maksimal 50 aktivitas; alamat IP tidak disimpan.</p></div></div>
    <div class="table-scroll">
        <table><thead><tr><th>Waktu</th><th>Kos</th><th>Aktivitas</th><th>Pengunjung</th><th>Pencarian</th></tr></thead>
            <tbody>
                <?php foreach ($visitorEvents as $event): ?>
                    <tr><td><?= e(date_id($event['occurred_at'])) ?></td><td><?= e($event['building_name']) ?></td><td><?= e(['search_result' => 'Hasil pencarian', 'property_view' => 'Melihat kos', 'room_view' => 'Melihat kamar'][$event['event_type']] ?? $event['event_type']) ?></td><td><?= e($event['visitor_label']) ?></td><td><?= e($event['search_query'] ?: '—') ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$visitorEvents): ?><tr><td colspan="5" class="no-data">Belum ada aktivitas pengunjung pada kos Anda.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="panel">
    <div class="panel-heading"><div><h2>Penilaian dan komentar pengunjung</h2><p>Balasan yang diterbitkan akan tampil bersama ulasan pada halaman publik kos.</p></div></div>
    <div class="review-management-list">
        <?php foreach ($reviews as $review): ?>
            <article class="review-management-card">
                <div class="review-management-heading">
                    <div><strong><?= e($review['reviewer_name'] ?: 'Penghuni') ?> · <?= str_repeat('★', (int) $review['rating']) ?></strong><small><?= e($review['building_name']) ?> · <?= e(date_id($review['created_at'])) ?></small></div>
                    <span class="badge <?= e($review['status']) ?>"><?= e($review['status'] === 'published' ? 'Tampil' : 'Disembunyikan') ?></span>
                </div>
                <p><?= nl2br(e($review['review_text'])) ?></p>
                <?php if ($reviewReplyColumn): ?>
                    <form method="post" class="review-reply-form">
                        <?= csrf_field() ?><input type="hidden" name="action" value="operator_review_reply"><input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                        <label class="field"><span>Balasan pengelola</span><textarea name="operator_reply" rows="3" maxlength="3000" required><?= e($review['operator_reply'] ?? '') ?></textarea></label>
                        <button class="button primary" type="submit"><?= $review['operator_reply'] ? 'Perbarui balasan' : 'Kirim balasan' ?></button>
                        <a class="text-link" href="index.php?page=property&amp;id=<?= (int) $review['building_id'] ?>#review">Lihat ulasan di halaman kos</a>
                    </form>
                <?php elseif ($review['status'] === 'published'): ?>
                    <p class="muted">Balasan pengelola belum tersedia pada database ini.</p>
                <?php endif; ?>
                <details class="review-moderation">
                    <summary>Moderasi ulasan</summary>
                    <form method="post" class="review-moderation-form">
                        <?= csrf_field() ?><input type="hidden" name="action" value="operator_review_visibility"><input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                        <select name="review_status" aria-label="Status publikasi ulasan">
                            <option value="hidden" <?= $review['status'] === 'published' ? 'selected' : '' ?>>Sembunyikan</option>
                            <option value="published" <?= $review['status'] === 'hidden' ? 'selected' : '' ?>>Tampilkan</option>
                        </select>
                        <label class="confirmation-check"><input type="checkbox" name="confirm_review_action" value="yes" required><span>Saya memahami status ulasan akan diubah.</span></label>
                        <button class="button secondary" type="submit">Simpan moderasi</button>
                    </form>
                </details>
            </article>
        <?php endforeach; ?>
        <?php if (!$reviews): ?><p class="no-data">Belum ada ulasan untuk properti Anda.</p><?php endif; ?>
    </div>
</section>
