<?php
$listings = rows(
    $pdo,
    "SELECT b.id,b.name,b.address,b.status,b.created_at,u.id AS owner_id,
            u.name AS owner_name,u.phone AS owner_phone
     FROM kos_buildings b JOIN users u ON u.id=b.owner_user_id
     ORDER BY b.created_at DESC,b.id DESC"
);
?>
<section class="page-heading"><div><span class="eyebrow">PROPERTI TERDAFTAR</span><h2>Daftar kos</h2><p>Properti yang dikelola operator. Listing aktif dapat langsung ditemukan pengguna.</p></div></section>
<div class="panel"><div class="table-scroll"><table><thead><tr><th>Nama kos / alamat</th><th>Operator</th><th>Telepon</th><th>Status</th><th>Terdaftar</th></tr></thead><tbody>
<?php foreach ($listings as $listing): ?><tr><td><a class="text-link" href="index.php?page=property&amp;id=<?= (int) $listing['id'] ?>"><?= e($listing['name']) ?> ↗</a><small class="subline"><?= e($listing['address']) ?></small></td><td><a class="text-link" href="index.php?page=platform_operator_profile&amp;id=<?= (int) $listing['owner_id'] ?>"><?= e($listing['owner_name']) ?></a></td><td><?= e($listing['owner_phone'] ?: '—') ?></td><td><span class="badge <?= e($listing['status']) ?>"><?= e(status_label($listing['status'])) ?></span></td><td><?= e(date_id($listing['created_at'])) ?></td></tr><?php endforeach; ?>
<?php if (!$listings): ?><tr><td colspan="5" class="no-data">Belum ada kos terdaftar.</td></tr><?php endif; ?>
</tbody></table></div></div>
