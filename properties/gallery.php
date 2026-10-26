<?php
declare(strict_types=1);

$buildingScope = $user['role'] === 'owner' ? 'WHERE owner_user_id=:owner_id' : '';
$buildings = rows(
    $pdo,
    "SELECT id,name FROM kos_buildings {$buildingScope} ORDER BY name",
    $user['role'] === 'owner' ? ['owner_id' => $user['id']] : []
);
$buildingId = max(0, (int) ($_GET['building_id'] ?? 0));
if (!$buildingId && $buildings) {
    $buildingId = (int) $buildings[0]['id'];
}
$building = $buildingId ? one(
    $pdo,
    'SELECT id,name FROM kos_buildings WHERE id=:id',
    ['id' => $buildingId]
) : null;
if ($buildingId && !$building) {
    set_flash('error', 'Gedung kos tidak ditemukan.');
    redirect('gallery');
}
if ($building && !can_manage_building($pdo, $user, $buildingId)) {
    set_flash('error', 'Anda tidak dapat mengelola galeri gedung tersebut.');
    redirect('gallery');
}
$roomTypes = $building
    ? array_column(
        rows($pdo, 'SELECT DISTINCT room_type FROM rooms WHERE building_id=:id ORDER BY room_type', ['id' => $buildingId]),
        'room_type'
    )
    : [];
$selectedRoomType = isset($_GET['room_type']) && is_string($_GET['room_type'])
    ? $_GET['room_type'] : '';
