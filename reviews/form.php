<?php
declare(strict_types=1);

$role = $user['role'];
$replyColumn = $pdo->query("SHOW COLUMNS FROM property_reviews LIKE 'operator_reply'")->fetch();
$replyFields = $replyColumn
    ? 'pr.operator_reply,pr.operator_replied_at'
    : 'NULL AS operator_reply,NULL AS operator_replied_at';
$reviewCondition = match ($role) {
    'owner' => 'b.owner_user_id=:user_id',
    'seeker' => '(pr.reviewer_user_id=:user_id OR t.user_id=:user_id)',
    default => '1=1',
};
$reviews = rows(
    $pdo,
    "SELECT DISTINCT pr.id,pr.building_id,pr.rating,pr.review_text,pr.status,pr.created_at,
            {$replyFields},b.name AS building_name,u.name AS reviewer_name
     FROM property_reviews pr
     JOIN kos_buildings b ON b.id=pr.building_id
     LEFT JOIN tenants t ON t.id=pr.tenant_id
     LEFT JOIN users u ON u.id=COALESCE(pr.reviewer_user_id,t.user_id)
     WHERE {$reviewCondition}
     ORDER BY pr.created_at DESC,pr.id DESC LIMIT 200",
    $role === 'admin' ? [] : ['user_id' => (int) $user['id']]
);
?>
<section class="page-heading">
    <div><span class="eyebrow">ULASAN</span><h2><?= $role === 'owner' ? 'Ulasan properti Anda' : ($role === 'seeker' ? 'Ulasan saya' : 'Ulasan platform') ?></h2>
        <p><?= $role === 'owner' ? 'Tanggapi dan moderasi ulasan pada properti yang Anda kelola.' : 'Lihat ulasan dan balasan terkait KosKosan.' ?></p>
    </div>
</section>
<section class="panel">
    <div class="panel-heading"><div><h2>Daftar ulasan</h2><p>Ulasan ditampilkan sesuai akses akun Anda.</p></div></div>
    <div class="review-list">
        <?php foreach ($reviews as $review): ?>
            <article class="review-management-card">
                <div class="review-management-heading">
                    <div><strong><?= e($review['reviewer_name'] ?: 'Penghuni') ?> · <?= str_repeat('★', (int) $review['rating']) ?></strong>
                        <small><?= e($review['building_name']) ?> · <?= e(date_id($review['created_at'])) ?></small></div>
                    <span class="badge <?= e($review['status']) ?>"><?= e($review['status'] === 'published' ? 'Tampil' : 'Disembunyikan') ?></span>
                </div>
                <p><?= nl2br(e($review['review_text'])) ?></p>
                <?php if (!empty($review['operator_reply'])): ?><div class="property-review-reply"><strong>Balasan pengelola</strong><p><?= nl2br(e($review['operator_reply'])) ?></p></div><?php endif; ?>
                <?php if ($role === 'owner'): ?>
                    <?php if ($replyColumn): ?>
                        <form method="post" class="review-reply-form">
                            <?= csrf_field() ?><input type="hidden" name="action" value="operator_review_reply"><input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                            <label class="field"><span>Balasan pengelola</span><textarea name="operator_reply" rows="3" maxlength="3000" required><?= e($review['operator_reply'] ?? '') ?></textarea></label>
                            <button class="button primary" type="submit"><?= $review['operator_reply'] ? 'Perbarui balasan' : 'Kirim balasan' ?></button>
                        </form>
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
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if (!$reviews): ?><p class="review-list-empty">Belum ada ulasan untuk ditampilkan.</p><?php endif; ?>
</section>
