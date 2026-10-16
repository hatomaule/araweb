<?php
if ($page === 'room') {
    $roomId = max(0, (int) ($_GET['id'] ?? 0));
    $room = one(
        $pdo,
        "SELECT rm.*,b.id AS property_id,b.name AS building_name,b.address AS building_address,
                b.description AS building_description,b.gender_policy,b.general_facilities,
                b.house_rules,b.latitude,b.longitude,b.owner_user_id,
                u.name AS owner_name,u.phone AS owner_phone,u.profile_photo_path AS owner_photo,
                u.gender AS owner_gender,u.avatar_key AS owner_avatar,
                CASE WHEN rm.status='available' AND NOT EXISTS (
                    SELECT 1 FROM rentals r
                    WHERE r.room_id=rm.id AND (
                        (r.status='active' AND r.start_date<=CURDATE()
                            AND (r.end_date IS NULL OR r.end_date>=CURDATE()))
                        OR (r.status='upcoming' AND (r.end_date IS NULL OR r.end_date>=CURDATE())
                            AND (r.booking_expires_at IS NULL OR r.booking_expires_at>NOW()
                                OR EXISTS (SELECT 1 FROM invoices i JOIN payments p ON p.invoice_id=i.id
                                    WHERE i.rental_id=r.id AND p.status='pending')))
                    )
                ) THEN 1 ELSE 0 END AS is_available
         FROM rooms rm JOIN kos_buildings b ON b.id=rm.building_id
         LEFT JOIN users u ON u.id=b.owner_user_id
         WHERE rm.id=:id AND b.status='active'",
        ['id' => $roomId]
    );
    if (!$room) {
        http_response_code(404);
        exit('Kamar tidak ditemukan atau belum tersedia untuk publik.');
    }
    $pdo->prepare(
        "INSERT INTO property_activity_events (building_id,user_id,visitor_key,event_type)
         VALUES (:building_id,:user_id,:visitor_key,'room_view')"
    )->execute([
        'building_id' => $room['property_id'],
        'user_id' => $user['id'] ?? null,
        'visitor_key' => analytics_visitor_key(),
    ]);
    $roomPhotos = rows($pdo,
        "SELECT id,category,photo_path FROM room_photos WHERE room_id=:id
         ORDER BY (category='room') DESC,id", ['id' => $roomId]);
    $roomGalleryPhotos = [];
    if (!empty($room['profile_photo_path'])) {
        $roomGalleryPhotos[] = [
            'photo_path' => $room['profile_photo_path'],
            'category' => 'room',
        ];
    }
    foreach ($roomPhotos as $photo) {
        if ($photo['photo_path'] !== ($room['profile_photo_path'] ?? null)) {
            $roomGalleryPhotos[] = $photo;
        }
    }
    $roomAvailable = (bool) $room['is_available'];
    $genderLabel = ['mixed' => 'Campur', 'male' => 'Putra', 'female' => 'Putri'][$room['gender_policy']];
    ?>
    <!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="<?= e($room['room_type'] ?: 'Kamar ' . $room['room_number']) ?> di <?= e($room['building_name']) ?>. Tarif <?= e(money($room['monthly_rate'])) ?> per bulan.">
    <title><?= e($room['room_type'] ?: 'Kamar ' . $room['room_number']) ?> — <?= e($room['building_name']) ?> | Araweb</title><link rel="stylesheet" href="style.css?v=20261016-room-detail-footer">
    <?php if ($room['latitude'] !== null && $room['longitude'] !== null): ?><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""><?php endif; ?>
    </head>
    <body class="public-page property-page"><header class="public-header" id="top"><nav class="public-nav">
        <a class="tiara-brand araweb-brand" href="index.php" aria-label="Araweb, beranda"><span class="tiara-brand-icon">A</span><span>ara<span>web</span><small>HUNIAN NYAMAN</small></span></a>
        <a class="public-nav-cta" href="index.php?page=property&amp;id=<?= (int) $room['property_id'] ?>">Lihat informasi kos ↗</a>
    </nav></header>
    <main class="property-detail-main room-detail-main">
        <a class="text-link" href="index.php?page=home#kamar">← Kembali ke pilihan kamar</a>
        <section class="property-gallery room-detail-gallery">
            <div class="property-gallery-main room-detail-photo-carousel">
                <?php if ($roomGalleryPhotos): ?>
                    <?php if (count($roomGalleryPhotos) > 1): ?>
                        <button type="button" class="property-carousel-arrow room-photo-arrow room-photo-arrow-left" data-carousel-target="room-detail-photos" data-carousel-direction="left" aria-label="Foto sebelumnya" disabled>‹</button>
                        <button type="button" class="property-carousel-arrow room-photo-arrow room-photo-arrow-right" data-carousel-target="room-detail-photos" data-carousel-direction="right" aria-label="Foto berikutnya">›</button>
                    <?php endif; ?>
                    <div class="room-detail-photo-track" id="room-detail-photos" tabindex="0" aria-label="Galeri foto kamar">
                        <?php foreach ($roomGalleryPhotos as $photo): ?>
                            <button class="room-detail-photo-slide" type="button" data-photo-preview="<?= e($photo['photo_path']) ?>" data-photo-alt="<?= e(($photo['category'] === 'facility' ? 'Foto fasilitas kamar ' : 'Foto kamar ') . $room['room_number']) ?>" aria-label="Lihat <?= $photo['category'] === 'facility' ? 'foto fasilitas' : 'foto kamar' ?> ukuran penuh">
                                <img src="<?= e($photo['photo_path']) ?>" alt="<?= $photo['category'] === 'facility' ? 'Foto fasilitas kamar' : 'Foto kamar' ?> <?= e($room['room_number']) ?>">
                                <?php if ($photo['category'] === 'facility'): ?><span class="room-detail-photo-label">Foto fasilitas</span><?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="property-photo-placeholder araweb-house-placeholder"><span aria-hidden="true">⌂</span><strong>Kamar <?= e($room['room_number']) ?></strong><small>Foto kamar dapat ditambahkan pengelola</small></div>
                <?php endif; ?>
            </div>
            <div class="room-detail-map-preview">
                <?php if ($room['latitude'] !== null && $room['longitude'] !== null): ?>
                    <div id="room-detail-map" class="property-banner-map" aria-label="Peta lokasi <?= e($room['building_name']) ?>"></div>
                    <div class="property-location-actions"><a class="button secondary compact-button" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e($room['latitude']) ?>%2C<?= e($room['longitude']) ?>" target="_blank" rel="noopener noreferrer">Buka Google Maps ↗</a></div>
                <?php else: ?>
                    <div class="property-location-empty"><span aria-hidden="true">⌖</span><strong>Lokasi kos belum diatur</strong><small><?= e($room['building_name']) ?></small></div>
                    <div class="property-location-actions"><a class="button secondary compact-button" href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode($room['building_address']) ?>" target="_blank" rel="noopener noreferrer">Cari alamat di Google Maps ↗</a></div>
                <?php endif; ?>
            </div>
        </section>
        <div class="property-detail-layout"><div class="property-detail-content">
            <section class="property-intro"><span class="badge <?= $roomAvailable ? 'active' : 'inactive' ?>"><?= $roomAvailable ? 'Tersedia sekarang' : 'Tidak tersedia' ?></span><span class="badge active"><?= e($genderLabel) ?></span><h1><?= e($room['room_type'] ?: 'Kamar ' . $room['room_number']) ?></h1><p><?= e($room['building_name']) ?> · <?= e($room['building_address']) ?></p><p><?= e($room['floor'] ? 'Lantai ' . $room['floor'] . ' · ' : '') ?>Kamar <?= e($room['room_number']) ?></p><?php if ($room['building_description']): ?><p><?= nl2br(e($room['building_description'])) ?></p><?php endif; ?></section>
            <section class="property-section" id="fasilitas-kamar"><h2>Fasilitas kamar</h2><div class="property-feature-list"><?php foreach (array_filter(array_map('trim', preg_split('/[,;\r\n]+/', (string) $room['facilities']))) as $facility): ?><span><?= e($facility) ?></span><?php endforeach; ?><?php if (!$room['facilities']): ?><span>Informasi fasilitas belum ditambahkan.</span><?php endif; ?></div></section>
            <section class="property-section" id="fasilitas-umum"><h2>Fasilitas umum &amp; peraturan</h2><div class="property-feature-list"><?php foreach (array_filter(array_map('trim', preg_split('/[,;\r\n]+/', (string) $room['general_facilities']))) as $facility): ?><span><?= e($facility) ?></span><?php endforeach; ?><?php if (!$room['general_facilities']): ?><span>Informasi fasilitas umum belum ditambahkan.</span><?php endif; ?></div><?php if ($room['house_rules']): ?><h3>Peraturan kos</h3><p><?= nl2br(e($room['house_rules'])) ?></p><?php endif; ?></section>
            <section class="property-section"><h2>Informasi kos</h2><p><?= e($room['building_name']) ?></p><p><?= e($room['building_address']) ?></p><a class="text-link" href="index.php?page=property&amp;id=<?= (int) $room['property_id'] ?>">Lihat profil kos dan kamar lainnya ↗</a></section>
            <?php if ($room['owner_name']): ?><section class="property-section"><h2>Pengelola</h2><div class="property-owner-card"><?= avatar_markup(['name' => $room['owner_name'], 'gender' => $room['owner_gender'], 'profile_photo_path' => $room['owner_photo']], 'profile-avatar') ?><div><strong><?= e($room['owner_name']) ?></strong><small>Pengelola kos</small><?php if ($room['owner_phone']): ?><a href="https://wa.me/<?= e(preg_replace('/\D+/', '', $room['owner_phone'])) ?>">Hubungi pengelola ↗</a><?php endif; ?></div></div></section><?php endif; ?>
        </div><aside class="property-booking-card room-booking-card"><span><?= $roomAvailable ? 'Kamar tersedia' : 'Kamar sedang tidak tersedia' ?></span><strong><?= e(money($room['monthly_rate'])) ?><small> / bulan</small></strong><p>Tarif bulanan kamar <?= e($room['room_number']) ?> di <?= e($room['building_name']) ?>.</p><?php if ($roomAvailable): ?><a class="button primary" href="<?= $user && $user['role'] === 'seeker' ? 'index.php?page=booking_form&id=' . $roomId : 'index.php?page=register&role=seeker&next=booking&id=' . $roomId ?>">Ajukan booking kamar</a><?php else: ?><span class="room-unavailable-note">Booking belum dapat dilakukan untuk kamar ini.</span><?php endif; ?></aside></div>
    </main><footer class="public-footer room-detail-footer">
        <div class="public-footer-inner room-detail-footer-inner">
            <div class="public-footer-brand">
                <a class="tiara-brand araweb-brand" href="index.php" aria-label="Araweb, beranda"><span class="tiara-brand-icon">A</span><span>ara<span>web</span><small>HUNIAN NYAMAN</small></span></a>
                <p>Temukan hunian nyaman dan ruang untuk memulai cerita barumu.</p>
            </div>
            <nav class="public-footer-links" aria-label="Jelajahi Araweb">
                <h2>Jelajahi Araweb</h2>
                <a href="index.php?page=property&amp;id=<?= (int) $room['property_id'] ?>">› <span><?= e($room['building_name']) ?></span></a>
                <a href="index.php?page=home#kamar">› <span>Lihat kamar lainnya</span></a>
                <a href="#top">↑ <span>Kembali ke atas</span></a>
            </nav>
            <div class="public-footer-bottom">
                <span>© <?= date('Y') ?> Araweb. Hak cipta dilindungi.</span>
                <a href="#top" aria-label="Kembali ke atas">↑</a>
            </div>
        </div>
    </footer>
    <dialog class="photo-lightbox" id="photo-lightbox" aria-label="Pratinjau foto"><button class="photo-lightbox-close" type="button" data-photo-preview-close aria-label="Tutup foto">×</button><img src="" alt=""></dialog>
    <?php if ($room['latitude'] !== null && $room['longitude'] !== null): ?><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script><script>
        const roomMap = L.map('room-detail-map', {scrollWheelZoom: false, zoomControl: false}).setView([<?= json_encode((float) $room['latitude']) ?>, <?= json_encode((float) $room['longitude']) ?>], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'}).addTo(roomMap);
        L.marker([<?= json_encode((float) $room['latitude']) ?>, <?= json_encode((float) $room['longitude']) ?>]).addTo(roomMap).bindPopup(<?= json_encode($room['building_name'] . ' - Kamar ' . $room['room_number'], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>);
        setTimeout(() => roomMap.invalidateSize(), 100);
        document.getElementById('room-detail-map').addEventListener('click', () => roomMap.scrollWheelZoom.enable());
    </script><?php endif; ?>
    <script src="app.js?v=20261016-interactive-controls"></script></body></html>
    <?php
    exit;
}

if ($page === 'property') {
    $buildingId = max(0, (int) ($_GET['id'] ?? 0));
    $property = one(
        $pdo,
        "SELECT b.*,u.name AS owner_name,u.phone AS owner_phone,u.profile_photo_path AS owner_photo,
                u.gender AS owner_gender,u.avatar_key AS owner_avatar
         FROM kos_buildings b LEFT JOIN users u ON u.id=b.owner_user_id
         WHERE b.id=:id AND b.status='active'",
        ['id' => $buildingId]
    );
    if (!$property) {
        http_response_code(404);
        exit('Kos tidak ditemukan atau belum tersedia untuk publik.');
    }
    $pdo->prepare(
        "INSERT INTO property_activity_events (building_id,user_id,visitor_key,event_type)
         VALUES (:building_id,:user_id,:visitor_key,'property_view')"
    )->execute([
        'building_id' => $buildingId,
        'user_id' => $user['id'] ?? null,
        'visitor_key' => analytics_visitor_key(),
    ]);
    $propertyPhotos = rows(
        $pdo,
        'SELECT id,photo_path FROM property_photos WHERE building_id=:id ORDER BY display_order,id',
        ['id' => $buildingId]
    );
    $propertyGalleryPhotos = [];
    if (!empty($property['profile_photo_path'])) {
        $propertyGalleryPhotos[] = $property['profile_photo_path'];
    }
    foreach ($propertyPhotos as $photo) {
        if (!in_array($photo['photo_path'], $propertyGalleryPhotos, true)) {
            $propertyGalleryPhotos[] = $photo['photo_path'];
        }
    }
    $propertyRooms = rows(
        $pdo,
        "SELECT rm.* FROM rooms rm WHERE rm.building_id=:id AND rm.status='available'
         AND NOT EXISTS (SELECT 1 FROM rentals r WHERE r.room_id=rm.id
           AND r.status IN ('active','upcoming') AND (r.end_date IS NULL OR r.end_date>=CURDATE())
           AND (r.booking_expires_at IS NULL OR r.booking_expires_at>NOW()
             OR EXISTS (SELECT 1 FROM invoices i JOIN payments p ON p.invoice_id=i.id
               WHERE i.rental_id=r.id AND p.status='pending')))
         ORDER BY rm.monthly_rate,rm.room_number",
        ['id' => $buildingId]
    );
    $reviewReplyColumn = $pdo->query(
        "SHOW COLUMNS FROM property_reviews LIKE 'operator_reply'"
    )->fetch();
    $reviewReplyFields = $reviewReplyColumn
        ? 'pr.operator_reply,pr.operator_replied_at'
        : 'NULL AS operator_reply,NULL AS operator_replied_at';
    $propertyReviews = rows(
        $pdo,
        "SELECT pr.rating,pr.review_text,pr.created_at,{$reviewReplyFields},
                u.name AS reviewer_name
         FROM property_reviews pr JOIN tenants t ON t.id=pr.tenant_id
         LEFT JOIN users u ON u.id=t.user_id
         WHERE pr.building_id=:id AND pr.status='published'
         ORDER BY pr.created_at DESC LIMIT 30",
        ['id' => $buildingId]
    );
    $averageRating = $propertyReviews
        ? array_sum(array_column($propertyReviews, 'rating')) / count($propertyReviews) : null;
    $canReview = false;
    if ($user && $user['role'] === 'seeker') {
        $canReview = (bool) one(
            $pdo,
            "SELECT r.id FROM rentals r JOIN tenants t ON t.id=r.tenant_id
             JOIN rooms rm ON rm.id=r.room_id
             WHERE t.user_id=:user_id AND rm.building_id=:building_id AND r.status='ended'
               AND (r.end_date IS NULL OR r.end_date<CURDATE()) LIMIT 1",
            ['user_id' => $user['id'], 'building_id' => $buildingId]
        );
    }
    $genderLabel = ['mixed' => 'Campur', 'male' => 'Putra', 'female' => 'Putri'][$property['gender_policy']];
    $canManageProperty = $user && can_manage_building($pdo, $user, $buildingId);
    ?>
    <!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="<?= e($property['name']) ?> — foto, kamar, fasilitas, lokasi, ulasan dan informasi pemilik kos.">
    <title><?= e($property['name']) ?> — Araweb</title><link rel="stylesheet" href="style.css?v=20261015-photo-carousel-lightbox">
    <?php if ($property['latitude'] !== null && $property['longitude'] !== null): ?><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""><?php endif; ?>
    </head><body class="public-page property-page">
    <header class="public-header"><nav class="public-nav"><a class="tiara-brand araweb-brand" href="index.php" aria-label="Araweb, beranda"><span class="tiara-brand-icon">A</span><span>ara<span>web</span><small>HUNIAN NYAMAN</small></span></a><form class="public-search" action="index.php" method="get"><input type="hidden" name="page" value="home"><input name="q" placeholder="Cari nama kos atau alamat" aria-label="Cari nama kos atau alamat"><button class="button primary">Cari</button></form><?php if ($user): ?><a class="public-nav-cta" href="index.php?page=<?= $user['role'] === 'seeker' ? 'profile' : ($user['role'] === 'admin' ? 'platform_dashboard' : 'dashboard') ?>"><?= e($user['name']) ?></a><?php else: ?><a class="public-nav-cta" href="index.php?page=login">Masuk / Daftar</a><?php endif; ?></nav>
    <nav class="property-tabs"><a href="#foto">Foto properti</a><a href="#fasilitas-kamar">Fasilitas kamar</a><a href="#fasilitas-umum">Fasilitas umum</a><a href="#lokasi">Lokasi</a><a href="#review">Review</a><a href="#pemilik">Pemilik kos</a></nav></header>
    <main class="property-detail-main">
        <a class="text-link" href="index.php?page=home">← Kembali ke pencarian</a>
        <section id="foto" class="property-gallery property-banner">
            <div class="property-gallery-main property-profile-photo">
                <?php if ($propertyGalleryPhotos): ?>
                    <?php if (count($propertyGalleryPhotos) > 1): ?>
                        <button type="button" class="property-carousel-arrow room-photo-arrow room-photo-arrow-left" data-carousel-target="property-detail-photos" data-carousel-direction="left" aria-label="Foto sebelumnya" disabled>‹</button>
                        <button type="button" class="property-carousel-arrow room-photo-arrow room-photo-arrow-right" data-carousel-target="property-detail-photos" data-carousel-direction="right" aria-label="Foto berikutnya">›</button>
                    <?php endif; ?>
                    <div class="room-detail-photo-track" id="property-detail-photos" tabindex="0" aria-label="Galeri foto kos">
                        <?php foreach ($propertyGalleryPhotos as $index => $photoPath): ?>
                            <button class="room-detail-photo-slide" type="button" data-photo-preview="<?= e($photoPath) ?>" data-photo-alt="<?= e('Foto kos ' . $property['name']) ?>" aria-label="Lihat foto kos ukuran penuh">
                                <img src="<?= e($photoPath) ?>" alt="<?= e(($index === 0 ? 'Foto profil kos ' : 'Foto galeri kos ') . $property['name']) ?>">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="property-photo-placeholder araweb-house-placeholder"><span aria-hidden="true">⌂</span><strong><?= e($property['name']) ?></strong><small>Foto profil kos belum ditambahkan</small></div>
                <?php endif; ?>
                <?php if ($canManageProperty): ?><a class="property-banner-edit button primary" href="index.php?page=building_form&amp;id=<?= $buildingId ?>"><?= !empty($property['profile_photo_path']) ? 'Ganti foto kos' : 'Tambah foto kos' ?></a><?php endif; ?>
            </div>
            <div class="property-gallery-side property-location-preview" id="lokasi">
                <?php if ($property['latitude'] !== null && $property['longitude'] !== null): ?>
                    <div id="property-map" class="property-banner-map" aria-label="Peta lokasi <?= e($property['name']) ?>"></div>
                <?php else: ?>
                    <div class="property-location-empty"><span aria-hidden="true">⌖</span><strong>Lokasi kos belum diatur</strong><small>Tambahkan pin lokasi agar mudah ditemukan.</small></div>
                <?php endif; ?>
                <div class="property-location-actions">
                    <?php if ($property['latitude'] !== null && $property['longitude'] !== null): ?>
                        <a class="button secondary compact-button" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e($property['latitude']) ?>%2C<?= e($property['longitude']) ?>" target="_blank" rel="noopener noreferrer">Buka Google Maps ↗</a>
                    <?php else: ?>
                        <a class="button secondary compact-button" href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode($property['address']) ?>" target="_blank" rel="noopener noreferrer">Cari alamat di Google Maps ↗</a>
                    <?php endif; ?>
                    <?php if ($canManageProperty): ?><a class="button primary compact-button" href="index.php?page=building_form&amp;id=<?= $buildingId ?>#lokasi-gedung">Edit lokasi</a><?php endif; ?>
                </div>
            </div>
        </section>
        <div class="property-detail-layout"><div class="property-detail-content">
            <section class="property-intro"><span class="badge active"><?= e($genderLabel) ?></span><h1><?= e($property['name']) ?></h1><p><?= e($property['address']) ?></p><?php if ($averageRating !== null): ?><strong class="property-rating">★ <?= number_format($averageRating,1) ?> <small>(<?= count($propertyReviews) ?> ulasan)</small></strong><?php else: ?><span class="muted">Belum ada review</span><?php endif; ?><?php if ($property['description']): ?><p><?= nl2br(e($property['description'])) ?></p><?php endif; ?></section>
            <section class="property-section" id="fasilitas-kamar"><h2>Fasilitas kamar</h2><p class="muted">Fasilitas ditampilkan untuk setiap kamar agar informasi dan tarifnya jelas.</p><div class="property-room-list"><?php foreach ($propertyRooms as $room): ?><article><div><strong><?= e($room['room_type']) ?> · Kamar <?= e($room['room_number']) ?></strong><small><?= e($room['floor'] ? 'Lantai ' . $room['floor'] . ' · ' : '') ?><?= e($room['facilities'] ?: 'Tanyakan fasilitas kepada pemilik') ?></small></div><div><strong><?= e(money($room['monthly_rate'])) ?>/bln</strong><a class="button primary" href="<?= $user && $user['role']==='seeker' ? 'index.php?page=booking_form&id='.(int)$room['id'] : 'index.php?page=register&role=seeker&next=booking&id='.(int)$room['id'] ?>">Booking</a></div></article><?php endforeach; ?><?php if (!$propertyRooms): ?><p class="muted">Saat ini belum ada kamar tersedia.</p><?php endif; ?></div></section>
            <section class="property-section" id="fasilitas-umum"><h2>Fasilitas umum</h2><div class="property-feature-list"><?php foreach (array_filter(array_map('trim', preg_split('/[,;\r\n]+/', (string) $property['general_facilities']))) as $facility): ?><span><?= e($facility) ?></span><?php endforeach; ?><?php if (!$property['general_facilities']): ?><span>Informasi fasilitas umum belum ditambahkan.</span><?php endif; ?></div><?php if ($property['house_rules']): ?><h3>Peraturan kos</h3><p><?= nl2br(e($property['house_rules'])) ?></p><?php endif; ?></section>
            <section class="property-section"><h2>Alamat kos</h2><p><?= e($property['address']) ?></p></section>
            <section class="property-section" id="review"><h2>Review penyewa</h2><?php if ($propertyReviews): ?><div class="property-review-list"><?php foreach ($propertyReviews as $review): ?><article><strong><?= e($review['reviewer_name'] ?: 'Penyewa') ?> · <?= str_repeat('★',(int)$review['rating']) ?></strong><small><?= e(date_id($review['created_at'])) ?></small><p><?= nl2br(e($review['review_text'])) ?></p><?php if ($review['operator_reply']): ?><div class="property-review-reply"><strong>Balasan pengelola</strong><?php if ($review['operator_replied_at']): ?><small><?= e(date_id($review['operator_replied_at'])) ?></small><?php endif; ?><p><?= nl2br(e($review['operator_reply'])) ?></p></div><?php endif; ?></article><?php endforeach; ?></div><?php else: ?><p class="muted">Kos ini belum memiliki review dari penyewa.</p><?php endif; ?><?php if ($canReview): ?><form method="post" class="property-review-form"><?= csrf_field() ?><input type="hidden" name="action" value="submit_review"><input type="hidden" name="building_id" value="<?= $buildingId ?>"><label class="field"><span>Rating</span><select name="rating" required><?php for($star=5;$star>=1;$star--): ?><option value="<?= $star ?>"><?= $star ?> bintang</option><?php endfor; ?></select></label><label class="field"><span>Ceritakan pengalaman Anda</span><textarea name="review_text" rows="4" maxlength="3000" required></textarea></label><button class="button primary">Kirim review</button></form><?php endif; ?></section>
            <section class="property-section" id="pemilik"><h2>Pemilik kos</h2><article class="property-owner-card"><?= avatar_markup(['name' => $property['owner_name'] ?: 'Pengelola kos', 'gender' => $property['owner_gender'], 'profile_photo_path' => $property['owner_photo'], 'avatar_key' => $property['owner_avatar']]) ?><div><strong><?= e($property['owner_name'] ?: 'Pengelola kos') ?></strong><small>Operator kos</small><?php if ($property['owner_phone']): ?><a href="https://wa.me/<?= e(preg_replace('/\D+/', '', $property['owner_phone'])) ?>" target="_blank" rel="noopener noreferrer">Hubungi melalui WhatsApp</a><?php endif; ?></div></article></section>
        </div><aside class="property-booking-card"><span>Mulai dari</span><strong><?= e($propertyRooms ? money(min(array_column($propertyRooms, 'monthly_rate'))) : '—') ?><small>/ bulan</small></strong><a class="button primary full" href="#fasilitas-kamar">Pilih kamar tersedia</a><p>Login diperlukan untuk mengirim permintaan booking. Pemilik kos akan meninjau permintaan Anda.</p></aside></div>
    </main>
    <?php if ($property['latitude'] !== null && $property['longitude'] !== null): ?><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script><script>const propertyMap=L.map('property-map',{scrollWheelZoom:false,zoomControl:false}).setView([<?= json_encode((float)$property['latitude']) ?>,<?= json_encode((float)$property['longitude']) ?>],14);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(propertyMap);L.marker([<?= json_encode((float)$property['latitude']) ?>,<?= json_encode((float)$property['longitude']) ?>]).addTo(propertyMap).bindPopup(<?= json_encode($property['name'], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>);setTimeout(()=>propertyMap.invalidateSize(),100);document.getElementById('property-map').addEventListener('click',()=>propertyMap.scrollWheelZoom.enable());</script><?php endif; ?>
    <dialog class="photo-lightbox" id="photo-lightbox" aria-label="Pratinjau foto"><button class="photo-lightbox-close" type="button" data-photo-preview-close aria-label="Tutup foto">×</button><img src="" alt=""></dialog>
    <script src="app.js?v=20261016-interactive-controls"></script>
    </body></html><?php
    exit;
}

if (in_array($page, ['home', 'search'], true)) {
    $searchTerm = trim(isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '');
    if (strlen($searchTerm) > 255) {
        $searchTerm = substr($searchTerm, 0, 255);
    }
    $publicRooms = rows(
        $pdo,
        "SELECT rm.*, b.name AS building_name, b.address AS building_address,
                b.description AS building_description, b.gender_policy,
                b.latitude AS building_latitude, b.longitude AS building_longitude,
                b.profile_photo_path AS building_profile_photo_path,
                CASE WHEN rm.status='available' AND NOT EXISTS (
                    SELECT 1 FROM rentals r
                    WHERE r.room_id=rm.id AND (
                        (r.status='active' AND r.start_date<=CURDATE()
                            AND (r.end_date IS NULL OR r.end_date>=CURDATE()))
                        OR (r.status='upcoming' AND (r.end_date IS NULL OR r.end_date>=CURDATE())
                            AND (r.booking_expires_at IS NULL OR r.booking_expires_at>NOW()
                                OR EXISTS (SELECT 1 FROM invoices i JOIN payments p ON p.invoice_id=i.id
                                    WHERE i.rental_id=r.id AND p.status='pending')))
                    )
                ) THEN 1 ELSE 0 END AS is_available
         FROM rooms rm JOIN kos_buildings b ON b.id=rm.building_id
         WHERE b.status='active'
         AND (:search_name='' OR b.name LIKE :name_like OR b.address LIKE :address_like)
         ORDER BY rm.monthly_rate, b.name, rm.room_number",
        [
            'search_name' => $searchTerm,
            'name_like' => '%' . $searchTerm . '%',
            'address_like' => '%' . $searchTerm . '%',
        ]
    );
    $availableRooms = array_values(array_filter(
        $publicRooms,
        static fn(array $room): bool => (bool) $room['is_available']
    ));
    if ($searchTerm !== '' && $user && $user['role'] === 'seeker') {
        $searchLog = $pdo->prepare(
            'INSERT INTO search_history (user_id, search_query) VALUES (:user_id, :search_query)'
        );
        $searchLog->execute(['user_id' => $user['id'], 'search_query' => $searchTerm]);
        $pdo->prepare(
            'DELETE FROM search_history WHERE user_id=:user_id AND id NOT IN
             (SELECT id FROM (SELECT id FROM search_history WHERE user_id=:user_id_2
              ORDER BY searched_at DESC,id DESC LIMIT 100) recent_searches)'
        )->execute(['user_id' => $user['id'], 'user_id_2' => $user['id']]);
    }
    $savedSearches = $user && $user['role'] === 'seeker'
        ? rows($pdo, 'SELECT search_query,searched_at FROM search_history
            WHERE user_id=:user_id ORDER BY searched_at DESC,id DESC LIMIT 8', ['user_id' => $user['id']])
        : [];
    $accountPanel = null;
    if ($user && $user['role'] === 'seeker') {
        $accountPanel = one(
            $pdo,
            "SELECT
                (SELECT COALESCE(SUM(p.amount),0) FROM payments p
                 JOIN invoices i ON i.id=p.invoice_id JOIN rentals r ON r.id=i.rental_id
                 JOIN tenants t ON t.id=r.tenant_id
                 WHERE t.user_id=:paid_user AND p.status='confirmed') AS paid_total,
                (SELECT COALESCE(SUM(GREATEST(i.amount_due-COALESCE(p.paid,0),0)),0)
                 FROM invoices i JOIN rentals r ON r.id=i.rental_id
                 JOIN tenants t ON t.id=r.tenant_id
                 LEFT JOIN (SELECT invoice_id,SUM(amount) AS paid FROM payments
                            WHERE status='confirmed' GROUP BY invoice_id) p ON p.invoice_id=i.id
                 WHERE t.user_id=:open_user AND i.status='open') AS outstanding,
                (SELECT COUNT(*) FROM payments p
                 JOIN invoices i ON i.id=p.invoice_id JOIN rentals r ON r.id=i.rental_id
                 JOIN tenants t ON t.id=r.tenant_id
                 WHERE t.user_id=:pending_user AND p.status='pending') AS pending_payments,
                (SELECT COUNT(*) FROM rentals r JOIN tenants t ON t.id=r.tenant_id
                 WHERE t.user_id=:rental_user AND r.status='active'
                   AND r.start_date<=CURDATE() AND (r.end_date IS NULL OR r.end_date>=CURDATE())) AS active_rentals",
            [
                'paid_user' => $user['id'], 'open_user' => $user['id'],
                'pending_user' => $user['id'], 'rental_user' => $user['id'],
            ]
        );
    }
    $roomPhotos = [];
    if ($publicRooms) {
        $roomIds = array_map(static fn(array $room): int => (int) $room['id'], $publicRooms);
        $placeholders = implode(',', array_fill(0, count($roomIds), '?'));
        $photoStatement = $pdo->prepare(
            "SELECT room_id, category, photo_path FROM room_photos
             WHERE room_id IN ({$placeholders})
             ORDER BY (category = 'room') DESC, id"
        );
        $photoStatement->execute($roomIds);
        foreach ($photoStatement->fetchAll() as $photo) {
            $roomPhotos[(int) $photo['room_id']][] = $photo;
        }
    }
    $roomListings = [];
    if (!$searchTerm) {
        foreach ($publicRooms as $room) {
            $roomId = (int) $room['id'];
            $room['cover_photo'] = $room['profile_photo_path'] ?: null;
            foreach ($roomPhotos[$roomId] ?? [] as $photo) {
                if ($room['cover_photo'] === null && $photo['category'] === 'room') {
                    $room['cover_photo'] = $photo['photo_path'];
                    break;
                }
            }
            $roomListings[] = $room;
        }
    }
    $searchListings = [];
    foreach ($publicRooms as $room) {
        $buildingId = (int) $room['building_id'];
        if (!isset($searchListings[$buildingId])) {
            $searchListings[$buildingId] = [
                'id' => $buildingId,
                'name' => $room['building_name'],
                'address' => $room['building_address'],
                'latitude' => $room['building_latitude'],
                'longitude' => $room['building_longitude'],
                'rooms' => [],
                'available_count' => 0,
                'minimum_rate' => (float) $room['monthly_rate'],
                'cover_photo' => $room['building_profile_photo_path'] ?: null,
            ];
        }
        $searchListings[$buildingId]['rooms'][] = $room;
        if ($room['is_available']) {
            $searchListings[$buildingId]['available_count']++;
        }
        $searchListings[$buildingId]['minimum_rate'] = min(
            $searchListings[$buildingId]['minimum_rate'],
            (float) $room['monthly_rate']
        );
    }
    if ($searchListings) {
        $buildingIds = array_keys($searchListings);
        if ($searchTerm !== '') {
            $activityStatement = $pdo->prepare(
                "INSERT INTO property_activity_events
                    (building_id,user_id,visitor_key,event_type,search_query)
                 VALUES (:building_id,:user_id,:visitor_key,'search_result',:search_query)"
            );
            foreach ($buildingIds as $buildingId) {
                $activityStatement->execute([
                    'building_id' => $buildingId,
                    'user_id' => $user['id'] ?? null,
                    'visitor_key' => analytics_visitor_key(),
                    'search_query' => $searchTerm,
                ]);
            }
        }
        $placeholders = implode(',', array_fill(0, count($buildingIds), '?'));
        $propertyPhotoStatement = $pdo->prepare(
            "SELECT building_id,photo_path FROM property_photos
             WHERE building_id IN ({$placeholders}) ORDER BY display_order,id"
        );
        $propertyPhotoStatement->execute($buildingIds);
        foreach ($propertyPhotoStatement->fetchAll() as $photo) {
            $buildingId = (int) $photo['building_id'];
            if ($searchListings[$buildingId]['cover_photo'] === null) {
                $searchListings[$buildingId]['cover_photo'] = $photo['photo_path'];
            }
        }
    }
    $searchListings = array_values($searchListings);
    $propertyIds = array_column($searchListings, 'id');
    foreach ($searchListings as &$listing) {
        $listing['view_count'] = 0;
        $listing['average_rating'] = null;
        $listing['review_count'] = 0;
    }
    unset($listing);
    if ($propertyIds) {
        $propertyPlaceholders = implode(',', array_fill(0, count($propertyIds), '?'));
        $viewStatement = $pdo->prepare(
            "SELECT building_id,COUNT(*) AS view_count
             FROM property_activity_events
             WHERE building_id IN ({$propertyPlaceholders})
               AND event_type IN ('property_view','room_view')
             GROUP BY building_id"
        );
        $viewStatement->execute($propertyIds);
        $viewCounts = [];
        foreach ($viewStatement->fetchAll() as $viewRow) {
            $viewCounts[(int) $viewRow['building_id']] = (int) $viewRow['view_count'];
        }
        $reviewStatement = $pdo->prepare(
            "SELECT building_id,AVG(rating) AS average_rating,COUNT(*) AS review_count
             FROM property_reviews
             WHERE building_id IN ({$propertyPlaceholders}) AND status='published'
             GROUP BY building_id"
        );
        $reviewStatement->execute($propertyIds);
        $reviewStats = [];
        foreach ($reviewStatement->fetchAll() as $reviewRow) {
            $reviewStats[(int) $reviewRow['building_id']] = [
                'average_rating' => (float) $reviewRow['average_rating'],
                'review_count' => (int) $reviewRow['review_count'],
            ];
        }
        foreach ($searchListings as &$listing) {
            $listingId = (int) $listing['id'];
            $listing['view_count'] = $viewCounts[$listingId] ?? 0;
            if (isset($reviewStats[$listingId])) {
                $listing['average_rating'] = $reviewStats[$listingId]['average_rating'];
                $listing['review_count'] = $reviewStats[$listingId]['review_count'];
            }
        }
        unset($listing);
    }
    $favoriteListings = $searchListings;
    usort(
        $favoriteListings,
        static fn(array $first, array $second): int =>
            $second['view_count'] <=> $first['view_count']
            ?: $second['available_count'] <=> $first['available_count']
            ?: strcmp($first['name'], $second['name'])
    );
    $favoriteListings = array_slice($favoriteListings, 0, 8);
    $reviewedListings = array_values(array_filter(
        $searchListings,
        static fn(array $listing): bool => $listing['review_count'] > 0
    ));
    usort(
        $reviewedListings,
        static fn(array $first, array $second): int =>
            $second['average_rating'] <=> $first['average_rating']
            ?: $second['review_count'] <=> $first['review_count']
            ?: strcmp($first['name'], $second['name'])
    );
    $reviewedListings = array_slice($reviewedListings, 0, 2);
    $allListings = $searchListings;
    usort($allListings, static fn(array $first, array $second): int => strcmp($first['name'], $second['name']));
    $roomCount = count($availableRooms);
    $mapBuildings = [];
    foreach ($availableRooms as $room) {
        if ($room['building_latitude'] === null || $room['building_longitude'] === null) {
            continue;
        }
        $buildingId = (int) $room['building_id'];
        if (!isset($mapBuildings[$buildingId])) {
            $mapBuildings[$buildingId] = [
                'name' => $room['building_name'],
                'address' => $room['building_address'],
                'latitude' => (float) $room['building_latitude'],
                'longitude' => (float) $room['building_longitude'],
                'rooms' => [],
            ];
        }
        $mapBuildings[$buildingId]['rooms'][] = [
            'id' => (int) $room['id'],
            'number' => $room['room_number'],
            'type' => $room['room_type'],
            'rate' => (float) $room['monthly_rate'],
        ];
    }
    $mapBuildings = array_values($mapBuildings);
    $reviewReplyColumn = $pdo->query(
        "SHOW COLUMNS FROM property_reviews LIKE 'operator_reply'"
    )->fetch();
    $reviewReplyFields = $reviewReplyColumn
        ? 'pr.operator_reply,pr.operator_replied_at'
        : 'NULL AS operator_reply,NULL AS operator_replied_at';
    $homeReviews = rows(
        $pdo,
        "SELECT pr.building_id,pr.rating,pr.review_text,pr.created_at,{$reviewReplyFields},
                b.name AS building_name,u.name AS reviewer_name
         FROM property_reviews pr
         JOIN kos_buildings b ON b.id=pr.building_id
         JOIN tenants t ON t.id=pr.tenant_id
         LEFT JOIN users u ON u.id=t.user_id
         WHERE pr.status='published' AND b.status='active'
         ORDER BY pr.created_at DESC,pr.id DESC LIMIT 6"
    );
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#fff9df">
        <meta name="description" content="Temukan kos dan kamar nyaman di Araweb. Bandingkan tipe kamar, fasilitas, lokasi, dan harga terbaru.">
        <title>Araweb — Hunian nyaman, hati tenang</title>
        <link rel="stylesheet" href="style.css?v=20261012-map-after-rooms">
        <?php if ($mapBuildings): ?><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""><?php endif; ?>
    </head>
    <body class="public-page">
        <header class="public-header">
            <nav class="public-nav" aria-label="Navigasi utama">
                <a class="tiara-brand araweb-brand" href="index.php" aria-label="Araweb, beranda">
                    <span class="tiara-brand-icon">A</span>
                    <span>ara<span>web</span><small>HUNIAN NYAMAN</small></span>
                </a>
                <form class="public-search" action="index.php" method="get">
                    <input type="hidden" name="page" value="home">
                    <input name="q" value="<?= e($searchTerm) ?>" placeholder="Cari nama kos atau alamat" aria-label="Cari nama kos atau alamat">
                    <button class="button primary" type="submit">Cari</button>
                </form>
                <div class="public-nav-links">
                    <a class="nav-current" href="index.php">Beranda</a>
                    <a href="#kamar">Pilihan kos</a>
                    <a href="#peta">Peta lokasi</a>
                    <a href="#kontak">Kontak</a>
                    <?php if (!$user): ?><a href="index.php?page=register&amp;role=operator">Daftarkan kos</a><?php endif; ?>
                </div>
                <?php if ($user): ?><a class="public-nav-cta" href="index.php?page=<?= $user['role'] === 'seeker' ? 'my_bookings' : ($user['role'] === 'admin' ? 'platform_dashboard' : 'dashboard') ?>"><?= $user['role'] === 'seeker' ? 'Booking saya' : ($user['role'] === 'admin' ? 'Panel admin' : 'Panel pemilik kos') ?><span aria-hidden="true">↗</span></a><?php else: ?><a class="public-nav-cta" href="index.php?page=login">Masuk / Daftar<span aria-hidden="true">↗</span></a><?php endif; ?>
                <button class="public-menu-toggle" type="button" aria-label="Buka navigasi" aria-expanded="false">☰</button>
            </nav>
        </header>
        <main>
            <section class="tiara-hero" id="beranda">
                <div class="hero-inner">
                    <div class="hero-copy">
                        <div class="hero-kicker"><span></span> TEMPAT PULANG YANG TERASA HANGAT</div>
                        <h1><?= $searchTerm ? 'Kos di sekitar <span>' . e($searchTerm) . '.</span>' : 'Temukan nyamanmu, <span>mulai dari sini.</span>' ?></h1>
                        <p class="hero-description">Hunian bersih, suasana tenang, dan fasilitas yang bikin keseharian terasa lebih mudah. Temukan kamar yang pas untuk langkahmu berikutnya.</p>
                        <div class="hero-actions">
                            <a class="hero-button" href="#kamar">Jelajahi kamar <span aria-hidden="true">↓</span></a>
                            <span class="hero-note"><span class="hero-note-icon">✦</span> Nyaman untuk ditinggali</span>
                        </div>
                        <div class="hero-proof"><div class="proof-stars">★★★★★</div><span>Ruang nyaman untuk cerita barumu</span></div>
                    </div>
                    <div class="hero-showcase">
                        <div class="hero-art" aria-label="Ilustrasi rumah Araweb">
                            <div class="sun-disc"></div><div class="hero-plant plant-one">✳</div><div class="hero-plant plant-two">✳</div>
                            <div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
                            <div class="house-art"><div class="house-roof"></div><div class="house-top"><div class="house-window window-left"><i></i></div><div class="house-window window-right"><i></i></div></div><div class="house-awning"></div><div class="house-bottom"><div class="house-door"><i></i></div><div class="house-window window-bottom"><i></i></div></div><div class="house-sign">ARAWEB</div></div>
                            <div class="art-leaf leaf-left">✦</div><div class="art-leaf leaf-right">✦</div>
                            <div class="hero-room-tag"><span class="room-tag-icon">⌂</span><span><strong><?= $roomCount ?> kamar tersedia</strong><small>Siap jadi ruang ceritamu</small></span><b>↗</b></div>
                        </div>
                    </div>
                </div>
                <div class="hero-bottom-wave"></div>
            </section>
            <?php if ($accountPanel): ?>
                <section class="user-account-monitor" aria-labelledby="user-account-monitor-title">
                    <div class="user-account-monitor-heading"><div><span class="section-eyebrow"><span></span> RINGKASAN AKUN</span><h2 id="user-account-monitor-title">Pantau akunmu</h2></div><a class="location-directions-link" href="index.php?page=user_reports">Buka laporan lengkap ↗</a></div>
                    <div class="user-account-monitor-grid">
                        <article><span>Total dibayar</span><strong><?= e(money($accountPanel['paid_total'])) ?></strong></article>
                        <article><span>Sisa tagihan</span><strong><?= e(money($accountPanel['outstanding'])) ?></strong></article>
                        <article><span>Pembayaran menunggu</span><strong><?= (int) $accountPanel['pending_payments'] ?></strong></article>
                        <article><span>Masa sewa aktif</span><strong><?= (int) $accountPanel['active_rentals'] ?></strong></article>
                    </div>
                </section>
            <?php endif; ?>
            <?php if ($savedSearches): ?><section class="search-history-strip"><strong>Pencarian terakhirmu</strong><div><?php foreach ($savedSearches as $savedSearch): ?><a href="index.php?page=home&amp;q=<?= rawurlencode($savedSearch['search_query']) ?>"><?= e($savedSearch['search_query']) ?></a><?php endforeach; ?></div></section><?php endif; ?>
            <section class="public-rooms-section" id="kamar">
                <div class="rooms-section-heading">
                    <div><div class="section-eyebrow"><span></span> <?= $searchTerm !== '' ? 'HASIL PENCARIAN' : 'PILIHAN HUNIAN' ?></div><h2><?= $searchTerm !== '' ? 'Kos yang cocok,' : 'Temukan kos favorit,' ?><br><span><?= $searchTerm !== '' ? 'untuk pencarianmu.' : 'untuk cerita barumu.' ?></span></h2></div>
                    <p><?= $searchTerm !== '' ? 'Ditemukan ' . count($searchListings) . ' lokasi kos dengan ' . $roomCount . ' kamar tersedia untuk “' . e($searchTerm) . '”.' : 'Jelajahi kos favorit, rekomendasi dengan ulasan terbaik, dan seluruh daftar kos yang tersedia di Araweb.' ?></p>
                </div>
                <?php if ($searchTerm !== ''): ?>
                    <?php if ($searchListings): ?>
                        <div class="property-result-grid">
                            <?php foreach ($searchListings as $listing):
                                $listingTypes = array_values(array_unique(array_column($listing['rooms'], 'room_type')));
                                $listingFacilities = [];
                                foreach ($listing['rooms'] as $listingRoom) {
                                    foreach (preg_split('/[,;\r\n]+/', (string) $listingRoom['facilities'], -1, PREG_SPLIT_NO_EMPTY) as $facility) {
                                        $listingFacilities[] = trim($facility);
                                    }
                                }
                                $listingFacilities = array_slice(array_values(array_unique(array_filter($listingFacilities))), 0, 4);
                            ?>
                                <article class="property-result-card">
                                    <a class="property-result-visual" href="index.php?page=property&amp;id=<?= (int) $listing['id'] ?>">
                                        <?php if ($listing['cover_photo']): ?><img src="<?= e($listing['cover_photo']) ?>" alt="Foto kos <?= e($listing['name']) ?>" loading="lazy"><?php else: ?><span class="listing-house-art" aria-hidden="true">⌂</span><small>Foto kos dapat ditambahkan pemilik</small><?php endif; ?>
                                        <span class="availability-pill"><i></i> <?= (int) $listing['available_count'] ?> kamar tersedia</span>
                                    </a>
                                    <div class="property-result-body">
                                        <div class="property-result-heading"><div><span class="section-eyebrow">KOS DI SEKITARMU</span><h3><?= e($listing['name']) ?></h3></div><span class="listing-type-count"><?= count($listingTypes) ?> tipe</span></div>
                                        <p class="property-result-address">⌖ <?= e($listing['address']) ?></p>
                                        <div class="listing-type-chips"><?php foreach ($listingTypes as $listingType): ?><span><?= e($listingType) ?></span><?php endforeach; ?></div>
                                        <?php if ($listingFacilities): ?><div class="listing-facility-chips"><?php foreach ($listingFacilities as $facility): ?><span><?= e($facility) ?></span><?php endforeach; ?></div><?php endif; ?>
                                        <div class="property-result-footer"><div><small>Mulai dari</small><strong><?= e(money($listing['minimum_rate'])) ?><small> / bulan</small></strong></div><a class="room-cta" href="index.php?page=property&amp;id=<?= (int) $listing['id'] ?>">Lihat kos <span>↗</span></a></div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="rooms-empty"><span>⌕</span><h3>Belum ada kos yang cocok</h3><p>Coba nama kos atau lokasi lain. Pastikan juga ejaan pencarian sudah benar.</p></div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($searchListings): ?>
                        <?php
                        $propertyRows = [
                            ['id' => 'favorite-kost', 'eyebrow' => 'KOS FAVORIT', 'title' => 'Paling banyak dilihat', 'listings' => $favoriteListings],
                            ['id' => 'top-rated-kost', 'eyebrow' => 'PILIHAN PENGHUNI', 'title' => '2 kos dengan ulasan terbaik', 'listings' => $reviewedListings],
                            ['id' => 'all-kost', 'eyebrow' => 'JELAJAHI ARAWEB', 'title' => 'Daftar kos', 'listings' => $allListings],
                        ];
                        foreach ($propertyRows as $propertyRow):
                        ?>
                            <section class="property-carousel-section" aria-labelledby="<?= e($propertyRow['id']) ?>-heading">
                                <div class="property-carousel-heading">
                                    <div><div class="section-eyebrow"><span></span> <?= e($propertyRow['eyebrow']) ?></div><h3 id="<?= e($propertyRow['id']) ?>-heading"><?= e($propertyRow['title']) ?></h3></div>
                                    <?php if ($propertyRow['listings']): ?><div class="property-carousel-controls">
                                        <button type="button" class="property-carousel-arrow" data-carousel-target="<?= e($propertyRow['id']) ?>" data-carousel-direction="left" aria-label="Geser ke kiri" disabled>‹</button>
                                        <button type="button" class="property-carousel-arrow" data-carousel-target="<?= e($propertyRow['id']) ?>" data-carousel-direction="right" aria-label="Geser ke kanan">›</button>
                                    </div><?php endif; ?>
                                </div>
                                <?php if ($propertyRow['listings']): ?><div class="property-carousel-track" id="<?= e($propertyRow['id']) ?>" tabindex="0" aria-label="<?= e($propertyRow['title']) ?>">
                                    <?php foreach ($propertyRow['listings'] as $listing): ?>
                                        <a class="property-carousel-card" href="index.php?page=property&amp;id=<?= (int) $listing['id'] ?>">
                                            <div class="property-carousel-visual">
                                                <?php if ($listing['cover_photo']): ?><img src="<?= e($listing['cover_photo']) ?>" alt="Foto kos <?= e($listing['name']) ?>" loading="lazy"><?php else: ?><span class="listing-house-art" aria-hidden="true">⌂</span><small>Foto kos dapat ditambahkan pemilik</small><?php endif; ?>
                                                <span class="availability-pill"><i></i> <?= (int) $listing['available_count'] ?> kamar tersedia</span>
                                                <?php if ($propertyRow['id'] === 'favorite-kost'): ?><span class="property-carousel-badge">♥ Favorit</span><?php endif; ?>
                                            </div>
                                            <div class="property-carousel-card-body">
                                                <span class="property-carousel-address">⌖ <?= e($listing['address']) ?></span>
                                                <h4><?= e($listing['name']) ?></h4>
                                                <?php if ($listing['average_rating'] !== null): ?><p class="property-carousel-rating">★ <?= number_format($listing['average_rating'], 1) ?><span>(<?= (int) $listing['review_count'] ?> ulasan)</span></p><?php elseif ($propertyRow['id'] === 'favorite-kost'): ?><p class="property-carousel-rating"><span><?= (int) $listing['view_count'] ?> kali dilihat</span></p><?php else: ?><p class="property-carousel-rating"><span><?= (int) $listing['available_count'] ?> kamar tersedia</span></p><?php endif; ?>
                                                <div class="property-carousel-price"><span>Mulai dari</span><strong><?= e(money($listing['minimum_rate'])) ?><small> / bulan</small></strong></div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div><?php else: ?><p class="property-carousel-empty">Belum ada ulasan kos yang dipublikasikan.</p><?php endif; ?>
                            </section>
                        <?php endforeach; ?>
                        <section class="property-carousel-section" aria-labelledby="all-rooms-heading">
                            <div class="property-carousel-heading">
                                <div><div class="section-eyebrow"><span></span> JELAJAHI KAMAR</div><h3 id="all-rooms-heading">Daftar kamar</h3></div>
                                <?php if ($roomListings): ?><div class="property-carousel-controls">
                                    <button type="button" class="property-carousel-arrow" data-carousel-target="all-rooms" data-carousel-direction="left" aria-label="Geser ke kiri" disabled>‹</button>
                                    <button type="button" class="property-carousel-arrow" data-carousel-target="all-rooms" data-carousel-direction="right" aria-label="Geser ke kanan">›</button>
                                </div><?php endif; ?>
                            </div>
                            <?php if ($roomListings): ?>
                                <div class="property-carousel-track" id="all-rooms" tabindex="0" aria-label="Daftar kamar">
                                    <?php foreach ($roomListings as $room): ?>
                                        <a class="property-carousel-card" href="index.php?page=room&amp;id=<?= (int) $room['id'] ?>">
                                            <div class="property-carousel-visual">
                                                <?php if ($room['cover_photo']): ?><img src="<?= e($room['cover_photo']) ?>" alt="Foto <?= e($room['room_type'] ?: 'kamar ' . $room['room_number']) ?>" loading="lazy"><?php else: ?><span class="listing-house-art" aria-hidden="true">⌂</span><small>Foto kamar dapat ditambahkan pengelola</small><?php endif; ?>
                                                <span class="availability-pill <?= $room['is_available'] ? '' : 'unavailable' ?>"><i></i> <?= $room['is_available'] ? 'Tersedia' : 'Tidak tersedia' ?></span>
                                            </div>
                                            <div class="property-carousel-card-body">
                                                <span class="property-carousel-address">⌖ <?= e($room['building_name']) ?> · <?= e($room['building_address']) ?></span>
                                                <h4><?= e(($room['room_type'] ?: 'Kamar') . ' · ' . $room['room_number']) ?></h4>
                                                <p class="property-carousel-rating"><span><?= e($room['floor'] ? 'Lantai ' . $room['floor'] : 'Detail kamar dan fasilitas') ?></span></p>
                                                <div class="property-carousel-price"><span>Harga sewa</span><strong><?= e(money($room['monthly_rate'])) ?><small> / bulan</small></strong></div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="property-carousel-empty">Belum ada kamar terdaftar.</p>
                            <?php endif; ?>
                        </section>
                    <?php else: ?>
                        <div class="rooms-empty"><span>⌂</span><h3>Belum ada kos terdaftar</h3><p>Kos yang sudah dipublikasikan akan tampil di sini.</p></div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
            <?php if ($homeReviews): ?>
                <section class="home-reviews-section" aria-labelledby="home-reviews-heading">
                    <div class="rooms-section-heading">
                        <div><div class="section-eyebrow"><span></span> CERITA PENGHUNI</div><h2 id="home-reviews-heading">Ulasan dan <span>balasan pengelola.</span></h2></div>
                        <p>Komentar penghuni dan tanggapan pengelola, langsung menuju kos yang diulas.</p>
                    </div>
                    <div class="home-reviews-grid">
                        <?php foreach ($homeReviews as $review): ?>
                            <article class="home-review-card">
                                <div><strong><?= e($review['reviewer_name'] ?: 'Penghuni') ?></strong><span><?= str_repeat('★', (int) $review['rating']) ?></span></div>
                                <small><?= e(date_id($review['created_at'])) ?> · <?= e($review['building_name']) ?></small>
                                <p><?= nl2br(e($review['review_text'])) ?></p>
                                <?php if ($review['operator_reply']): ?><div class="home-review-reply"><strong>Balasan pengelola</strong><p><?= nl2br(e($review['operator_reply'])) ?></p></div><?php endif; ?>
                                <a class="text-link" href="index.php?page=property&amp;id=<?= (int) $review['building_id'] ?>#review">Lihat ulasan di halaman kos ↗</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            <section class="location-overview" id="peta" aria-labelledby="location-overview-heading">
                <div class="location-overview-grid">
                    <div class="location-info-card">
                        <div class="section-eyebrow"><span></span> TEMUKAN LOKASI</div>
                        <h2 id="location-overview-heading">Cari kos yang dekat dengan tujuanmu.</h2>
                        <p>Lihat lokasi kos dan kamar yang tersedia di sekitar Gambesi. Pilih penanda pada peta untuk melihat tipe kamar dan harga sewanya.</p>
                        <div class="location-info-address">
                            <span aria-hidden="true">⌖</span>
                            <div><strong>Alamat</strong><span>Maps Gambesi Tiara Kost,<br>Lapangan Pramuka Gambesi</span></div>
                        </div>
                        <a class="location-directions-link" href="https://www.google.com/maps/search/?api=1&amp;query=Maps%20Gambesi%20Tiara%20Kost%2C%20lapangan%20pramuka%20gambesi" target="_blank" rel="noopener noreferrer">Buka petunjuk lokasi <span aria-hidden="true">↗</span></a>
                    </div>
                    <div class="location-map-card">
                        <div class="location-map-heading">
                            <div><span class="map-status-dot"></span><strong><?= count($mapBuildings) ?> lokasi kos di peta</strong></div>
                            <small><?= $roomCount ?> kamar tersedia</small>
                        </div>
                        <?php if ($mapBuildings): ?>
                            <div id="tiara-kos-map" class="tiara-kos-map" role="application" aria-label="Peta lokasi kos di Araweb"></div>
                            <script type="application/json" id="tiara-kos-map-data"><?= json_encode($mapBuildings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
                        <?php else: ?><div class="map-empty-state"><span>⌖</span><strong>Lokasi belum tersedia di peta</strong><p>Pengelola perlu mengisi koordinat gedung dan menambahkan kamar kosong yang aktif.</p></div><?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
        <footer class="public-footer" id="kontak">
            <div class="public-footer-inner">
                <div class="public-footer-brand">
                    <a class="tiara-brand araweb-brand" href="#beranda" aria-label="Araweb, kembali ke atas">
                        <span class="tiara-brand-icon">A</span>
                        <span>ara<span>web</span><small>HUNIAN NYAMAN</small></span>
                    </a>
                    <p>Temukan ruang nyaman untuk cerita barumu.</p>
                </div>
                <nav class="public-footer-links" aria-label="Tautan cepat">
                    <h2>Tautan cepat</h2>
                    <a href="#beranda">› <span>Beranda</span></a>
                    <a href="#kamar">› <span>Daftar kos &amp; kamar</span></a>
                    <a href="#peta">› <span>Peta lokasi</span></a>
                    <a href="#kontak">› <span>Kontak</span></a>
                </nav>
                <section class="public-footer-contact" aria-labelledby="public-footer-contact-heading">
                    <h2 id="public-footer-contact-heading">Kontak</h2>
                    <a href="https://www.google.com/maps/search/?api=1&amp;query=Maps%20Gambesi%20Tiara%20Kost%2C%20lapangan%20pramuka%20gambesi" target="_blank" rel="noopener noreferrer">
                        <span aria-hidden="true">⌖</span><span>Maps Gambesi Tiara Kost,<br>Lapangan Pramuka Gambesi</span>
                    </a>
                    <a href="tel:081342122105"><span aria-hidden="true">☎</span><span>081342122105</span></a>
                    <a href="mailto:alstrazhila@gmail.com"><span aria-hidden="true">✉</span><span>alstrazhila@gmail.com</span></a>
                </section>
                <div class="public-footer-bottom">
                    <span>© <?= date('Y') ?> Araweb. Hak cipta dilindungi.</span>
                    <a href="#beranda" aria-label="Kembali ke atas">↑</a>
                </div>
            </div>
        </footer>
        <?php if ($mapBuildings): ?>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            const mapData = JSON.parse(document.getElementById('tiara-kos-map-data').textContent);
            const map = L.map('tiara-kos-map', {scrollWheelZoom: false});
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            const mapBounds = [];
            mapData.forEach((building) => {
                const marker = L.marker([building.latitude, building.longitude]).addTo(map);
                const popup = document.createElement('div');
                popup.className = 'map-popup';
                const name = document.createElement('strong');
                name.textContent = building.name;
                popup.append(name);
                const address = document.createElement('p');
                address.textContent = building.address;
                popup.append(address);
                const roomList = document.createElement('div');
                roomList.className = 'map-popup-rooms';
                building.rooms.forEach((room) => {
                    const item = document.createElement('div');
                    item.className = 'map-popup-room';
                    const detail = document.createElement('span');
                    detail.textContent = `Kamar ${room.number} · ${room.type}`;
                    const price = document.createElement('strong');
                    price.textContent = new Intl.NumberFormat('id-ID', {
                        style: 'currency', currency: 'IDR', maximumFractionDigits: 0
                    }).format(room.rate) + '/bln';
                    item.append(detail, price);
                    roomList.append(item);
                });
                popup.append(roomList);
                const directions = document.createElement('a');
                directions.href = `https://www.openstreetmap.org/?mlat=${building.latitude}&mlon=${building.longitude}#map=17/${building.latitude}/${building.longitude}`;
                directions.target = '_blank';
                directions.rel = 'noopener noreferrer';
                directions.textContent = 'Buka petunjuk lokasi ↗';
                popup.append(directions);
                marker.bindPopup(popup);
                mapBounds.push([building.latitude, building.longitude]);
            });
            if (mapBounds.length === 1) {
                map.setView(mapBounds[0], 15);
            } else {
                map.fitBounds(mapBounds, {padding: [36, 36], maxZoom: 14});
            }
        </script><?php endif; ?>
        <script src="app.js?v=20261016-interactive-controls"></script>
    </body>
    </html>
    <?php
    exit;
}
