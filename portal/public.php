<?php
function render_public_login_dialog(): void
{
    $facebookConfigured = facebook_oauth_config() !== null;
    ?>
    <dialog class="auth-dialog" id="public-login-dialog" aria-labelledby="public-login-title">
        <button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button>
        <div class="auth-dialog-shell">
            <div class="auth-dialog-heading">
                <h2 id="public-login-title">Masuk</h2>
                <a href="index.php?page=register">Daftar</a>
            </div>
            <form method="post" action="index.php?page=login" class="auth-dialog-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="login">
                <label class="field">
                    <span>Username, email, atau nomor telepon</span>
                    <input type="text" name="login_identifier" required autocomplete="username" placeholder="Masukkan username, email, atau nomor telepon" autofocus>
                </label>
                <label class="field">
                    <span>Kata sandi</span>
                    <div class="password-input-wrap">
                        <input id="public-login-password" type="password" name="password" required autocomplete="current-password" placeholder="Masukkan kata sandi">
                        <button class="password-visibility-toggle" type="button" data-password-toggle="public-login-password" aria-label="Tampilkan kata sandi" aria-pressed="false">
                            <svg class="password-eye-icon password-eye-visible" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="password-eye-icon password-eye-hidden" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                                <path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15.8 15.8 0 0 1-3.1 3.9M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7c1.1 0 2.2-.2 3.1-.6"></path>
                            </svg>
                        </button>
                    </div>
                </label>
                <div class="auth-dialog-forgot"><a href="index.php?page=forgot_password">Lupa kata sandi?</a></div>
                <button class="button primary full" type="submit">Masuk</button>
            </form>
            <?php if ($facebookConfigured): ?>
                <div class="auth-dialog-divider"><span>atau masuk dengan</span></div>
                <a class="button secondary full facebook-login-button auth-dialog-facebook" href="index.php?page=facebook_login">Lanjutkan dengan Facebook</a>
            <?php endif; ?>
            <p class="auth-dialog-operator">Ingin mendaftarkan kos? <a href="index.php?page=register&amp;role=owner">Daftar sebagai Owner kos</a></p>
        </div>
    </dialog>
    <?php
}

