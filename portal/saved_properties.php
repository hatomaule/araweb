<?php
declare(strict_types=1);

$savedProperties = rows(
    $pdo,
    "SELECT b.id,b.name,b.address,b.gender_policy,b.verification_tier,b.verification_status,
            b.gold_verification_status,
            CASE WHEN EXISTS (
                SELECT 1 FROM kos_buildings gold_building
                WHERE gold_building.owner_user_id=b.owner_user_id
                  AND gold_building.gold_verification_status='verified'
            ) THEN 'gold' ELSE 'basic' END AS owner_membership_level,
            COALESCE(b.profile_photo_path,
                (SELECT p.photo_path FROM property_photos p WHERE p.building_id=b.id ORDER BY p.display_order,p.id LIMIT 1),
                (SELECT gp.photo_path FROM room_type_gallery_photos gp WHERE gp.building_id=b.id AND gp.category='building' ORDER BY gp.id LIMIT 1)
            ) AS cover_photo,
            (SELECT COUNT(*) FROM rooms rm WHERE rm.building_id=b.id) AS room_count
     FROM property_user_actions a JOIN kos_buildings b ON b.id=a.building_id
     WHERE a.user_id=:user_id AND a.action_type='saved' AND b.status='active'
       AND (b.owner_user_id IS NULL OR b.publication_status='published')
       AND (b.owner_user_id IS NULL OR EXISTS (
           SELECT 1 FROM users owner WHERE owner.id=b.owner_user_id
             AND owner.status='active' AND owner.self_deactivated_at IS NULL
       ))
     ORDER BY a.updated_at DESC,a.id DESC",
    ['user_id' => $user['id']]
);
?>
<div class="welcome-row"><div><h2>Kos tersimpan</h2><p>Properti yang Anda tandai untuk dilihat kembali.</p></div></div>
<?php if ($savedProperties): ?>
    <div class="saved-property-grid">
        <?php foreach ($savedProperties as $property): ?>
            <article class="saved-property-card">
                <a class="saved-property-photo" href="index.php?page=property&amp;id=<?= (int) $property['id'] ?>">
                    <?php if ($property['cover_photo']): ?><img src="<?= e($property['cover_photo']) ?>" alt="Foto <?= e($property['name']) ?>" loading="lazy"><?php else: ?><span aria-hidden="true">⌂</span><?php endif; ?>
                    <span class="saved-property-location-tag"><?= e($property['address']) ?></span>
                </a>
                <div class="saved-property-body"><span class="owner-level-badge owner-level-<?= e($property['owner_membership_level']) ?>"><?= e(ucfirst($property['owner_membership_level'])) ?></span><h3><a href="index.php?page=property&amp;id=<?= (int) $property['id'] ?>"><?= e($property['name']) ?></a></h3><small><?= e(['male' => 'Kos putra', 'female' => 'Kos putri', 'mixed' => 'Kos campur'][$property['gender_policy']] ?? 'Kos') ?> · <?= (int) $property['room_count'] ?> kamar</small><div class="saved-property-actions"><a class="button primary compact-button" href="index.php?page=property&amp;id=<?= (int) $property['id'] ?>">Lihat kos</a><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_saved_property"><input type="hidden" name="building_id" value="<?= (int) $property['id'] ?>"><button class="button secondary compact-button" type="submit">Hapus simpanan</button></form></div></div>
            </article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <section class="panel empty-state"><span>♡</span><strong>Belum ada kos tersimpan</strong><p>Pilih “Simpan kos” pada profil kos untuk menyimpannya di sini.</p><a class="button primary" href="index.php?page=home">Cari kos</a></section>
<?php endif; ?>
