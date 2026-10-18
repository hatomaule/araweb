<?php
                $buildingId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
                $building = $buildingId ? one($pdo, 'SELECT * FROM kos_buildings WHERE id=:id', ['id' => $buildingId]) : null;
                if ($buildingId && !$building) { set_flash('error', 'Data gedung tidak ditemukan.'); redirect('buildings'); }
                if ($buildingId && !can_manage_building($pdo, $user, $buildingId)) {
                    set_flash('error', 'Anda tidak dapat melihat atau mengubah kos milik akun lain.');
                    redirect('buildings');
                }
                $buildingPhotos = $buildingId
                    ? rows($pdo, 'SELECT id,photo_path FROM property_photos WHERE building_id=:id ORDER BY display_order,id', ['id' => $buildingId])
                    : [];
            ?>
                <div class="form-panel"><div class="panel-heading"><div><h2><?= $building ? 'Edit gedung kos' : 'Tambah gedung kos' ?></h2><p>Masukkan lokasi gedung agar calon pelanggan dapat menemukannya di peta.</p></div><a class="text-link" href="index.php?page=buildings">← Kembali</a></div>
                    <?php if ($building && $building['latitude'] === null): ?><div class="alert info">Gedung lama ini belum memiliki koordinat. Isi koordinatnya agar lokasi tampil di peta publik.</div><?php endif; ?>
                    <?php if ($building): ?>
                        <section class="profile-image-editor full-span">
                            <div><strong>Foto profil kos</strong><small>Foto ini tampil di samping peta pada halaman kos dan menjadi sampul kos di halaman depan.</small></div>
                            <?php if (!empty($building['profile_photo_path'])): ?><img class="profile-image-editor-preview" src="<?= e($building['profile_photo_path']) ?>" alt="Foto profil kos <?= e($building['name']) ?>"><?php elseif ($buildingPhotos): ?><img class="profile-image-editor-preview" src="<?= e($buildingPhotos[0]['photo_path']) ?>" alt="Foto galeri sampul <?= e($building['name']) ?>"><small class="field-help">Foto galeri pertama saat ini digunakan sebagai sampul. Unggah foto untuk menetapkan foto profil khusus.</small><?php else: ?><div class="profile-image-editor-empty">Belum ada foto profil kos</div><?php endif; ?>
                            <form method="post" enctype="multipart/form-data" class="profile-image-editor-form"><?= csrf_field() ?><input type="hidden" name="action" value="update_building_profile_photo"><input type="hidden" name="building_id" value="<?= $buildingId ?>">
                                <div class="photo-upload-control" data-photo-upload data-photo-upload-saved="<?= !empty($building['profile_photo_path']) ? 1 : 0 ?>" data-photo-upload-limit="1">
                                    <input class="visually-hidden" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" data-photo-upload-input>
                                    <div class="building-gallery-actions">
                                        <button class="button secondary" type="button" data-photo-upload-trigger>＋ Upload foto</button>
                                        <span class="field-help" data-photo-upload-count><?= !empty($building['profile_photo_path']) ? '1 foto tersimpan' : 'Belum ada foto yang dipilih' ?></span>
                                    </div>
                                    <small class="field-help" data-photo-upload-status>Pilih satu foto profil kos. Format JPG, PNG, atau WebP; maksimal 4 MB.</small>
                                    <div class="photo-upload-preview-grid" data-photo-upload-grid></div>
                                </div>
                                <div class="profile-image-editor-actions"><button class="button primary" type="submit">Simpan foto profil</button><?php if (!empty($building['profile_photo_path'])): ?><button class="button secondary" type="submit" name="remove_profile_photo" value="yes" formnovalidate onclick="this.form.querySelector('[name=profile_photo]').value='';">Hapus foto profil</button><?php endif; ?></div>
                            </form>
                        </section>
                    <?php endif; ?>
                    <form id="building-form" method="post" enctype="multipart/form-data" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="save_building"><input type="hidden" name="id" value="<?= $buildingId ?>"><input type="hidden" name="map_address" id="map-address" value="<?= isset($building['latitude'], $building['longitude']) && $building['latitude'] !== null && $building['longitude'] !== null ? e($building['address'] ?? '') : '' ?>"><input id="building-gallery-input" class="visually-hidden" type="file" name="property_photos[]" accept="image/jpeg,image/png,image/webp" multiple aria-label="Pilih foto galeri gedung" aria-hidden="true" tabindex="-1">
                        <?php field('Nama kos / gedung *', 'name', $building['name'] ?? '', 'text', ['required' => 'required', 'maxlength' => '150', 'placeholder' => 'Contoh: Tiara Kos - Gedung Melati']); ?>
                        <label class="field"><span>Status gedung</span><select name="status"><option value="active" <?= ($building['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option><option value="inactive" <?= ($building['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Nonaktif</option></select><small class="field-help">Gedung nonaktif tidak tampil pada peta dan tidak dapat dipilih untuk kamar baru.</small></label>
                        <?= indonesia_region_fields($pdo, [
                            'province' => $building['province_code'] ?? '',
                            'regency' => $building['regency_code'] ?? '',
                            'district' => $building['district_code'] ?? '',
                            'village' => $building['village_code'] ?? '',
                        ], true) ?>
                        <?php if ($building && empty($building['province_code']) && !empty($building['address'])): ?>
                            <p class="field-help full-span">Alamat lama dimuat sebagai alamat detail. Lengkapi wilayah di atas, lalu rapikan alamat detail bila perlu.</p>
                        <?php endif; ?>
                        <?php field(
                            'Nama jalan / alamat detail',
                            'street_name',
                            $building['street_name'] ?? (empty($building['province_code']) ? ($building['address'] ?? '') : ''),
                            'text',
                            ['maxlength' => '1000', 'placeholder' => 'Contoh: Jl. Anggrek No. 12']
                        ); ?>
                        <?php field('RT (opsional)', 'address_rt', $building['address_rt'] ?? '', 'text', ['maxlength' => '3', 'inputmode' => 'numeric', 'pattern' => '[0-9]{1,3}']); ?>
                        <?php field('RW (opsional)', 'address_rw', $building['address_rw'] ?? '', 'text', ['maxlength' => '3', 'inputmode' => 'numeric', 'pattern' => '[0-9]{1,3}']); ?>
                        <?php field('Detail Alamat (opsional)', 'address_block', $building['address_block'] ?? '', 'text', ['maxlength' => '50', 'placeholder' => 'Nomor rumah, patokan, atau keterangan lain']); ?>
                        <label class="field"><span>Kebijakan penghuni</span><select name="gender_policy"><option value="mixed" <?= ($building['gender_policy'] ?? 'mixed') === 'mixed' ? 'selected' : '' ?>>Campur</option><option value="male" <?= ($building['gender_policy'] ?? '') === 'male' ? 'selected' : '' ?>>Putra</option><option value="female" <?= ($building['gender_policy'] ?? '') === 'female' ? 'selected' : '' ?>>Putri</option></select></label>
                        <div class="building-map-editor full-span" id="lokasi-gedung">
                            <div class="building-coordinate-help"><strong>Atur lokasi kos (opsional)</strong><p>Pilih titik pada peta untuk mengisi koordinat dan alamat secara otomatis. Lokasi peta boleh dikosongkan; alamat dan data gedung tetap dapat disimpan. Peta publik memakai titik ini bila tersedia.</p></div>
                            <div class="building-location-map" id="building-location-map" role="application" aria-label="Peta pemilihan lokasi gedung"></div>
                            <small class="field-help" id="building-map-status" aria-live="polite">Klik peta untuk memilih titik lokasi. Koordinat akan digunakan untuk mencari alamat melalui OpenStreetMap Nominatim.</small>
                            <small class="field-help">Peta © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>; geocoding memakai <a href="https://operations.osmfoundation.org/policies/nominatim/" target="_blank" rel="noopener noreferrer">Nominatim</a>.</small>
                            <label class="field"><span>Tautan Google Maps (opsional)</span><input type="url" id="google-maps-url" placeholder="https://maps.google.com/..."><small class="field-help" id="google-maps-status">Buka Google Maps, pilih titik kos, lalu salin tautannya ke sini.</small></label>
                            <div class="form-grid building-coordinate-fields">
                                <?php field('Latitude (opsional)', 'latitude', $building['latitude'] ?? '', 'number', ['id' => 'building-latitude', 'min' => '-90', 'max' => '90', 'step' => 'any', 'placeholder' => '-6.2000000']); field('Longitude (opsional)', 'longitude', $building['longitude'] ?? '', 'number', ['id' => 'building-longitude', 'min' => '-180', 'max' => '180', 'step' => 'any', 'placeholder' => '106.8166667']); ?>
                            </div>
                            <a class="text-link building-google-search" id="google-maps-search" href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode($building['address'] ?? '') ?>" target="_blank" rel="noopener noreferrer">Cari alamat ini di Google Maps ↗</a>
                        </div>
                        <label class="field full-span"><span>Deskripsi kos</span><textarea name="description" rows="4" maxlength="10000"><?= e($building['description'] ?? '') ?></textarea></label>
                        <label class="field full-span"><span>Fasilitas umum (pisahkan dengan koma atau baris baru)</span><textarea name="general_facilities" rows="3" maxlength="5000"><?= e($building['general_facilities'] ?? '') ?></textarea></label>
                        <label class="field full-span"><span>Peraturan kos</span><textarea name="house_rules" rows="3" maxlength="5000"><?= e($building['house_rules'] ?? '') ?></textarea></label>
                    </form>
                    <section class="property-photo-manager full-span building-gallery-manager" data-building-gallery>
                        <strong>Galeri foto gedung</strong>
                        <p class="field-help" data-building-gallery-status aria-live="polite">Tekan Upload foto untuk memilih foto. Foto baru akan tersimpan saat Anda menekan tombol simpan perubahan.</p>
                        <div class="property-photo-manager-grid" data-building-gallery-grid>
                            <?php foreach ($buildingPhotos as $photo): ?>
                                <article>
                                    <img src="<?= e($photo['photo_path']) ?>" alt="Foto galeri kos">
                                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete_property_photo"><input type="hidden" name="building_id" value="<?= $buildingId ?>"><input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>"><button class="text-button" type="submit">Hapus foto</button></form>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <div class="building-gallery-actions">
                            <button class="button secondary" type="button" data-building-gallery-add>＋ Upload foto</button>
                            <span class="field-help" data-building-gallery-count><?= count($buildingPhotos) ?> foto tersimpan</span>
                        </div>
                    </section>
                    <div class="form-actions full-span"><a class="button secondary" href="index.php?page=buildings">Batal</a><button class="button primary" type="submit" form="building-form"><?= $building ? 'Simpan perubahan terbaru' : 'Simpan gedung' ?></button></div>
                </div>
                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
                <script>
                (() => {
                    const mapsUrl = document.getElementById('google-maps-url');
                    const latitude = document.getElementById('building-latitude');
                    const longitude = document.getElementById('building-longitude');
                    const status = document.getElementById('google-maps-status');
                    const searchLink = document.getElementById('google-maps-search');
                    const mapElement = document.getElementById('building-location-map');
                    const mapStatus = document.getElementById('building-map-status');
                    const streetField = document.querySelector('[name="street_name"]');
                    const mapAddress = document.getElementById('map-address');
                    const legacyAddress = <?= json_encode(
                        $building['address'] ?? '',
                        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
                    ) ?>;
                    if (!mapsUrl || !latitude || !longitude || !mapElement || !mapStatus || !streetField || !mapAddress) return;
                    const hasCoordinates = () => latitude.value !== '' && longitude.value !== ''
                        && Number.isFinite(Number(latitude.value))
                        && Number.isFinite(Number(longitude.value))
                        && Number(latitude.value) >= -90 && Number(latitude.value) <= 90
                        && Number(longitude.value) >= -180 && Number(longitude.value) <= 180;
                    const updateSearchLink = () => {
                        const selectedAddress = [
                            document.querySelector('[name="street_name"]').value,
                            ...Array.from(document.querySelectorAll('[data-region-level]'))
                                .map((select) => select.selectedOptions[0]?.textContent || '')
                                .filter((label) => label && !label.startsWith('Pilih ')),
                        ].filter(Boolean).join(', ');
                        const query = hasCoordinates()
                            ? `${latitude.value},${longitude.value}`
                            : selectedAddress || legacyAddress;
                        searchLink.href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
                    };
                    const parseCoordinates = (value) => {
                        const patterns = [
                            /@(-?\d{1,2}(?:\.\d+)?),\s*(-?\d{1,3}(?:\.\d+)?)/,
                            /!3d(-?\d{1,2}(?:\.\d+)?)!4d(-?\d{1,3}(?:\.\d+)?)/,
                            /[?&](?:q|query|ll|center)=(-?\d{1,2}(?:\.\d+)?)[,%2C ]+(-?\d{1,3}(?:\.\d+)?)/i,
                        ];
                        for (const pattern of patterns) {
                            const match = value.match(pattern);
                            if (match) return [match[1], match[2]];
                        }
                        return null;
                    };
                    let locationMap;
                    let locationMarker;
                    let reverseGeocodeTimer;
                    let reverseGeocodeRequest = 0;
                    const invalidateReverseGeocode = () => {
                        clearTimeout(reverseGeocodeTimer);
                        reverseGeocodeRequest += 1;
                    };
                    const updateLocationMarker = (center = false) => {
                        if (!locationMap) return;
                        if (!hasCoordinates()) {
                            if (locationMarker) {
                                locationMap.removeLayer(locationMarker);
                                locationMarker = null;
                            }
                            return;
                        }
                        const point = [Number(latitude.value), Number(longitude.value)];
                        if (locationMarker) locationMarker.setLatLng(point);
                        else {
                            locationMarker = L.marker(point, { draggable: true }).addTo(locationMap);
                            locationMarker.on('dragend', () => selectMapLocation(locationMarker.getLatLng()));
                        }
                        if (center) locationMap.setView(point, Math.max(locationMap.getZoom(), 16));
                    };
                    const reverseGeocode = async (point, requestId) => {
                        mapStatus.textContent = 'Mencari alamat titik yang dipilih…';
                        const endpoint = new URL('index.php', window.location.href);
                        endpoint.searchParams.set('reverse_geocode', '1');
                        endpoint.searchParams.set('lat', point.lat.toFixed(7));
                        endpoint.searchParams.set('lon', point.lng.toFixed(7));
                        try {
                            const response = await fetch(endpoint, {
                                headers: { Accept: 'application/json' },
                                credentials: 'same-origin',
                            });
                            const result = await response.json();
                            if (!response.ok) throw new Error(result.error || `HTTP ${response.status}`);
                            if (requestId !== reverseGeocodeRequest) return;
                            if (latitude.value !== point.lat.toFixed(7)
                                || longitude.value !== point.lng.toFixed(7)) return;
                            if (result.street) streetField.value = result.street;
                            mapAddress.value = result.display_name || '';
                            mapsUrl.value = '';
                            updateSearchLink();
                            mapStatus.textContent = result.display_name
                                ? `Lokasi dipilih: ${result.display_name}. Alamat jalan diperbarui; lengkapi wilayah, RT/RW, atau detail alamat bila perlu.`
                                : 'Koordinat tersimpan. Isi alamat jalan secara manual bila diperlukan.';
                        } catch (error) {
                            if (requestId !== reverseGeocodeRequest) return;
                            console.error('Reverse geocoding failed:', error);
                            mapStatus.textContent = 'Koordinat terpilih, tetapi alamat otomatis gagal dimuat. Lokasi tetap dapat disimpan; isi alamat jalan manual bila diperlukan.';
                        }
                    };
                    const selectMapLocation = (point) => {
                        invalidateReverseGeocode();
                        latitude.value = point.lat.toFixed(7);
                        longitude.value = point.lng.toFixed(7);
                        mapAddress.value = '';
                        updateLocationMarker();
                        updateSearchLink();
                        const requestId = reverseGeocodeRequest;
                        reverseGeocodeTimer = setTimeout(() => reverseGeocode(point, requestId), 900);
                    };
                    if (window.L) {
                        const initialPoint = hasCoordinates()
                            ? [Number(latitude.value), Number(longitude.value)]
                            : [-2.5, 118];
                        locationMap = L.map(mapElement, { scrollWheelZoom: false })
                            .setView(initialPoint, hasCoordinates() ? 15 : 5);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                        }).addTo(locationMap);
                        if (hasCoordinates()) updateLocationMarker();
                        locationMap.on('click', (event) => {
                            selectMapLocation(event.latlng);
                        });
                        mapElement.dataset.mapReady = 'true';
                    } else {
                        mapStatus.textContent = 'Peta gagal dimuat. Lokasi tetap opsional; masukkan koordinat secara manual bila tersedia.';
                    }
                    mapsUrl.addEventListener('input', () => {
                        invalidateReverseGeocode();
                        const coordinates = parseCoordinates(mapsUrl.value);
                        if (!coordinates) {
                            status.textContent = mapsUrl.value
                                ? 'Koordinat tidak terbaca dari tautan ini. Gunakan tautan Google Maps berisi pin, atau masukkan koordinat manual.'
                                : 'Buka Google Maps, pilih titik kos, lalu salin tautannya ke sini.';
                            return;
                        }
                        latitude.value = coordinates[0];
                        longitude.value = coordinates[1];
                        mapAddress.value = '';
                        updateLocationMarker(true);
                        status.textContent = 'Koordinat berhasil diambil dari Google Maps.';
                        updateSearchLink();
                    });
                    [
                        latitude,
                        longitude,
                        streetField,
                        document.querySelector('[name="address_rt"]'),
                        document.querySelector('[name="address_rw"]'),
                        document.querySelector('[name="address_block"]'),
                    ].forEach((field) => {
                        field.addEventListener('input', () => {
                            invalidateReverseGeocode();
                            mapAddress.value = '';
                            updateSearchLink();
                            if (field === latitude || field === longitude) updateLocationMarker(true);
                        });
                    });
                    document.querySelectorAll('[data-region-level]').forEach((select) => {
                        select.addEventListener('change', () => {
                            invalidateReverseGeocode();
                            mapAddress.value = '';
                            updateSearchLink();
                        });
                    });
                    updateSearchLink();
                    if (locationMap) {
                        setTimeout(() => locationMap.invalidateSize(), 0);
                    }
                })();
                </script>
            