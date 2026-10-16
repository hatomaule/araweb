<?php
declare(strict_types=1);

$buildingScope = $user['role'] === 'operator' ? 'WHERE owner_user_id=:owner_id' : '';
$buildings = rows(
    $pdo,
    "SELECT id,name FROM kos_buildings {$buildingScope} ORDER BY name",
    $user['role'] === 'operator' ? ['owner_id' => $user['id']] : []
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
    ? rows($pdo, 'SELECT DISTINCT room_type FROM rooms WHERE building_id=:id ORDER BY room_type', ['id' => $buildingId])
    : [];
$selectedRoomType = isset($_GET['room_type']) && is_string($_GET['room_type'])
    ? $_GET['room_type'] : '';
if ($roomTypes && !in_array($selectedRoomType, array_column($roomTypes, 'room_type'), true)) {
    $selectedRoomType = $roomTypes[0]['room_type'];
}
$galleryPhotos = $selectedRoomType
    ? rows(
        $pdo,
        'SELECT id,category,photo_path,created_at FROM room_type_gallery_photos
         WHERE building_id=:building_id AND room_type=:room_type
         ORDER BY FIELD(category,\'building\',\'room\',\'room_facility\',\'general_facility\'),id',
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
        'description' => 'Foto dipakai bersama oleh kamar bertipe sama dan dapat dipilih sebagai foto profil kamar.',
        'icon' => '▧',
    ],
    'room_facility' => [
        'title' => 'Fasilitas kamar',
        'description' => 'Contoh: kamar mandi dalam, AC, lemari, atau meja belajar.',
        'icon' => '✳',
    ],
    'general_facility' => [
        'title' => 'Fasilitas umum',
        'description' => 'Contoh: dapur bersama, area parkir, ruang santai, atau halaman.',
        'icon' => '⌂',
    ],
];
$photosByCategory = array_fill_keys(array_keys($galleryCategories), []);
foreach ($galleryPhotos as $galleryPhoto) {
    $photosByCategory[$galleryPhoto['category']][] = $galleryPhoto;
}
?>
<div class="welcome-row gallery-welcome">
    <div><h2>Galeri foto</h2><p>Kelola foto gedung, kamar, dan fasilitas per tipe kamar. Foto kamar dan fasilitas akan digunakan bersama oleh kamar bertipe sama.</p></div>
</div>
<div class="panel gallery-filter-panel">
    <form method="get" class="gallery-filter-form">
        <input type="hidden" name="page" value="gallery">
        <label class="field"><span>Gedung kos</span><select name="building_id" onchange="this.form.submit()"><?php foreach ($buildings as $buildingOption): ?><option value="<?= (int) $buildingOption['id'] ?>" <?= (int) $buildingOption['id'] === $buildingId ? 'selected' : '' ?>><?= e($buildingOption['name']) ?></option><?php endforeach; ?></select></label>
        <?php if ($roomTypes): ?>
            <label class="field"><span>Tipe kamar</span><select name="room_type" onchange="this.form.submit()"><?php foreach ($roomTypes as $roomType): ?><option value="<?= e($roomType['room_type']) ?>" <?= $roomType['room_type'] === $selectedRoomType ? 'selected' : '' ?>><?= e($roomType['room_type']) ?></option><?php endforeach; ?></select></label>
        <?php endif; ?>
        <noscript><button class="button secondary" type="submit">Tampilkan galeri</button></noscript>
    </form>
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
            <div class="gallery-category-heading">
                <span class="gallery-category-icon <?= e($category) ?>"><?= e($categoryInfo['icon']) ?></span>
                <div><h2><?= e($categoryInfo['title']) ?></h2><p><?= e($categoryInfo['description']) ?></p></div>
                <span class="gallery-photo-count"><?= count($photosByCategory[$category]) ?> foto</span>
            </div>
            <form method="post" enctype="multipart/form-data" class="gallery-upload-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload_room_type_gallery_photos">
                <input type="hidden" name="building_id" value="<?= $buildingId ?>">
                <input type="hidden" name="room_type" value="<?= e($selectedRoomType) ?>">
                <input type="hidden" name="category" value="<?= e($category) ?>">
                <label class="field"><span>Tambah <?= e($categoryInfo['title']) ?></span><input type="file" name="gallery_photos[]" accept="image/jpeg,image/png,image/webp" multiple required><small class="field-help">Pilih hingga 4 foto sekaligus. Format JPG, PNG, atau WebP; maksimal 4 MB per foto.</small></label>
                <button class="button primary" type="submit">＋ Unggah foto</button>
            </form>
            <?php if ($photosByCategory[$category]): ?>
                <div class="gallery-photo-grid">
                    <?php foreach ($photosByCategory[$category] as $photo): ?>
                        <article class="gallery-photo-card">
                            <img src="<?= e($photo['photo_path']) ?>" alt="<?= e($categoryInfo['title'] . ' ' . $selectedRoomType) ?>" loading="lazy">
                            <?php if ($category === 'room'): ?>
                                <form method="post" class="gallery-photo-assign">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="select_room_gallery_photo">
                                    <input type="hidden" name="building_id" value="<?= $buildingId ?>">
                                    <input type="hidden" name="room_type" value="<?= e($selectedRoomType) ?>">
                                    <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                                    <label class="field"><span>Gunakan sebagai foto profil</span><select name="room_id" required><option value="">Pilih kamar</option><?php foreach ($matchingRooms as $matchingRoom): ?><option value="<?= (int) $matchingRoom['id'] ?>"><?= e($matchingRoom['room_number']) ?><?= $matchingRoom['profile_photo_path'] === $photo['photo_path'] ? ' · Foto saat ini' : '' ?></option><?php endforeach; ?></select></label>
                                    <button class="button secondary compact-button" type="submit">Terapkan ke kamar</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" class="gallery-photo-delete" onsubmit="return confirm('Hapus foto dari galeri tipe kamar ini?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_room_type_gallery_photo">
                                <input type="hidden" name="building_id" value="<?= $buildingId ?>">
                                <input type="hidden" name="room_type" value="<?= e($selectedRoomType) ?>">
                                <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                                <button class="text-button" type="submit">Hapus foto</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="gallery-empty-state"><span>▧</span><strong>Galeri <?= e($categoryInfo['title']) ?> masih kosong</strong><small>Foto yang diunggah akan langsung tersedia pada halaman terkait.</small></div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
