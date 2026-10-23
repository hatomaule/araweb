<?php
$listings = rows(
    $pdo,
    "SELECT b.id,b.name,b.address,
            b.profile_photo_path,
            (SELECT pp.photo_path FROM property_photos pp
             WHERE pp.building_id=b.id ORDER BY pp.display_order,pp.id LIMIT 1) AS first_property_photo,
            (SELECT gp.photo_path FROM room_type_gallery_photos gp
             WHERE gp.building_id=b.id AND gp.category='building' ORDER BY gp.id LIMIT 1) AS first_gallery_photo,
            b.status,b.created_at,u.id AS owner_id,
            u.name AS owner_name,u.phone AS owner_phone
     FROM kos_buildings b JOIN users u ON u.id=b.owner_user_id
     ORDER BY b.created_at DESC,b.id DESC"
);
?>
<section class="page-heading"><div><span class="eyebrow">PROPERTI TERDAFTAR</span><h2>Daftar kos</h2><p>Properti yang dikelola Owner. Listing aktif dapat langsung ditemukan pengguna.</p></div></section>
<div class="panel"><div class="table-scroll"><table><thead><tr><th data-card-label="">Nama kos / alamat</th><th>Owner</th><th>Telepon</th><th>Status</th><th>Terdaftar</th></tr></thead><tbody>
<?php foreach ($listings as $listing):
    $propertyUrl = 'index.php?page=property&amp;id=' . (int) $listing['id'];
    $propertyPhoto = $listing['profile_photo_path']
        ?: ($listing['first_property_photo'] ?: $listing['first_gallery_photo']);
?>
<tr>
    <td class="admin-property-data"><div class="admin-property-cell">
        <a class="admin-property-avatar" href="<?= $propertyUrl ?>" aria-label="Buka profil kos <?= e($listing['name']) ?>">
            <?php if ($propertyPhoto): ?><img src="<?= e($propertyPhoto) ?>" alt=""><?php else: ?><span aria-hidden="true">K</span><?php endif; ?>
        </a>
        <div class="admin-property-copy"><a class="text-link" href="<?= $propertyUrl ?>"><?= e($listing['name']) ?> ↗</a><small class="subline"><?= e($listing['address']) ?></small></div>
    </div></td>
    <td><a class="text-link" href="index.php?page=platform_operator_profile&amp;id=<?= (int) $listing['owner_id'] ?>"><?= e($listing['owner_name']) ?></a></td><td><?= e($listing['owner_phone'] ?: '—') ?></td><td><span class="badge <?= e($listing['status']) ?>"><?= e(status_label($listing['status'])) ?></span></td><td><?= e(date_id($listing['created_at'])) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$listings): ?><tr><td colspan="5" class="no-data">Belum ada kos terdaftar.</td></tr><?php endif; ?>
</tbody></table></div></div>