if ($page === 'room') {
    $roomId = max(0, (int) ($_GET['id'] ?? 0));
    $room = one(
        $pdo,
        "SELECT rm.*,b.id AS property_id,b.name AS building_name,b.address AS building_address,
                b.description AS building_description,b.gender_policy,b.general_facilities,
                b.house_rules,b.latitude,b.longitude,b.owner_user_id,
                u.name AS owner_name,
                CASE WHEN u.hide_phone_public=1 AND :admin_view=0 THEN NULL ELSE u.phone END AS owner_phone,
                CASE WHEN u.hide_profile_photo_public=1 AND :admin_view_photo=0 THEN NULL ELSE u.profile_photo_path END AS owner_photo,
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
         WHERE rm.id=:id AND b.status='active'
           AND (b.owner_user_id IS NULL OR EXISTS (
               SELECT 1 FROM users owner WHERE owner.id=b.owner_user_id
                 AND owner.status='active' AND owner.self_deactivated_at IS NULL
           ))",
        [
            'id' => $roomId,
            'admin_view' => $user && $user['role'] === 'admin' ? 1 : 0,
            'admin_view_photo' => $user && $user['role'] === 'admin' ? 1 : 0,
        ]
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
    $roomTypeGalleryPhotos = rows(
        $pdo,
        'SELECT id,category,photo_path FROM room_type_gallery_photos
         WHERE building_id=:building_id AND room_type=:room_type
           AND (room_id IS NULL OR room_id=:room_id)
         ORDER BY (room_id IS NOT NULL) DESC,id',
        ['building_id' => $room['property_id'], 'room_type' => $room['room_type'], 'room_id' => $roomId]
    );
    $roomGalleryPhotos = [];
    if (!empty($room['profile_photo_path'])) {
        $roomGalleryPhotos[] = [
            'photo_path' => $room['profile_photo_path'],
            'category' => 'room',
        ];
    }
    foreach ($roomPhotos as $photo) {
        if ($photo['category'] === 'room'
            && $photo['photo_path'] !== ($room['profile_photo_path'] ?? null)) {
            $roomGalleryPhotos[] = $photo;
        }
    }
    $roomFacilityPhotos = array_values(array_filter(
        $roomPhotos,
        static fn (array $photo): bool => $photo['category'] === 'facility'
    ));
    $generalFacilityPhotos = [];
    foreach ($roomTypeGalleryPhotos as $galleryPhoto) {
        if ($galleryPhoto['category'] === 'room' && !in_array(
            $galleryPhoto['photo_path'],
            array_column($roomGalleryPhotos, 'photo_path'),
            true
        )) {
            $roomGalleryPhotos[] = $galleryPhoto;
        } elseif ($galleryPhoto['category'] === 'room_facility'
            && !in_array($galleryPhoto['photo_path'], array_column($roomFacilityPhotos, 'photo_path'), true)) {
            $roomFacilityPhotos[] = $galleryPhoto;
        } elseif ($galleryPhoto['category'] === 'general_facility') {
            $generalFacilityPhotos[] = $galleryPhoto;
        }
    }
    $roomAvailable = (bool) $room['is_available'];
    $genderLabel = ['mixed' => 'Campur', 'male' => 'Putra', 'female' => 'Putri'][$room['gender_policy']];
    ?>
    <!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="manifest" href="site.webmanifest">
    <meta name="description" content="<?= e($room['room_type'] ?: 'Kamar ' . $room['room_number']) ?> di <?= e($room['building_name']) ?>. Tarif <?= e(money($room['monthly_rate'])) ?> per bulan.">
    <title><?= e($room['room_type'] ?: 'Kamar ' . $room['room_number']) ?> — <?= e($room['building_name']) ?> | KosKosan</title><link rel="stylesheet" href="style.css?v=koskosan-ui-20261023-2">
    <?php if ($room['latitude'] !== null && $room['longitude'] !== null): ?><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""><?php endif; ?>
    </head>
    <body class="public-page property-page"><header class="public-header" id="top"><nav class="public-nav">
        <a class="tiara-brand koskosan-brand" href="index.php" aria-label="KosKosan, beranda"><span class="tiara-brand-icon">K</span><span>KosKosan<small>HUNIAN NYAMAN</small></span></a>
        <?php if ($user): ?><?= notification_bell_markup($notificationUnreadCount, $pdo) ?><?= message_menu_markup($user['role']) ?><?php endif; ?>
        <?= theme_switcher() ?>
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
                    <div class="property-photo-placeholder koskosan-house-placeholder"><span aria-hidden="true">⌂</span><strong>Kamar <?= e($room['room_number']) ?></strong><small>Foto kamar dapat ditambahkan pengelola</small></div>
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
            <section class="property-section" id="fasilitas-kamar"><h2>Fasilitas kamar</h2><div class="property-feature-list"><?php foreach (array_filter(array_map('trim', preg_split('/[,;\r\n]+/', (string) $room['facilities']))) as $facility): ?><span><?= e($facility) ?></span><?php endforeach; ?><?php if (!$room['facilities']): ?><span>Informasi fasilitas kamar belum ditambahkan.</span><?php endif; ?></div>
                <?php if ($roomFacilityPhotos): ?><div class="room-facility-gallery">
                    <div class="property-carousel-heading"><div><div class="section-eyebrow"><span></span> GALERI <?= e(strtoupper($room['room_type'])) ?></div><h3>Foto fasilitas kamar</h3></div>
                        <?php if (count($roomFacilityPhotos) > 1): ?><div class="property-carousel-controls"><button type="button" class="property-carousel-arrow" data-carousel-target="room-facility-photos" data-carousel-direction="left" aria-label="Geser foto fasilitas kamar ke kiri" disabled>‹</button><button type="button" class="property-carousel-arrow" data-carousel-target="room-facility-photos" data-carousel-direction="right" aria-label="Geser foto fasilitas kamar ke kanan">›</button></div><?php endif; ?>
                    </div>
                    <div class="property-carousel-track room-facility-photo-track" id="room-facility-photos" tabindex="0" aria-label="Foto fasilitas kamar">
                        <?php foreach ($roomFacilityPhotos as $index => $photo): ?><button class="room-facility-photo-card" type="button" data-photo-preview="<?= e($photo['photo_path']) ?>" data-photo-alt="<?= e('Fasilitas kamar ' . $room['room_type']) ?>" aria-label="Lihat foto fasilitas kamar ukuran penuh"><img src="<?= e($photo['photo_path']) ?>" alt="<?= e('Fasilitas kamar ' . $room['room_type'] . ' ' . ($index + 1)) ?>" loading="lazy"></button><?php endforeach; ?>
                    </div>
                </div><?php endif; ?>
            </section>
            <section class="property-section" id="fasilitas-umum"><h2>Fasilitas umum &amp; peraturan</h2><div class="property-feature-list"><?php foreach (array_filter(array_map('trim', preg_split('/[,;\r\n]+/', (string) $room['general_facilities']))) as $facility): ?><span><?= e($facility) ?></span><?php endforeach; ?><?php if (!$room['general_facilities']): ?><span>Informasi fasilitas umum belum ditambahkan.</span><?php endif; ?></div>
                <?php if ($generalFacilityPhotos): ?><div class="room-facility-gallery">
                    <div class="property-carousel-heading"><div><div class="section-eyebrow"><span></span> GALERI <?= e(strtoupper($room['room_type'])) ?></div><h3>Foto fasilitas umum</h3></div>
                        <?php if (count($generalFacilityPhotos) > 1): ?><div class="property-carousel-controls"><button type="button" class="property-carousel-arrow" data-carousel-target="general-facility-photos" data-carousel-direction="left" aria-label="Geser foto fasilitas umum ke kiri" disabled>‹</button><button type="button" class="property-carousel-arrow" data-carousel-target="general-facility-photos" data-carousel-direction="right" aria-label="Geser foto fasilitas umum ke kanan">›</button></div><?php endif; ?>
                    </div>
                    <div class="property-carousel-track room-facility-photo-track" id="general-facility-photos" tabindex="0" aria-label="Foto fasilitas umum">
                        <?php foreach ($generalFacilityPhotos as $index => $photo): ?><button class="room-facility-photo-card" type="button" data-photo-preview="<?= e($photo['photo_path']) ?>" data-photo-alt="<?= e('Fasilitas umum ' . $room['room_type']) ?>" aria-label="Lihat foto fasilitas umum ukuran penuh"><img src="<?= e($photo['photo_path']) ?>" alt="<?= e('Fasilitas umum ' . $room['room_type'] . ' ' . ($index + 1)) ?>" loading="lazy"></button><?php endforeach; ?>
                    </div>
                </div><?php endif; ?>
                <?php if ($room['house_rules']): ?><h3>Peraturan kos</h3><p><?= nl2br(e($room['house_rules'])) ?></p><?php endif; ?></section>
            <section class="property-section"><h2>Informasi kos</h2><p><?= e($room['building_name']) ?></p><p><?= e($room['building_address']) ?></p><a class="text-link" href="index.php?page=property&amp;id=<?= (int) $room['property_id'] ?>">Lihat profil kos dan kamar lainnya ↗</a></section>
            <?php if ($room['owner_name']): ?><section class="property-section"><h2>Pengelola</h2><div class="property-owner-card"><?= avatar_markup(['name' => $room['owner_name'], 'gender' => $room['owner_gender'], 'profile_photo_path' => $room['owner_photo']], 'profile-avatar') ?><div><strong><?= e($room['owner_name']) ?></strong><small>Pengelola kos</small></div><div class="property-owner-actions"><?php if ($user && $user['role'] === 'seeker'): ?><button class="button secondary compact-button" type="button" data-dialog-open="room-chat-dialog">Chat Sistem</button><?php endif; ?><?php if ($room['owner_phone'] && ($whatsappUrl = whatsapp_url((string) $room['owner_phone']))): ?><a class="button secondary compact-button" href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer">WhatsApp ↗</a><?php elseif ($user && $user['role'] === 'seeker'): ?><small>Nomor WhatsApp disembunyikan; gunakan chat sistem.</small><?php endif; ?></div></div></section><?php endif; ?>
        </div><aside class="property-booking-card room-booking-card"><span><?= $roomAvailable ? 'Kamar tersedia' : 'Kamar sedang tidak tersedia' ?></span><strong><?= e(money($room['monthly_rate'])) ?><small> / bulan</small></strong><p>Tarif bulanan kamar <?= e($room['room_number']) ?> di <?= e($room['building_name']) ?>.</p><?php if ($roomAvailable): ?><a class="button primary" href="<?= $user && $user['role'] === 'seeker' ? 'index.php?page=booking_form&id=' . $roomId : 'index.php?page=register&role=seeker&next=booking&id=' . $roomId ?>">Ajukan booking kamar</a><?php else: ?><span class="room-unavailable-note">Booking belum dapat dilakukan untuk kamar ini.</span><?php endif; ?></aside></div>
    </main><footer class="public-footer room-detail-footer">
        <div class="public-footer-inner room-detail-footer-inner">
            <div class="public-footer-brand">
                <a class="tiara-brand koskosan-brand" href="index.php" aria-label="KosKosan, beranda"><span class="tiara-brand-icon">K</span><span>KosKosan<small>HUNIAN NYAMAN</small></span></a>
                <p>Temukan hunian nyaman dan ruang untuk memulai cerita barumu.</p>
            </div>
            <nav class="public-footer-links" aria-label="Jelajahi KosKosan">
                <h2>Jelajahi KosKosan</h2>
                <a href="index.php?page=property&amp;id=<?= (int) $room['property_id'] ?>">› <span><?= e($room['building_name']) ?></span></a>
                <a href="index.php?page=home#kamar">› <span>Lihat kamar lainnya</span></a>
                <a href="#top">↑ <span>Kembali ke atas</span></a>
            </nav>
            <div class="public-footer-bottom">
                <span>© <?= date('Y') ?> KosKosan. Hak cipta dilindungi.</span>
                <a href="#top" aria-label="Kembali ke atas">↑</a>
            </div>
        </div>
    </footer>
    <?php if ($user && $user['role'] === 'seeker'): ?><dialog class="action-dialog" id="room-chat-dialog"><form method="post" class="action-dialog-form"><?= csrf_field() ?><input type="hidden" name="action" value="start_chat_conversation"><input type="hidden" name="building_id" value="<?= (int) $room['property_id'] ?>"><input type="hidden" name="room_id" value="<?= $roomId ?>"><button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button><span class="dialog-eyebrow">CHAT SISTEM</span><h2>Hubungi pengelola</h2><p>Percakapan ini hanya dapat dibaca oleh Anda dan pengelola gedung.</p><label class="field"><span>Pesan</span><textarea name="message" rows="4" maxlength="4000" required>Halo, saya tertarik dengan Kamar <?= e($room['room_number']) ?> di <?= e($room['building_name']) ?>.</textarea></label><div class="dialog-actions"><button class="button secondary" type="button" data-dialog-close>Batal</button><button class="button primary" type="submit">Mulai chat</button></div></form></dialog><?php endif; ?>
    <dialog class="photo-lightbox" id="photo-lightbox" aria-label="Pratinjau foto"><button class="photo-lightbox-close" type="button" data-photo-preview-close aria-label="Tutup foto">×</button><img src="" alt=""></dialog>

    <?php if ($room['latitude'] !== null && $room['longitude'] !== null): ?><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script><script>
        const roomMap = L.map('room-detail-map', {scrollWheelZoom: false, zoomControl: false}).setView([<?= json_encode((float) $room['latitude']) ?>, <?= json_encode((float) $room['longitude']) ?>], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'}).addTo(roomMap);
        L.marker([<?= json_encode((float) $room['latitude']) ?>, <?= json_encode((float) $room['longitude']) ?>]).addTo(roomMap).bindPopup(<?= json_encode($room['building_name'] . ' - Kamar ' . $room['room_number'], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>);
        setTimeout(() => roomMap.invalidateSize(), 100);
        document.getElementById('room-detail-map').addEventListener('click', () => roomMap.scrollWheelZoom.enable());
    </script><?php endif; ?>
    <dialog class="auth-dialog" id="public-login-dialog">
        <button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button>
        <div class="auth-dialog-shell">
            <div class="auth-dialog-brand">
                <span class="brand-mark">K</span>
                <div>
                    <strong>KosKosan</strong>
                    <small>Manajemen kos lebih mudah</small>
                </div>
            </div>
            <h2>Masuk</h2>
            <form method="post" action="index.php?page=login" class="auth-dialog-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="login">
                <label class="field">
                    <span>Username, email, atau nomor telepon</span>
                    <input type="text" name="login_identifier" required autocomplete="username" placeholder="Username, email, atau nomor telepon">
                </label>
                <label class="field">
                    <span>Kata sandi</span>
                    <div class="password-input-wrap">
                        <input id="public-login-password" type="password" name="password" required autocomplete="current-password" placeholder="Kata sandi">
                        <button class="password-visibility-toggle" type="button" data-password-toggle="public-login-password" aria-label="Tampilkan kata sandi" aria-pressed="false">
                            <svg class="password-eye-icon password-eye-visible" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="password-eye-icon password-eye-hidden" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                                <path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15.8 15.8 0 0 1-3.1 3.9M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7c1.1 0 2.2-.2 3.1-.6"></path>
                            </svg>
                        </button>
                    </div>
                </label>
                <button class="button primary full" type="submit">Masuk</button>
            </form>
            <div class="auth-dialog-meta">
                <a href="index.php?page=forgot_password">Lupa kata sandi?</a>
                <span>Belum punya akun? <a href="index.php?page=register">Daftar</a></span>
            </div>
        </div>
    </dialog>
    <?php require __DIR__ . '/../chat/widget.php'; ?>
    <script src="app.js?v=koskosan-ui-20261023-2"></script></body></html>
    <?php
    exit;
}

if ($page === 'property') {
    $buildingId = max(0, (int) ($_GET['id'] ?? 0));
    $property = one(
        $pdo,
        "SELECT b.*,u.name AS owner_name,
                CASE WHEN u.hide_phone_public=1 AND :admin_view=0 THEN NULL ELSE u.phone END AS owner_phone,
                CASE WHEN u.hide_profile_photo_public=1 AND :admin_view_photo=0 THEN NULL ELSE u.profile_photo_path END AS owner_photo,
                u.gender AS owner_gender,u.avatar_key AS owner_avatar
         FROM kos_buildings b LEFT JOIN users u ON u.id=b.owner_user_id
         WHERE b.id=:id AND b.status='active'
           AND (b.owner_user_id IS NULL OR EXISTS (
               SELECT 1 FROM users owner WHERE owner.id=b.owner_user_id
                 AND owner.status='active' AND owner.self_deactivated_at IS NULL
           ))",
        [
            'id' => $buildingId,
            'admin_view' => $user && $user['role'] === 'admin' ? 1 : 0,
            'admin_view_photo' => $user && $user['role'] === 'admin' ? 1 : 0,
        ]
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
    $missingPropertyPhotos = [];
    $isPropertyPhotoAvailable = static function (string $photoPath) use (&$missingPropertyPhotos): bool {
        if (preg_match('~^https?://~i', $photoPath) === 1 || !str_starts_with($photoPath, 'uploads/')) {
            return true;
        }
        $filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $photoPath);
        if (is_file($filePath)) {
            return true;
        }
        if (!isset($missingPropertyPhotos[$photoPath])) {
            error_log('Property gallery references a missing uploaded photo: ' . $photoPath);
            $missingPropertyPhotos[$photoPath] = true;
        }
        return false;
    };
    $propertyPhotos = rows(
        $pdo,
        'SELECT id,photo_path FROM property_photos WHERE building_id=:id ORDER BY display_order,id',
        ['id' => $buildingId]
    );
    $galleryBuildingPhotos = rows(
        $pdo,
        "SELECT photo_path FROM room_type_gallery_photos
         WHERE building_id=:id AND category='building' ORDER BY id",
        ['id' => $buildingId]
    );
    $propertyGalleryPhotos = [];
    if (!empty($property['profile_photo_path'])
        && $isPropertyPhotoAvailable($property['profile_photo_path'])) {
        $propertyGalleryPhotos[] = $property['profile_photo_path'];
    }
    foreach ($propertyPhotos as $photo) {
        if ($isPropertyPhotoAvailable($photo['photo_path'])
            && !in_array($photo['photo_path'], $propertyGalleryPhotos, true)) {
            $propertyGalleryPhotos[] = $photo['photo_path'];
        }
    }
    foreach ($galleryBuildingPhotos as $photo) {
        if ($isPropertyPhotoAvailable($photo['photo_path'])
            && !in_array($photo['photo_path'], $propertyGalleryPhotos, true)) {
            $propertyGalleryPhotos[] = $photo['photo_path'];
        }
    }
    $propertyRooms = rows(
        $pdo,
        "SELECT rm.*,
                CASE WHEN rm.status='available' AND NOT EXISTS (
                    SELECT 1 FROM rentals r WHERE r.room_id=rm.id AND (
                        (r.status='active' AND r.start_date<=CURDATE()
                            AND (r.end_date IS NULL OR r.end_date>=CURDATE()))
                        OR (r.status='upcoming' AND (r.end_date IS NULL OR r.end_date>=CURDATE())
                            AND (r.booking_expires_at IS NULL OR r.booking_expires_at>NOW()
                                OR EXISTS (SELECT 1 FROM invoices i JOIN payments p ON p.invoice_id=i.id
                                    WHERE i.rental_id=r.id AND p.status='pending')))
                    )
                ) THEN 1 ELSE 0 END AS is_available
         FROM rooms rm WHERE rm.building_id=:id
         ORDER BY rm.monthly_rate,rm.room_number",
        ['id' => $buildingId]
    );
    $roomPhotosById = [];
    if ($propertyRooms) {
        $roomIds = array_map(static fn(array $room): int => (int) $room['id'], $propertyRooms);
        $roomPlaceholders = implode(',', array_fill(0, count($roomIds), '?'));
        $roomPhotoStatement = $pdo->prepare(
            "SELECT room_id,category,photo_path FROM room_photos
             WHERE room_id IN ({$roomPlaceholders}) ORDER BY id"
        );
        $roomPhotoStatement->execute($roomIds);
        foreach ($roomPhotoStatement->fetchAll() as $photo) {
            if ($isPropertyPhotoAvailable($photo['photo_path'])) {
                $roomPhotosById[(int) $photo['room_id']][] = $photo;
            }
        }
    }
    $roomTypeGalleryPhotos = rows(
        $pdo,
        "SELECT room_id,room_type,category,photo_path FROM room_type_gallery_photos
         WHERE building_id=:id AND category IN ('room','room_facility','general_facility')
         ORDER BY id",
        ['id' => $buildingId]
    );
    $roomGalleryPhotosByType = [];
    $roomGalleryPhotosByRoom = [];
    $roomFacilityPhotosByType = [];
    $generalFacilityPhotos = [];
    foreach ($roomTypeGalleryPhotos as $photo) {
        if (!$isPropertyPhotoAvailable($photo['photo_path'])) {
            continue;
        }
        if ($photo['category'] === 'room') {
            if ($photo['room_id'] !== null) {
                $roomGalleryPhotosByRoom[(int) $photo['room_id']][] = $photo['photo_path'];
            } else {
                $roomGalleryPhotosByType[$photo['room_type']][] = $photo['photo_path'];
            }
        } elseif ($photo['category'] === 'room_facility') {
            $roomFacilityPhotosByType[$photo['room_type']][] = $photo['photo_path'];
        } else {
            $generalFacilityPhotos[] = $photo['photo_path'];
        }
    }
    $propertyRoomGroups = [];
    foreach ($propertyRooms as $room) {
        $roomType = $room['room_type'];
        $roomId = (int) $room['id'];
        $room['cover_photo'] = !empty($room['profile_photo_path'])
            && $isPropertyPhotoAvailable($room['profile_photo_path'])
            ? $room['profile_photo_path'] : null;
        foreach ($roomPhotosById[$roomId] ?? [] as $photo) {
            if ($photo['category'] === 'room' && $room['cover_photo'] === null) {
                $room['cover_photo'] = $photo['photo_path'];
            } elseif ($photo['category'] === 'facility') {
                $roomFacilityPhotosByType[$roomType][] = $photo['photo_path'];
            }
        }
        if ($room['cover_photo'] === null && !empty($roomGalleryPhotosByRoom[$roomId])) {
            $room['cover_photo'] = $roomGalleryPhotosByRoom[$roomId][0];
        }
        if ($room['cover_photo'] === null && !empty($roomGalleryPhotosByType[$roomType])) {
            $room['cover_photo'] = $roomGalleryPhotosByType[$roomType][0];
        }
        $propertyRoomGroups[$roomType][] = $room;
    }
    foreach ($roomFacilityPhotosByType as $roomType => $photoPaths) {
        $roomFacilityPhotosByType[$roomType] = array_values(array_unique($photoPaths));
    }
    $generalFacilityPhotos = array_values(array_unique($generalFacilityPhotos));
    $roomTypeOrder = static function (string $roomType): int {
        $normalized = strtolower($roomType);
        if (str_contains($normalized, 'stand')) {
            return 0;
        }
        if (str_contains($normalized, 'premium')) {
            return 1;
        }
        if (str_contains($normalized, 'eksekutif')
            || str_contains($normalized, 'eksklusif')
            || str_contains($normalized, 'executive')) {
            return 2;
        }
        return 3;
    };
    uksort($propertyRoomGroups, static fn(string $first, string $second): int =>
        $roomTypeOrder($first) <=> $roomTypeOrder($second) ?: strnatcasecmp($first, $second)
    );
    $availablePropertyRooms = array_values(array_filter(
        $propertyRooms,
        static fn(array $room): bool => (bool) $room['is_available']
    ));
    $reviewReplyColumn = $pdo->query(
        "SHOW COLUMNS FROM property_reviews LIKE 'operator_reply'"
    )->fetch();
    $reviewReplyFields = $reviewReplyColumn
        ? 'pr.operator_reply,pr.operator_replied_at'
        : 'NULL AS operator_reply,NULL AS operator_replied_at';
    $propertyReviews = rows(
        $pdo,
        "SELECT pr.rating,pr.review_text,pr.created_at,{$reviewReplyFields},
                COALESCE(reviewer.name,u.name) AS reviewer_name
         FROM property_reviews pr LEFT JOIN tenants t ON t.id=pr.tenant_id
         LEFT JOIN users u ON u.id=t.user_id
         LEFT JOIN users reviewer ON reviewer.id=pr.reviewer_user_id
         WHERE pr.building_id=:id AND pr.status='published'
         ORDER BY pr.created_at DESC LIMIT 30",
        ['id' => $buildingId]
    );
    $averageRating = $propertyReviews
        ? array_sum(array_column($propertyReviews, 'rating')) / count($propertyReviews) : null;
    $canReview = $user && $user['role'] === 'seeker';
    $genderLabel = ['mixed' => 'Campur', 'male' => 'Putra', 'female' => 'Putri'][$property['gender_policy']];
    $canManageProperty = $user && can_manage_building($pdo, $user, $buildingId);
    $propertySaved = $user && $user['role'] === 'seeker' && (bool) one(
        $pdo,
        "SELECT id FROM property_user_actions
         WHERE building_id=:building_id AND user_id=:user_id AND action_type='saved' LIMIT 1",
        ['building_id' => $buildingId, 'user_id' => $user['id']]
    );
    ?>
    <!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="manifest" href="site.webmanifest">
    <meta name="description" content="<?= e($property['name']) ?> — foto, kamar, fasilitas, lokasi, ulasan dan informasi pemilik kos.">
    <title><?= e($property['name']) ?> — KosKosan</title><link rel="stylesheet" href="style.css?v=koskosan-ui-20261023-2">
    <?php if ($property['latitude'] !== null && $property['longitude'] !== null): ?><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""><?php endif; ?>
    </head><body class="public-page property-page">
    <header class="public-header"><nav class="public-nav"><a class="tiara-brand koskosan-brand" href="index.php" aria-label="KosKosan, beranda"><span class="tiara-brand-icon">K</span><span>KosKosan<small>HUNIAN NYAMAN</small></span></a><?php if ($user): ?><?= notification_bell_markup($notificationUnreadCount, $pdo) ?><?= message_menu_markup($user['role']) ?><?php endif; ?><?= theme_switcher() ?><form class="public-search" action="index.php" method="get"><input type="hidden" name="page" value="home"><input name="q" placeholder="Cari nama kos atau alamat" aria-label="Cari nama kos atau alamat"><button class="button primary">Cari</button></form><?php if ($user): ?><a class="public-nav-cta" href="index.php?page=<?= $user['role'] === 'seeker' ? 'profile' : ($user['role'] === 'admin' ? 'platform_dashboard' : 'dashboard') ?>"><?= e($user['name']) ?></a><?php else: ?><button class="public-nav-cta" type="button" data-dialog-open="public-login-dialog">Masuk / Daftar</button><?php endif; ?></nav>
    <nav class="property-tabs"><a href="#foto">Foto properti</a><a href="#fasilitas-kamar">Fasilitas kamar</a><a href="#fasilitas-umum">Fasilitas umum</a><a href="#lokasi">Lokasi</a><a href="#review">Review</a><a href="#pemilik">Pemilik kos</a></nav></header>
    <main class="property-detail-main">
        <a class="text-link" href="index.php?page=home">← Kembali ke pencarian</a>
        <?php if (!$user): ?><div class="property-user-actions"><button type="button" class="button secondary compact-button" data-dialog-open="public-login-dialog">Masuk untuk menyimpan atau melaporkan kos</button></div><?php endif; ?>
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
                    <div class="property-photo-placeholder koskosan-house-placeholder"><span aria-hidden="true">⌂</span><strong><?= e($property['name']) ?></strong><small>Foto profil kos belum ditambahkan</small></div>
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
            <section class="property-intro"><span class="badge active"><?= e($genderLabel) ?></span><span class="verification-badge tier-<?= e($property['verification_tier']) ?>"><?= e($property['verification_status'] === 'verified' ? ucfirst($property['verification_tier']) . ' · Terverifikasi' : 'Basic · Belum terverifikasi') ?></span><h1><?= e($property['name']) ?></h1><p><?= e($property['address']) ?></p><?php if ($averageRating !== null): ?><strong class="property-rating">★ <?= number_format($averageRating,1) ?> <small>(<?= count($propertyReviews) ?> ulasan)</small></strong><?php else: ?><span class="muted">Belum ada review</span><?php endif; ?><?php if ($property['description']): ?><p><?= nl2br(e($property['description'])) ?></p><?php endif; ?><div class="property-user-actions"><?php if ($user && $user['role'] === 'seeker'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_saved_property"><input type="hidden" name="building_id" value="<?= $buildingId ?>"><button class="button secondary compact-button" type="submit"><?= $propertySaved ? '♥ Tersimpan' : '♡ Simpan kos' ?></button></form><button class="button secondary compact-button" type="button" data-share-title="<?= e($property['name']) ?>" data-share-url="<?= e('index.php?page=property&id=' . $buildingId) ?>">Bagikan</button><button class="button secondary compact-button" type="button" data-dialog-open="report-property-dialog">Laporkan</button><?php else: ?><button class="button secondary compact-button" type="button" data-share-title="<?= e($property['name']) ?>" data-share-url="<?= e('index.php?page=property&id=' . $buildingId) ?>">Bagikan</button><?php endif; ?><?php if ($user && $user['role'] === 'owner' && $canManageProperty && $property['verification_status'] !== 'verified'): ?><button class="button secondary compact-button" type="button" data-dialog-open="property-verification-dialog">Ajukan verifikasi</button><?php endif; ?></div></section>
            <section class="property-section property-room-types" id="fasilitas-kamar">
                <h2>Kamar dan fasilitas</h2>
                <p class="muted">Informasi kamar dan foto fasilitas mengikuti data yang dikelola untuk gedung ini.</p>
                <?php if ($propertyRoomGroups): ?>
                    <div class="property-room-type-list">
                        <?php $propertyRoomGroupIndex = 0; foreach ($propertyRoomGroups as $roomType => $typeRooms):
                            $typeId = 'property-room-type-' . $propertyRoomGroupIndex;
                            $typeFacilityPhotos = $roomFacilityPhotosByType[$roomType] ?? [];
                            $availableTypeRooms = array_values(array_filter(
                                $typeRooms,
                                static fn(array $room): bool => (bool) $room['is_available']
                            ));
                        ?>
                            <section class="property-room-type">
                                <div class="property-room-type-heading">
                                    <div><span>Tipe kamar</span><h3><?= e($roomType) ?></h3></div>
                                    <small><?= count($typeRooms) ?> kamar · <?= count($availableTypeRooms) ?> tersedia</small>
                                </div>
                                <?php if ($typeFacilityPhotos): ?>
                                    <div class="property-room-type-facilities">
                                        <span>Foto fasilitas tipe <?= e($roomType) ?></span>
                                        <div>
                                            <?php foreach ($typeFacilityPhotos as $photoPath): ?>
                                                <button class="property-facility-thumbnail" type="button" data-photo-preview="<?= e($photoPath) ?>" data-photo-alt="<?= e('Foto fasilitas ' . $roomType) ?>" aria-label="Lihat foto fasilitas <?= e($roomType) ?>">
                                                    <img src="<?= e($photoPath) ?>" alt="Fasilitas <?= e($roomType) ?>" loading="lazy">
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="property-room-carousel">
                                    <div class="property-carousel-controls property-room-type-controls">
                                        <button type="button" class="property-carousel-arrow" data-carousel-target="<?= e($typeId) ?>" data-carousel-direction="left" aria-label="Kamar <?= e($roomType) ?> sebelumnya" disabled>‹</button>
                                        <button type="button" class="property-carousel-arrow" data-carousel-target="<?= e($typeId) ?>" data-carousel-direction="right" aria-label="Kamar <?= e($roomType) ?> berikutnya">›</button>
                                    </div>
                                    <div class="property-carousel-track property-room-type-track" id="<?= e($typeId) ?>" tabindex="0" aria-label="Daftar kamar <?= e($roomType) ?>">
                                        <?php foreach ($typeRooms as $room): ?>
                                            <article class="property-room-vcard">
                                                <a class="property-room-vcard-photo" href="index.php?page=room&amp;id=<?= (int) $room['id'] ?>" aria-label="Lihat detail kamar <?= e($room['room_number']) ?>">
                                                    <?php if (!empty($room['cover_photo'])): ?><img src="<?= e($room['cover_photo']) ?>" alt="Foto kamar <?= e($room['room_number']) ?>" loading="lazy"><?php else: ?><span aria-hidden="true">⌂</span><?php endif; ?>
                                                    <span class="property-room-availability <?= $room['is_available'] ? 'available' : 'unavailable' ?>"><i aria-hidden="true"></i><?= $room['is_available'] ? 'Tersedia' : 'Tidak tersedia' ?></span>
                                                </a>
                                                <div class="property-room-vcard-body">
                                                    <small>Kamar <?= e($room['room_number']) ?><?= $room['floor'] ? ' · Lantai ' . e($room['floor']) : '' ?></small>
                                                    <h4><a href="index.php?page=room&amp;id=<?= (int) $room['id'] ?>">Kamar <?= e($room['room_number']) ?></a></h4>
                                                    <p><?= e($room['facilities'] ?: 'Informasi fasilitas kamar belum ditambahkan.') ?></p>
                                                    <strong><?= e(money($room['monthly_rate'])) ?><small> / bulan</small></strong>
                                                    <?php if ($room['is_available']): ?>
                                                        <a class="button primary" href="<?= $user && $user['role'] === 'seeker' ? 'index.php?page=booking_form&amp;id=' . (int) $room['id'] : 'index.php?page=register&amp;role=seeker&amp;next=booking&amp;id=' . (int) $room['id'] ?>">Booking kamar</a>
                                                    <?php else: ?>
                                                        <span class="property-room-unavailable">Kamar tidak dapat dibooking saat ini</span>
                                                    <?php endif; ?>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </section>
                        <?php $propertyRoomGroupIndex++; endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">Informasi kamar belum ditambahkan untuk gedung ini.</p>
                <?php endif; ?>
            </section>
            <section class="property-section" id="fasilitas-umum">
                <h2>Fasilitas umum</h2>
                <div class="property-feature-list"><?php foreach (array_filter(array_map('trim', preg_split('/[,;\r\n]+/', (string) $property['general_facilities']))) as $facility): ?><span><?= e($facility) ?></span><?php endforeach; ?><?php if (!$property['general_facilities']): ?><span>Informasi fasilitas umum belum ditambahkan.</span><?php endif; ?></div>
                <?php if ($generalFacilityPhotos): ?>
                    <div class="property-general-facility-photos" aria-label="Foto fasilitas umum">
                        <?php foreach ($generalFacilityPhotos as $photoPath): ?>
                            <button class="property-facility-thumbnail" type="button" data-photo-preview="<?= e($photoPath) ?>" data-photo-alt="Foto fasilitas umum kos" aria-label="Lihat foto fasilitas umum">
                                <img src="<?= e($photoPath) ?>" alt="Fasilitas umum kos" loading="lazy">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($property['house_rules']): ?><h3>Peraturan kos</h3><p><?= nl2br(e($property['house_rules'])) ?></p><?php endif; ?>
            </section>
            <section class="property-section"><h2>Alamat kos</h2><p><?= e($property['address']) ?></p></section>
            <section class="property-section" id="review"><h2>Ulasan pengguna</h2><?php if ($propertyReviews): ?><div class="property-review-list"><?php foreach ($propertyReviews as $review): ?><article><strong><?= e($review['reviewer_name'] ?: 'Pengguna') ?> · <?= str_repeat('★',(int)$review['rating']) ?></strong><small><?= e(date_id($review['created_at'])) ?></small><p><?= nl2br(e($review['review_text'])) ?></p><?php if ($review['operator_reply']): ?><div class="property-review-reply"><strong>Balasan pengelola</strong><?php if ($review['operator_replied_at']): ?><small><?= e(date_id($review['operator_replied_at'])) ?></small><?php endif; ?><p><?= nl2br(e($review['operator_reply'])) ?></p></div><?php endif; ?></article><?php endforeach; ?></div><?php else: ?><p class="muted">Kos ini belum memiliki ulasan.</p><?php endif; ?><?php if ($canReview): ?><form method="post" class="property-review-form"><?= csrf_field() ?><input type="hidden" name="action" value="submit_review"><input type="hidden" name="building_id" value="<?= $buildingId ?>"><fieldset class="review-star-field"><legend>Rating Anda</legend><div class="review-stars"><?php for($star=1;$star<=5;$star++): ?><label><input type="radio" name="rating" value="<?= $star ?>" required><span aria-label="<?= $star ?> bintang">★</span></label><?php endfor; ?></div></fieldset><label class="field"><span>Ceritakan pengalaman Anda</span><textarea name="review_text" rows="4" maxlength="3000" required></textarea></label><button class="button primary" type="submit">Kirim ulasan</button></form><?php elseif (!$user): ?><p class="muted">Silakan <button class="text-link" type="button" data-dialog-open="public-login-dialog">masuk</button> sebagai User untuk memberikan ulasan.</p><?php endif; ?></section>
            <section class="property-section" id="pemilik"><h2>Pemilik kos</h2><article class="property-owner-card"><?= avatar_markup(['name' => $property['owner_name'] ?: 'Pengelola kos', 'gender' => $property['owner_gender'], 'profile_photo_path' => $property['owner_photo'], 'avatar_key' => $property['owner_avatar']]) ?><div><strong><?= e($property['owner_name'] ?: 'Pengelola kos') ?></strong><small>Owner kos</small></div><div class="property-owner-actions"><?php if ($user && $user['role'] === 'seeker'): ?><button class="button secondary compact-button" type="button" data-dialog-open="property-chat-dialog">Chat Sistem</button><?php endif; ?><?php if ($property['owner_phone'] && ($whatsappUrl = whatsapp_url((string) $property['owner_phone']))): ?><a class="button secondary compact-button" href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer">WhatsApp ↗</a><?php elseif ($user && $user['role'] === 'seeker'): ?><small>Nomor WhatsApp disembunyikan; gunakan chat sistem.</small><?php endif; ?></div></article></section>
        </div><aside class="property-booking-card"><span>Mulai dari</span><strong><?= e($availablePropertyRooms ? money(min(array_column($availablePropertyRooms, 'monthly_rate'))) : '—') ?><small>/ bulan</small></strong><a class="button primary full" href="#fasilitas-kamar">Pilih kamar tersedia</a><p>Login diperlukan untuk mengirim permintaan booking. Pemilik kos akan meninjau permintaan Anda.</p></aside></div>
    </main>
    <?php if ($property['latitude'] !== null && $property['longitude'] !== null): ?><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script><script>const propertyMap=L.map('property-map',{scrollWheelZoom:false,zoomControl:false}).setView([<?= json_encode((float)$property['latitude']) ?>,<?= json_encode((float)$property['longitude']) ?>],14);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(propertyMap);L.marker([<?= json_encode((float)$property['latitude']) ?>,<?= json_encode((float)$property['longitude']) ?>]).addTo(propertyMap).bindPopup(<?= json_encode($property['name'], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>);setTimeout(()=>propertyMap.invalidateSize(),100);document.getElementById('property-map').addEventListener('click',()=>propertyMap.scrollWheelZoom.enable());</script><?php endif; ?>
    <?php if ($user && $user['role'] === 'seeker'): ?>
        <dialog class="action-dialog" id="property-chat-dialog"><form method="post" class="action-dialog-form"><?= csrf_field() ?><input type="hidden" name="action" value="start_chat_conversation"><input type="hidden" name="building_id" value="<?= $buildingId ?>"><button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button><span class="dialog-eyebrow">CHAT SISTEM</span><h2>Hubungi pengelola</h2><p>Pesan hanya dapat dibaca oleh Anda dan pengelola gedung.</p><label class="field"><span>Pesan</span><textarea name="message" rows="4" maxlength="4000" required>Halo, saya tertarik dengan <?= e($property['name']) ?>.</textarea></label><div class="dialog-actions"><button class="button secondary" type="button" data-dialog-close>Batal</button><button class="button primary" type="submit">Mulai chat</button></div></form></dialog>
        <dialog class="action-dialog" id="report-property-dialog"><form method="post" class="action-dialog-form"><?= csrf_field() ?><input type="hidden" name="action" value="report_property"><input type="hidden" name="building_id" value="<?= $buildingId ?>"><button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button><span class="dialog-eyebrow">LAPORKAN PROPERTI</span><h2>Laporkan informasi kos</h2><label class="field"><span>Alasan laporan</span><select name="report_reason" required><option value="">Pilih alasan</option><option value="misleading">Informasi tidak sesuai</option><option value="fake">Properti tidak ditemukan</option><option value="inappropriate">Foto atau konten tidak pantas</option><option value="other">Lainnya</option></select></label><label class="field"><span>Detail tambahan</span><textarea name="report_details" rows="3" maxlength="2000"></textarea></label><div class="dialog-actions"><button class="button secondary" type="button" data-dialog-close>Batal</button><button class="button danger" type="submit">Kirim laporan</button></div></form></dialog>
    <?php endif; ?>
    <?php if ($user && $user['role'] === 'owner' && $canManageProperty && $property['verification_status'] !== 'verified'): ?><dialog class="action-dialog" id="property-verification-dialog"><form method="post" class="action-dialog-form"><?= csrf_field() ?><input type="hidden" name="action" value="submit_property_verification"><input type="hidden" name="building_id" value="<?= $buildingId ?>"><button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button><span class="dialog-eyebrow">VERIFIKASI PROPERTI</span><h2>Ajukan verifikasi kos</h2><p>Pengajuan memerlukan data gedung lengkap, alamat dan pin lokasi, minimal satu kamar, serta tiga foto yang jelas. Klasifikasi menunjukkan tingkat verifikasi, bukan kemewahan.</p><label class="field"><span>Ajukan klasifikasi</span><select name="requested_tier" required><option value="premium">Premium</option><option value="exclusive">Eksklusif</option></select></label><label class="field"><span>Catatan untuk admin (opsional)</span><textarea name="operator_note" rows="3" maxlength="2000"></textarea></label><div class="dialog-actions"><button class="button secondary" type="button" data-dialog-close>Batal</button><button class="button primary" type="submit">Kirim pengajuan</button></div></form></dialog><?php endif; ?>
    <dialog class="photo-lightbox" id="photo-lightbox" aria-label="Pratinjau foto"><button class="photo-lightbox-close" type="button" data-photo-preview-close aria-label="Tutup foto">×</button><img src="" alt=""></dialog>
    <?php if (!$user): render_public_login_dialog(); endif; ?>
    <?php require __DIR__ . '/../chat/widget.php'; ?>
    <script src="app.js?v=koskosan-ui-20261023-2"></script>
    </body></html><?php
    exit;
}

if (in_array($page, ['home', 'search'], true)) {
    $searchTerm = trim(isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '');
    if (strlen($searchTerm) > 255) {
        $searchTerm = substr($searchTerm, 0, 255);
    }
    $genderPolicy = isset($_GET['gender_policy']) && is_string($_GET['gender_policy'])
        && in_array($_GET['gender_policy'], ['male', 'female', 'mixed'], true)
        ? $_GET['gender_policy'] : '';
    $searchGender = $genderPolicy !== '' ? '' : (
        str_contains(strtolower($searchTerm), 'putri') ? 'female'
        : (str_contains(strtolower($searchTerm), 'putra') ? 'male' : '')
    );
    $nearbyLatitude = filter_var($_GET['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $nearbyLongitude = filter_var($_GET['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $hasNearbyLocation = $nearbyLatitude !== false && $nearbyLongitude !== false
        && $nearbyLatitude !== null && $nearbyLongitude !== null
        && $nearbyLatitude >= -90 && $nearbyLatitude <= 90
        && $nearbyLongitude >= -180 && $nearbyLongitude <= 180;
    $popularDestinations = rows(
        $pdo,
        "SELECT ir.name,COUNT(DISTINCT b.id) AS property_count
         FROM kos_buildings b
         JOIN indonesia_regions ir ON ir.code=b.regency_code AND ir.level='regency'
         WHERE b.status='active'
         GROUP BY ir.code,ir.name
         ORDER BY property_count DESC,ir.name
         LIMIT 6"
    );
    $regionSearchSql = "OR EXISTS (
        SELECT 1 FROM indonesia_regions ir
        WHERE ir.code IN (b.province_code,b.regency_code,b.district_code,b.village_code)
          AND ir.name LIKE :region_like
    )";
    $genderSearchSql = $searchGender !== '' ? ' OR b.gender_policy=:gender_search' : '';
    $genderFilterSql = $genderPolicy !== '' ? ' AND b.gender_policy=:gender_filter' : '';
    $nearbyOrderSql = $hasNearbyLocation
        ? " ORDER BY (b.latitude IS NULL OR b.longitude IS NULL),
                (6371 * ACOS(LEAST(1,GREATEST(-1,
                COS(RADIANS(:order_latitude)) * COS(RADIANS(b.latitude))
                * COS(RADIANS(b.longitude) - RADIANS(:order_longitude))
                + SIN(RADIANS(:order_latitude_2)) * SIN(RADIANS(b.latitude))
            )))), rm.monthly_rate, b.name, rm.room_number"
        : ' ORDER BY rm.monthly_rate, b.name, rm.room_number';
    $publicRooms = rows(
        $pdo,
        "SELECT rm.*, b.name AS building_name, b.address AS building_address,
                b.description AS building_description, b.gender_policy,
                b.verification_tier,b.verification_status,
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
         AND (b.owner_user_id IS NULL OR EXISTS (
             SELECT 1 FROM users owner WHERE owner.id=b.owner_user_id
               AND owner.status='active' AND owner.self_deactivated_at IS NULL
         ))
         AND (:search_name='' OR b.name LIKE :name_like OR b.address LIKE :address_like
              {$regionSearchSql}{$genderSearchSql})
         {$genderFilterSql}{$nearbyOrderSql}",
        array_merge([
            'search_name' => $searchTerm,
            'name_like' => '%' . $searchTerm . '%',
            'address_like' => '%' . $searchTerm . '%',
            'region_like' => '%' . $searchTerm . '%',
        ], $searchGender !== '' ? ['gender_search' => $searchGender] : [],
            $genderPolicy !== '' ? ['gender_filter' => $genderPolicy] : [],
            $hasNearbyLocation ? [
                'order_latitude' => $nearbyLatitude, 'order_latitude_2' => $nearbyLatitude,
                'order_longitude' => $nearbyLongitude,
            ] : [])
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
                'verification_tier' => $room['verification_tier'],
                'verification_status' => $room['verification_status'],
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
                b.name AS building_name,COALESCE(reviewer.name,u.name) AS reviewer_name
         FROM property_reviews pr
         JOIN kos_buildings b ON b.id=pr.building_id
         LEFT JOIN tenants t ON t.id=pr.tenant_id
         LEFT JOIN users u ON u.id=t.user_id
         LEFT JOIN users reviewer ON reviewer.id=pr.reviewer_user_id
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
        <meta name="description" content="Temukan kos dan kamar nyaman di KosKosan. Bandingkan tipe kamar, fasilitas, lokasi, dan harga terbaru.">
        <title>KosKosan — Hunian nyaman, hati tenang</title>
        <link rel="manifest" href="site.webmanifest">
        <link rel="stylesheet" href="style.css?v=koskosan-ui-20261023-2">
        <?php if ($mapBuildings): ?><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""><?php endif; ?>
    </head>
    <body class="public-page">
        <header class="public-header">
            <nav class="public-nav" aria-label="Navigasi utama">
                <a class="tiara-brand koskosan-brand" href="index.php" aria-label="KosKosan, beranda">
                    <span class="tiara-brand-icon">K</span>
                    <span>KosKosan<small>HUNIAN NYAMAN</small></span>
                </a>
                <form class="public-search" action="index.php" method="get" data-public-search>
                    <input type="hidden" name="page" value="home">
                    <input type="hidden" name="latitude" value="<?= $hasNearbyLocation ? e((string) $nearbyLatitude) : '' ?>" data-nearby-latitude>
                    <input type="hidden" name="longitude" value="<?= $hasNearbyLocation ? e((string) $nearbyLongitude) : '' ?>" data-nearby-longitude>
                    <input name="q" value="<?= e($searchTerm) ?>" placeholder="Cari nama kos atau alamat" aria-label="Cari nama kos atau alamat" autocomplete="off">
                    <button class="button primary" type="submit">Cari</button>
                    <div class="public-search-options">
                        <button class="search-nearby-button" type="button" data-search-nearby><span aria-hidden="true">⌖</span>Dekat saya</button>
                        <div class="search-popular-destinations">
                            <h2>Destinasi populer</h2>
                            <?php foreach ($popularDestinations as $destination): ?>
                                <button type="button" data-search-destination="<?= e($destination['name']) ?>">
                                    <span><strong><?= e($destination['name']) ?></strong><small>Indonesia</small></span>
                                    <span class="search-destination-count"><?= (int) $destination['property_count'] ?> kos</span>
                                </button>
                            <?php endforeach; ?>
                            <?php if (!$popularDestinations): ?><p>Lokasi akan muncul setelah kos tersedia.</p><?php endif; ?>
                        </div>
                        <label class="public-search-type"><span>Jenis kos</span><select name="gender_policy" aria-label="Filter jenis kos">
                            <option value="">Semua jenis kos</option>
                            <option value="male" <?= $genderPolicy === 'male' ? 'selected' : '' ?>>Kos putra</option>
                            <option value="female" <?= $genderPolicy === 'female' ? 'selected' : '' ?>>Kos putri</option>
                            <option value="mixed" <?= $genderPolicy === 'mixed' ? 'selected' : '' ?>>Kos campur</option>
                        </select></label>
                        <small class="search-nearby-status" data-nearby-status aria-live="polite"><?= $hasNearbyLocation ? 'Hasil diurutkan berdasarkan lokasi Anda.' : '' ?></small>
                    </div>
                </form>
                <div class="public-nav-links">
                    <a class="nav-current" href="index.php" aria-current="page">Beranda</a>
                    <a href="#kamar">Pilihan kos</a>
                    <a href="#peta">Peta lokasi</a>
                    <a href="#kontak">Kontak</a>
                    <?php if (!$user): ?><a href="index.php?page=register&amp;role=owner">Daftarkan kos</a><?php endif; ?>
                </div>
                <?php if ($user): ?><?= notification_bell_markup($notificationUnreadCount, $pdo) ?><?= message_menu_markup($user['role']) ?><?php endif; ?>
                <?= theme_switcher() ?>
                <?php if ($user): ?><a class="public-nav-cta" href="index.php?page=<?= $user['role'] === 'seeker' ? 'my_bookings' : ($user['role'] === 'admin' ? 'platform_dashboard' : 'dashboard') ?>"><?= $user['role'] === 'seeker' ? 'Panel User' : ($user['role'] === 'admin' ? 'Panel Admin' : 'Panel Owner') ?><span aria-hidden="true">↗</span></a><?php else: ?><button class="public-nav-cta" type="button" data-dialog-open="public-login-dialog">Masuk / Daftar<span aria-hidden="true">↗</span></button><?php endif; ?>
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
                        <div class="hero-art" aria-label="Ilustrasi rumah KosKosan">
                            <div class="sun-disc"></div><div class="hero-plant plant-one">✳</div><div class="hero-plant plant-two">✳</div>
                            <div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
                            <div class="house-art"><div class="house-roof"></div><div class="house-top"><div class="house-window window-left"><i></i></div><div class="house-window window-right"><i></i></div></div><div class="house-awning"></div><div class="house-bottom"><div class="house-door"><i></i></div><div class="house-window window-bottom"><i></i></div></div><div class="house-sign">KOSKOSAN</div></div>
                            <div class="art-leaf leaf-left">✦</div><div class="art-leaf leaf-right">✦</div>
                            <div class="hero-room-tag"><span class="room-tag-icon">⌂</span><span><strong><?= $roomCount ?> kamar tersedia</strong><small>Siap jadi ruang ceritamu</small></span><b>↗</b></div>
                        </div>
                    </div>
                </div>
                <div class="hero-bottom-wave"></div>
            </section>
            <section class="public-rooms-section" id="kamar">
                <div class="rooms-section-heading">
                    <div><div class="section-eyebrow"><span></span> <?= $searchTerm !== '' ? 'HASIL PENCARIAN' : 'PILIHAN HUNIAN' ?></div><h2><?= $searchTerm !== '' ? 'Kos yang cocok,' : 'Temukan kos favorit,' ?><br><span><?= $searchTerm !== '' ? 'untuk pencarianmu.' : 'untuk cerita barumu.' ?></span></h2></div>
                    <p><?= $searchTerm !== '' ? 'Ditemukan ' . count($searchListings) . ' lokasi kos dengan ' . $roomCount . ' kamar tersedia untuk “' . e($searchTerm) . '”.' : 'Jelajahi kos favorit, rekomendasi dengan ulasan terbaik, dan seluruh daftar kos yang tersedia di KosKosan.' ?></p>
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
                                        <span class="property-card-location-tag"><?= e($listing['address']) ?></span>
                                        <span class="availability-pill"><i></i> <?= (int) $listing['available_count'] ?> kamar tersedia</span>
                                    </a>
                                    <div class="property-result-body">
                                        <div class="property-result-heading"><div><span class="section-eyebrow">KOS DI SEKITARMU</span><h3><?= e($listing['name']) ?></h3><span class="verification-badge tier-<?= e($listing['verification_tier']) ?>"><?= e($listing['verification_status'] === 'verified' ? ucfirst($listing['verification_tier']) . ' · Terverifikasi' : 'Basic') ?></span></div><span class="listing-type-count"><?= count($listingTypes) ?> tipe</span></div>
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
                            ['id' => 'all-kost', 'eyebrow' => 'JELAJAHI KOSKOSAN', 'title' => 'Daftar kos', 'listings' => $allListings],
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
                                                <span class="property-card-location-tag"><?= e($listing['address']) ?></span>
                                                <span class="availability-pill"><i></i> <?= (int) $listing['available_count'] ?> kamar tersedia</span>
                                                <?php if ($propertyRow['id'] === 'favorite-kost'): ?><span class="property-carousel-badge">♥ Favorit</span><?php endif; ?>
                                            </div>
                                            <div class="property-carousel-card-body">
                                                <h4><?= e($listing['name']) ?></h4>
                                                <span class="verification-badge tier-<?= e($listing['verification_tier']) ?>"><?= e($listing['verification_status'] === 'verified' ? ucfirst($listing['verification_tier']) . ' · Terverifikasi' : 'Basic') ?></span>
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
                                                <span class="property-card-location-tag"><?= e($room['building_name']) ?></span>
                                                <span class="availability-pill <?= $room['is_available'] ? '' : 'unavailable' ?>"><i></i> <?= $room['is_available'] ? 'Tersedia' : 'Tidak tersedia' ?></span>
                                            </div>
                                            <div class="property-carousel-card-body">
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
                            <div id="tiara-kos-map" class="tiara-kos-map" role="application" aria-label="Peta lokasi kos di KosKosan"></div>
                            <script type="application/json" id="tiara-kos-map-data"><?= json_encode($mapBuildings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
                        <?php else: ?><div class="map-empty-state"><span>⌖</span><strong>Lokasi belum tersedia di peta</strong><p>Pengelola perlu mengisi koordinat gedung dan menambahkan kamar kosong yang aktif.</p></div><?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
        <footer class="public-footer" id="kontak">
            <div class="public-footer-inner">
                <div class="public-footer-brand">
                    <a class="tiara-brand koskosan-brand" href="#beranda" aria-label="KosKosan, kembali ke atas">
                        <span class="tiara-brand-icon">K</span>
                        <span>KosKosan<small>HUNIAN NYAMAN</small></span>
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
                    <?php if ($adminWhatsappUrl = site_contact_whatsapp_url()): ?><a href="<?= e($adminWhatsappUrl) ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">☎</span><span>WhatsApp Admin · 081342122105</span></a><?php endif; ?>
                    <a href="mailto:alstrazhila@gmail.com"><span aria-hidden="true">✉</span><span>alstrazhila@gmail.com</span></a>
                </section>
                <div class="public-footer-bottom">
                    <span>© <?= date('Y') ?> KosKosan. Hak cipta dilindungi.</span>
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
        <?php if (!$user): render_public_login_dialog(); endif; ?>
        <?php require __DIR__ . '/../chat/widget.php'; ?>
        <script src="app.js?v=koskosan-ui-20261023-2"></script>
    </body>
    </html>
    <?php
    exit;
}
