<?php
$buildings = rows($pdo, "SELECT b.*,
    (SELECT COUNT(*) FROM rooms rm WHERE rm.building_id=b.id) AS room_count,
    COALESCE(b.profile_photo_path,
        (SELECT pp.photo_path FROM property_photos pp
         WHERE pp.building_id=b.id ORDER BY pp.display_order,pp.id LIMIT 1)
    ) AS cover_photo
    FROM kos_buildings b
    WHERE (:is_admin=1 OR b.owner_user_id=:owner_id)
    ORDER BY b.status='active' DESC, b.name", [
        'is_admin' => $user['role'] === 'admin' ? 1 : 0,
        'owner_id' => $user['id'],
    ]);
?>
<div class="page-actions building-list-heading">
    <div><h2><?= $user['role'] === 'admin' ? 'Gedung kos' : 'Gedung kos saya' ?></h2><p>Kelola informasi, galeri foto, lokasi, kamar, dan status setiap gedung.</p></div>
    <a class="button primary" href="index.php?page=building_form">＋ Tambah gedung</a>
</div>
<section class="building-list" data-building-card-list data-building-view-storage-key="buildingView-<?= e($user['role']) ?>-<?= (int) $user['id'] ?>">
    <div class="building-list-tools">
        <label class="building-list-search"><span>Cari gedung</span><input type="search" data-building-card-search placeholder="Nama atau alamat gedung..." aria-label="Cari gedung berdasarkan nama atau alamat"></label>
        <label class="building-list-page-size"><span>Tampilkan</span><select data-building-card-page-size aria-label="Jumlah gedung yang ditampilkan"><option value="5" selected>5</option><option value="10">10</option><option value="all">Semua</option></select></label>
        <div class="building-list-navigation">
            <span data-building-card-summary aria-live="polite"></span>
            <button class="button secondary compact-button" type="button" data-building-card-previous>Sebelumnya</button>
            <button class="button secondary compact-button" type="button" data-building-card-next>Berikutnya</button>
        </div>
        <details class="data-table-view-switch data-view-picker building-view-switch" data-building-view-switch aria-label="Pilih tampilan gedung"></details>
    </div>
    <div class="building-card-grid" data-building-card-grid>
        <?php foreach ($buildings as $building):
            $buildingInitial = iconv_substr((string) $building['name'], 0, 1, 'UTF-8');
            if ($buildingInitial === false) {
                $buildingInitial = substr((string) $building['name'], 0, 1);
            }
            $isActive = $building['status'] === 'active';
        ?>
            <article class="building-card" data-building-card data-building-id="<?= (int) $building['id'] ?>">
                <a class="building-card-cover" href="index.php?page=property&amp;id=<?= (int) $building['id'] ?>" aria-label="Lihat halaman publik <?= e($building['name']) ?>">
                    <?php if (!empty($building['cover_photo'])): ?>
                        <img src="<?= e($building['cover_photo']) ?>" alt="Foto <?= e($building['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="building-card-placeholder" aria-hidden="true">⌂</span>
                    <?php endif; ?>
                    <span class="building-card-location-tag"><?= e($building['gender_policy'] === 'male' ? 'Kos Putra' : ($building['gender_policy'] === 'female' ? 'Kos Putri' : 'Kos Campur')) ?></span>
                    <span class="badge <?= e($building['status']) ?>"><?= e(status_label($building['status'])) ?></span>
                </a>
                <div class="building-card-body">
                    <div class="building-card-heading">
                        <span class="building-card-initial" aria-hidden="true"><?= e($buildingInitial) ?></span>
                        <div>
                            <h3><a href="index.php?page=property&amp;id=<?= (int) $building['id'] ?>"><?= e($building['name']) ?></a></h3>
                        </div>
                    </div>
                    <p class="building-card-address"><?= e($building['address']) ?></p>
                    <div class="building-card-facts">
                        <span><strong><?= (int) $building['room_count'] ?></strong> kamar</span>
                        <?php if ($building['latitude'] !== null && $building['longitude'] !== null): ?>
                            <a class="building-card-location" href="https://www.openstreetmap.org/?mlat=<?= e($building['latitude']) ?>&amp;mlon=<?= e($building['longitude']) ?>#map=17/<?= e($building['latitude']) ?>/<?= e($building['longitude']) ?>" target="_blank" rel="noopener noreferrer">Lihat lokasi peta ↗</a>
                        <?php else: ?>
                            <span class="building-card-no-location">Lokasi belum diatur</span>
                        <?php endif; ?>
                    </div>
                    <div class="building-card-actions">
                        <a class="button secondary building-card-edit" href="index.php?page=building_form&amp;id=<?= (int) $building['id'] ?>">Edit gedung</a>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="building_status"><input type="hidden" name="id" value="<?= (int) $building['id'] ?>"><input type="hidden" name="status" value="<?= $isActive ? 'inactive' : 'active' ?>"><button class="button <?= $isActive ? 'danger' : 'primary' ?> building-card-toggle" type="submit"><?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="table-scroll building-table-view" data-building-table-view hidden>
        <table data-custom-table-controls>
            <thead><tr><th>Gedung</th><th>Alamat</th><th>Kamar</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($buildings as $building): ?>
                    <?php $isActive = $building['status'] === 'active'; ?>
                    <tr data-building-table-row data-building-id="<?= (int) $building['id'] ?>">
                        <td><a class="building-name-link" href="index.php?page=property&amp;id=<?= (int) $building['id'] ?>"><strong><?= e($building['name']) ?></strong></a></td>
                        <td><?= e($building['address']) ?></td>
                        <td><?= (int) $building['room_count'] ?></td>
                        <td><span class="badge <?= e($building['status']) ?>"><?= e(status_label($building['status'])) ?></span></td>
                        <td class="actions">
                            <a class="button secondary compact-button" href="index.php?page=building_form&amp;id=<?= (int) $building['id'] ?>">Edit</a>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="building_status"><input type="hidden" name="id" value="<?= (int) $building['id'] ?>"><input type="hidden" name="status" value="<?= $isActive ? 'inactive' : 'active' ?>"><button class="button <?= $isActive ? 'danger' : 'primary' ?> compact-button" type="submit"><?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$buildings): ?><tr><td colspan="5" class="no-data">Belum ada gedung kos.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="building-list-empty" data-building-card-empty <?= $buildings ? 'hidden' : '' ?>><?= $buildings ? 'Tidak ada gedung yang cocok dengan pencarian.' : 'Belum ada gedung kos. Tambahkan gedung sebelum mencatat kamar.' ?></p>
</section>
