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
                                <label class="field"><span>Unggah atau ganti foto profil</span><input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"><small class="field-help">JPG, PNG, atau WebP; maksimal 4 MB.</small></label>
                                <div class="profile-image-editor-actions"><button class="button primary" type="submit">Simpan foto profil</button><?php if (!empty($building['profile_photo_path'])): ?><button class="button secondary" type="submit" name="remove_profile_photo" value="yes" formnovalidate onclick="this.form.querySelector('[name=profile_photo]').value='';">Hapus foto profil</button><?php endif; ?></div>
                            </form>
                        </section>
                    <?php endif; ?>
                    <form method="post" enctype="multipart/form-data" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="save_building"><input type="hidden" name="id" value="<?= $buildingId ?>">
                        <?php field('Nama kos / gedung *', 'name', $building['name'] ?? '', 'text', ['required' => 'required', 'maxlength' => '150', 'placeholder' => 'Contoh: Tiara Kos - Gedung Melati']); ?>
                        <label class="field"><span>Status gedung</span><select name="status"><option value="active" <?= ($building['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option><option value="inactive" <?= ($building['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Nonaktif</option></select><small class="field-help">Gedung nonaktif tidak tampil pada peta dan tidak dapat dipilih untuk kamar baru.</small></label>
                        <label class="field full-span"><span>Alamat lengkap *</span><textarea name="address" rows="3" required><?= e($building['address'] ?? '') ?></textarea></label>
                        <label class="field"><span>Kebijakan penghuni</span><select name="gender_policy"><option value="mixed" <?= ($building['gender_policy'] ?? 'mixed') === 'mixed' ? 'selected' : '' ?>>Campur</option><option value="male" <?= ($building['gender_policy'] ?? '') === 'male' ? 'selected' : '' ?>>Putra</option><option value="female" <?= ($building['gender_policy'] ?? '') === 'female' ? 'selected' : '' ?>>Putri</option></select></label>
                        <div class="building-map-editor full-span" id="lokasi-gedung">
                            <div class="building-coordinate-help"><strong>Atur lokasi kos</strong><p>Tempel tautan Google Maps untuk mengisi koordinat otomatis, atau isikan latitude dan longitude secara manual. Peta di halaman kos memakai titik koordinat ini.</p></div>
                            <label class="field"><span>Tautan Google Maps (opsional)</span><input type="url" id="google-maps-url" placeholder="https://maps.google.com/..."><small class="field-help" id="google-maps-status">Buka Google Maps, pilih titik kos, lalu salin tautannya ke sini.</small></label>
                            <div class="form-grid building-coordinate-fields">
                                <?php field('Latitude (opsional)', 'latitude', $building['latitude'] ?? '', 'number', ['id' => 'building-latitude', 'min' => '-90', 'max' => '90', 'step' => 'any', 'placeholder' => '-6.2000000']); field('Longitude (opsional)', 'longitude', $building['longitude'] ?? '', 'number', ['id' => 'building-longitude', 'min' => '-180', 'max' => '180', 'step' => 'any', 'placeholder' => '106.8166667']); ?>
                            </div>
                            <a class="text-link building-google-search" id="google-maps-search" href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode($building['address'] ?? '') ?>" target="_blank" rel="noopener noreferrer">Cari alamat ini di Google Maps ↗</a>
                        </div>
                        <label class="field full-span"><span>Deskripsi kos</span><textarea name="description" rows="4" maxlength="10000"><?= e($building['description'] ?? '') ?></textarea></label>
                        <label class="field full-span"><span>Fasilitas umum (pisahkan dengan koma atau baris baru)</span><textarea name="general_facilities" rows="3" maxlength="5000"><?= e($building['general_facilities'] ?? '') ?></textarea></label>
                        <label class="field full-span"><span>Peraturan kos</span><textarea name="house_rules" rows="3" maxlength="5000"><?= e($building['house_rules'] ?? '') ?></textarea></label>
                        <label class="field full-span"><span>Foto galeri kos (JPG, PNG, WebP; maks. 4 MB/foto)</span><input type="file" name="property_photos[]" accept="image/jpeg,image/png,image/webp" multiple><small class="field-help">Foto galeri ditampilkan sebagai foto tambahan. Unggah hingga 4 foto setiap kali menyimpan.</small></label>
                        <?php if ($buildingPhotos): ?><div class="property-photo-manager full-span"><strong>Foto yang sudah diunggah</strong><div class="property-photo-manager-grid"><?php foreach ($buildingPhotos as $photo): ?><article><img src="<?= e($photo['photo_path']) ?>" alt="Foto kos"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete_property_photo"><input type="hidden" name="building_id" value="<?= $buildingId ?>"><input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>"><button class="text-button" type="submit">Hapus foto</button></form></article><?php endforeach; ?></div></div><?php endif; ?>
                        <div class="form-actions full-span"><a class="button secondary" href="index.php?page=buildings">Batal</a><button class="button primary" type="submit">Simpan gedung</button></div>
                    </form>
                </div>
                <script>
                (() => {
                    const mapsUrl = document.getElementById('google-maps-url');
                    const latitude = document.getElementById('building-latitude');
                    const longitude = document.getElementById('building-longitude');
                    const status = document.getElementById('google-maps-status');
                    const searchLink = document.getElementById('google-maps-search');
                    if (!mapsUrl || !latitude || !longitude) return;
                    const updateSearchLink = () => {
                        const query = latitude.value && longitude.value
                            ? `${latitude.value},${longitude.value}`
                            : document.querySelector('[name="address"]').value;
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
                    mapsUrl.addEventListener('input', () => {
                        const coordinates = parseCoordinates(mapsUrl.value);
                        if (!coordinates) {
                            status.textContent = mapsUrl.value
                                ? 'Koordinat tidak terbaca dari tautan ini. Gunakan tautan Google Maps berisi pin, atau masukkan koordinat manual.'
                                : 'Buka Google Maps, pilih titik kos, lalu salin tautannya ke sini.';
                            return;
                        }
                        latitude.value = coordinates[0];
                        longitude.value = coordinates[1];
                        status.textContent = 'Koordinat berhasil diambil dari Google Maps.';
                        updateSearchLink();
                    });
                    [latitude, longitude, document.querySelector('[name="address"]')].forEach((field) => {
                        field.addEventListener('input', updateSearchLink);
                    });
                })();
                </script>
            