if ($roomTypes && !in_array($selectedRoomType, $roomTypes, true)) {
    $selectedRoomType = $roomTypes[0];
}
$galleryPhotos = $selectedRoomType
    ? rows(
        $pdo,
        'SELECT g.id,g.category,g.photo_path,g.created_at,g.room_id,rm.room_number
         FROM room_type_gallery_photos g
         LEFT JOIN rooms rm ON rm.id=g.room_id
         WHERE g.building_id=:building_id AND g.room_type=:room_type
         ORDER BY FIELD(g.category,\'building\',\'room\',\'room_facility\',\'general_facility\'),g.id',
        ['building_id' => $buildingId, 'room_type' => $selectedRoomType]
    )
    : [];
$matchingRooms = $selectedRoomType
    ? rows(
        $pdo,
        'SELECT id,room_number,profile_photo_path FROM rooms
         WHERE building_id=:building_id AND room_type=:room_type ORDER BY room_number',
        ['building_id' => $buildingId, 'room_type' => $selectedRoomType]
    )
    : [];
$galleryCategories = [
    'building' => [
        'title' => 'Foto gedung',
        'description' => 'Foto ini akan tampil pada profil dan galeri publik gedung kos.',
        'icon' => '⌂',
    ],
    'room' => [
        'title' => 'Foto kamar',
        'description' => 'Foto kamar ditampilkan bersama untuk tipe yang sama. Terapkan foto sebagai foto profil semua kamar dengan tombol di bawah foto.',
        'icon' => '▧',
    ],
    'room_facility' => [
        'title' => 'Fasilitas kamar',
        'description' => 'Foto fasilitas ini tampil di profil semua kamar bertipe sama. Contoh: kamar mandi dalam, AC, lemari, atau meja belajar.',
        'icon' => '✳',
    ],
    'general_facility' => [
        'title' => 'Fasilitas umum',
        'description' => 'Foto fasilitas umum tampil di profil kamar dan halaman gedung. Contoh: dapur bersama, area parkir, ruang santai, atau halaman.',
        'icon' => '⌂',
    ],
];
$photosByCategory = array_fill_keys(array_keys($galleryCategories), []);
foreach ($galleryPhotos as $galleryPhoto) {
    $photosByCategory[$galleryPhoto['category']][] = $galleryPhoto;
}
?>
<div class="welcome-row gallery-welcome">
    <div><h2>Galeri foto</h2><p>Kelola foto gedung, kamar, dan fasilitas per tipe kamar. Foto kamar tersimpan di galeri bersama dan dapat dipilih sebagai foto profil dari kartu foto.</p></div>
</div>
<div class="panel gallery-filter-panel">
    <form method="get" class="gallery-filter-form">
        <input type="hidden" name="page" value="gallery">
        <label class="field"><span>Gedung kos</span><select name="building_id" onchange="this.form.submit()"><?php foreach ($buildings as $buildingOption): ?><option value="<?= (int) $buildingOption['id'] ?>" <?= (int) $buildingOption['id'] === $buildingId ? 'selected' : '' ?>><?= e($buildingOption['name']) ?></option><?php endforeach; ?></select></label>
        <?php if ($roomTypes): ?>
            <label class="field"><span>Tipe kamar</span><select name="room_type" onchange="this.form.submit()"><?php foreach ($roomTypes as $roomType): ?><option value="<?= e($roomType) ?>" <?= $roomType === $selectedRoomType ? 'selected' : '' ?>><?= e($roomType) ?></option><?php endforeach; ?></select></label>
        <?php endif; ?>
        <noscript><button class="button secondary" type="submit">Tampilkan galeri</button></noscript>
    </form>
    <?php if ($building && $selectedRoomType): ?>
        <form id="gallery-upload-form" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upload_room_type_gallery_photos">
            <input type="hidden" name="building_id" value="<?= $buildingId ?>">
            <input type="hidden" name="room_type" value="<?= e($selectedRoomType) ?>">
        </form>
        <button class="button primary gallery-save-button" type="submit" form="gallery-upload-form">Simpan foto</button>
    <?php endif; ?>
</div>
<?php if (!$buildings): ?>
    <div class="panel"><div class="empty-state"><span>⌂</span><strong>Belum ada gedung kos</strong><p>Tambahkan gedung kos sebelum mengelola galeri.</p><a class="button primary" href="index.php?page=building_form">Tambah gedung</a></div></div>
<?php elseif (!$roomTypes): ?>
    <div class="panel"><div class="empty-state"><span>▧</span><strong>Belum ada tipe kamar di <?= e($building['name']) ?></strong><p>Buat unit kamar untuk menyiapkan galeri berdasarkan tipe kamar.</p><a class="button primary" href="index.php?page=room_form">Tambah kamar</a></div></div>
<?php else: ?>
    <div class="gallery-building-banner">
        <span class="gallery-building-icon">⌂</span>
        <div><small>GALERI GEDUNG</small><strong><?= e($building['name']) ?></strong></div>
        <span class="gallery-room-type-badge"><?= e($selectedRoomType) ?></span>
    </div>
    <?php foreach ($galleryCategories as $category => $categoryInfo): ?>
        <section class="panel gallery-category-panel">
            <div class="gallery-category-layout">
                <div class="gallery-category-heading">
                    <span class="gallery-category-icon <?= e($category) ?>"><?= e($categoryInfo['icon']) ?></span>
                    <div><h2><?= e($categoryInfo['title']) ?></h2><p><?= e($categoryInfo['description']) ?></p></div>
                    <span class="gallery-photo-count"><?= count($photosByCategory[$category]) ?> foto</span>
                </div>
            </div>
            <div class="gallery-photo-grid">
                <?php if ($photosByCategory[$category]): ?>
                    <?php foreach ($photosByCategory[$category] as $photo): ?>
                        <article class="gallery-photo-card">
                            <img src="<?= e($photo['photo_path']) ?>" alt="<?= e($categoryInfo['title'] . ' ' . $selectedRoomType) ?>" loading="lazy">
                            <?php if ($category === 'room' && !empty($photo['room_number'])): ?><small class="gallery-photo-room-label">Khusus Kamar <?= e($photo['room_number']) ?></small><?php endif; ?>
                            <?php if ($category !== 'building' && $matchingRooms): ?>
                                <form method="post" class="gallery-apply-all-form" data-confirm="<?= e($category === 'room'
                                    ? 'Terapkan foto ini sebagai foto profil semua kamar tipe ' . $selectedRoomType . ' di gedung ' . $building['name'] . '?'
                                    : 'Tampilkan foto ini pada semua profil kamar tipe ' . $selectedRoomType . ' di gedung ' . $building['name'] . '?') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="apply_gallery_photo_to_all_rooms">
                                    <input type="hidden" name="building_id" value="<?= $buildingId ?>">
                                    <input type="hidden" name="room_type" value="<?= e($selectedRoomType) ?>">
                                    <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                                    <button class="button primary compact-button" type="submit">Terapkan ke semua Kamar</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($category === 'room' && $matchingRooms && empty($photo['room_id'])): ?>
                                <form method="post" class="gallery-profile-select">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="select_room_gallery_photo">
                                    <input type="hidden" name="building_id" value="<?= $buildingId ?>">
                                    <input type="hidden" name="room_type" value="<?= e($selectedRoomType) ?>">
                                    <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                                    <label class="field"><span>Jadikan foto profil untuk</span><select name="room_id" required><option value="">Pilih kamar</option><?php foreach ($matchingRooms as $matchingRoom): ?><option value="<?= (int) $matchingRoom['id'] ?>">Kamar <?= e($matchingRoom['room_number']) ?><?= ($matchingRoom['profile_photo_path'] ?? null) === $photo['photo_path'] ? ' · Foto profil saat ini' : '' ?></option><?php endforeach; ?></select></label>
                                    <button class="button secondary compact-button" type="submit"><span aria-hidden="true">☆</span> Jadikan foto profil</button>
                                </form>
                            <?php endif; ?>
                            <div class="gallery-photo-delete"><button class="text-button" type="button" data-dialog-open="delete-gallery-photo-<?= (int) $photo['id'] ?>">Hapus foto</button></div>
                            <dialog class="action-dialog" id="delete-gallery-photo-<?= (int) $photo['id'] ?>">
                                <form method="post" class="action-dialog-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_room_type_gallery_photo">
                                    <input type="hidden" name="building_id" value="<?= $buildingId ?>">
                                    <input type="hidden" name="room_type" value="<?= e($selectedRoomType) ?>">
                                    <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                                    <button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button>
                                    <span class="dialog-eyebrow">KEAMANAN GALERI</span><h2>Hapus foto?</h2>
                                    <p>Foto ini akan dihapus dari galeri <?= e($categoryInfo['title']) ?>. Tindakan ini tidak dapat dibatalkan.</p>
                                    <label class="confirmation-check"><input type="checkbox" name="confirm_delete_photo" value="yes" required><span>Saya memahami foto ini akan dihapus dari galeri.</span></label>
                                    <div class="dialog-actions"><button class="button secondary" type="button" data-dialog-close>Batal</button><button class="button danger" type="submit">Hapus foto</button></div>
                                </form>
                            </dialog>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="gallery-empty-state" data-photo-upload-empty><span>▧</span><strong>Galeri <?= e($categoryInfo['title']) ?> masih kosong</strong><small>Foto yang diunggah akan langsung tersedia pada halaman terkait.</small></div>
                <?php endif; ?>
                <div class="photo-upload-control gallery-upload-field" data-photo-upload data-photo-upload-saved="<?= count($photosByCategory[$category]) ?>" data-photo-upload-limit="4">
                    <input class="visually-hidden" form="gallery-upload-form" type="file" name="gallery_photos_<?= e($category) ?>[]" accept="image/jpeg,image/png,image/webp" multiple data-photo-upload-input>
                    <span class="gallery-upload-icon" aria-hidden="true">＋</span>
                    <strong>Tambah foto</strong>
                    <small class="field-help">JPG, PNG, atau WebP · Maks. 4 MB per foto</small>
                    <button class="button secondary" type="button" data-photo-upload-trigger>Upload foto</button>
                    <span class="field-help" data-photo-upload-count><?= count($photosByCategory[$category]) ?> foto tersimpan</span>
                    <small class="field-help" data-photo-upload-status>Pilih hingga 4 foto sekaligus.</small>
                    <div class="photo-upload-preview-grid" data-photo-upload-grid></div>
                </div>
            </div>
        </section>
    <?php endforeach; ?>
    <div class="gallery-save-bottom"><button class="button primary gallery-save-button" type="submit" form="gallery-upload-form">Simpan</button></div>
<?php endif; ?>
