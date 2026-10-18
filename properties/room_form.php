<?php
                $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
                $record = $id ? one($pdo, 'SELECT * FROM rooms WHERE id=:id', ['id' => $id]) : null;
                if ($id && !$record) { set_flash('error', 'Kamar tidak ditemukan.'); redirect('rooms'); }
                if ($id && !can_manage_room($pdo, $user, $id)) {
                    set_flash('error', 'Anda tidak dapat melihat atau mengubah kamar milik kos lain.');
                    redirect('rooms');
                }
                $buildings = rows($pdo, "SELECT id, name, status FROM kos_buildings
                    WHERE (status='active' OR id=:current_building)
                      AND (:is_admin=1 OR owner_user_id=:owner_id)
                    ORDER BY status='active' DESC, name", [
                        'current_building' => $record['building_id'] ?? 0,
                        'is_admin' => $user['role'] === 'admin' ? 1 : 0,
                        'owner_id' => $user['id'],
                    ]);
                $roomDisplayStatus = 'available';
                if ($record) {
                    $roomState = one($pdo, "SELECT
                        EXISTS(SELECT 1 FROM rentals r WHERE r.room_id=rooms.id
                            AND ((r.status='active' AND (r.end_date IS NULL OR r.end_date>=CURDATE()))
                                OR (r.status='upcoming' AND r.start_date<=CURDATE()
                                    AND (r.end_date IS NULL OR r.end_date>=CURDATE())
                                    AND (r.booking_expires_at IS NULL OR r.booking_expires_at>NOW()
                                         OR EXISTS (SELECT 1 FROM invoices i JOIN payments p ON p.invoice_id=i.id
                                                    WHERE i.rental_id=r.id AND p.status='pending'))))) AS occupied,
                        EXISTS(SELECT 1 FROM rentals r WHERE r.room_id=rooms.id
                            AND r.status='upcoming'
                            AND (r.end_date IS NULL OR r.end_date>=CURDATE())
                            AND (r.booking_expires_at IS NULL OR r.booking_expires_at>NOW()
                                 OR EXISTS (SELECT 1 FROM invoices i JOIN payments p ON p.invoice_id=i.id
                                            WHERE i.rental_id=r.id AND p.status='pending'))) AS pending
                        FROM rooms WHERE id=:id", ['id' => $id]);
                    $roomDisplayStatus = $record['status'] === 'maintenance'
                        ? 'maintenance'
                        : ((int) $roomState['occupied'] ? 'occupied' : ((int) $roomState['pending'] ? 'pending' : 'available'));
                }
                $roomPhotoList = $record
                    ? rows($pdo, 'SELECT id, category, photo_path FROM room_photos WHERE room_id=:id ORDER BY (category = \'room\') DESC, id', ['id' => $id])
                    : [];
                $roomTypeGalleryPhotos = $record
                    ? rows(
                        $pdo,
                        "SELECT id,photo_path FROM room_type_gallery_photos
                         WHERE building_id=:building_id AND room_type=:room_type AND category='room'
                         ORDER BY id",
                        ['building_id' => $record['building_id'], 'room_type' => $record['room_type']]
                    )
                    : [];
                $firstRoomPhoto = null;
                foreach ($roomPhotoList as $roomPhoto) {
                    if ($roomPhoto['category'] === 'room') {
                        $firstRoomPhoto = $roomPhoto;
                        break;
                    }
                }
            ?>
                <div class="form-panel"><div class="panel-heading"><div><h2><?= $record ? 'Edit kamar' : 'Tambah unit kamar' ?></h2><p><?= $record ? 'Perbarui data unit kamar dan foto fasilitasnya.' : 'Buat beberapa unit bertipe sama sekaligus. Sistem melewati nomor kamar yang sudah dipakai.' ?></p></div><a class="text-link" href="index.php?page=rooms">← Kembali</a></div>
                <?php if (!$buildings): ?><div class="alert error">Tambahkan gedung kos yang aktif sebelum membuat data kamar. <a class="text-link" href="index.php?page=building_form">Tambah gedung</a></div><?php endif; ?>
                <?php if ($record): ?>
                    <section class="profile-image-editor">
                        <div><strong>Foto profil kamar</strong><small>Foto ini menjadi sampul kamar di halaman depan dan foto utama pada detail kamar.</small></div>
                        <?php if (!empty($record['profile_photo_path'])): ?><img class="profile-image-editor-preview" src="<?= e($record['profile_photo_path']) ?>" alt="Foto profil kamar <?= e($record['room_number']) ?>"><?php elseif ($firstRoomPhoto): ?><img class="profile-image-editor-preview" src="<?= e($firstRoomPhoto['photo_path']) ?>" alt="Foto galeri sampul kamar <?= e($record['room_number']) ?>"><small class="field-help">Foto kamar pertama di galeri saat ini digunakan sebagai sampul. Unggah foto untuk menetapkan foto profil khusus.</small><?php else: ?><div class="profile-image-editor-empty">Belum ada foto profil kamar</div><?php endif; ?>
                        <form method="post" enctype="multipart/form-data" class="profile-image-editor-form"><?= csrf_field() ?><input type="hidden" name="action" value="update_room_profile_photo"><input type="hidden" name="room_id" value="<?= $id ?>">
                            <div class="photo-upload-control profile-photo-upload" data-photo-upload data-photo-upload-saved="0" data-photo-upload-limit="1" data-photo-upload-idle-label="<?= !empty($record['profile_photo_path']) ? 'Foto profil saat ini tersimpan' : 'Belum ada foto profil khusus' ?>">
                                <input class="visually-hidden" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" data-photo-upload-input>
                                <button class="button secondary" type="button" data-photo-upload-trigger>Upload foto profil</button>
                                <small class="field-help" data-photo-upload-count><?= !empty($record['profile_photo_path']) ? 'Foto profil saat ini tersimpan' : 'Belum ada foto profil khusus' ?></small>
                                <small class="field-help" data-photo-upload-status>Pilih JPG, PNG, atau WebP; maksimal 4 MB.</small>
                                <div class="photo-upload-preview-grid" data-photo-upload-grid></div>
                            </div>
                            <div class="profile-image-editor-actions"><button class="button primary" type="submit">Simpan foto profil</button><?php if (!empty($record['profile_photo_path'])): ?><button class="button secondary" type="submit" name="remove_profile_photo" value="yes" formnovalidate onclick="this.form.querySelector('[name=profile_photo]').value='';">Hapus foto profil</button><?php endif; ?></div>
                        </form>
                    </section>
                <?php endif; ?>
                <?php if ($roomPhotoList): ?><div class="room-photo-manager"><div class="room-photo-manager-heading"><strong>Foto yang sudah diunggah</strong><span>Foto kamar pertama menjadi sampul di halaman publik.</span></div><div class="room-photo-manager-grid">
                    <?php foreach ($roomPhotoList as $photo): ?><article class="room-photo-manager-card"><img src="<?= e($photo['photo_path']) ?>" alt="<?= $photo['category'] === 'room' ? 'Foto kamar' : 'Foto fasilitas' ?>"><div class="room-photo-manager-info"><span><?= $photo['category'] === 'room' ? 'Foto kamar' : 'Foto fasilitas' ?></span><button class="text-button" type="button" data-dialog-open="delete-room-photo-<?= (int) $photo['id'] ?>">Hapus</button></div>
                        <dialog class="action-dialog" id="delete-room-photo-<?= (int) $photo['id'] ?>"><form method="post" class="action-dialog-form"><?= csrf_field() ?><input type="hidden" name="action" value="delete_room_photo"><input type="hidden" name="room_id" value="<?= $id ?>"><input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>"><button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button><span class="dialog-eyebrow">KEAMANAN FOTO KAMAR</span><h2>Hapus foto?</h2><p>Foto ini akan dihapus dari kamar <?= e($record['room_number']) ?>. Tindakan ini tidak dapat dibatalkan.</p><label class="confirmation-check"><input type="checkbox" name="confirm_delete_photo" value="yes" required><span>Saya memahami foto ini akan dihapus dari kamar.</span></label><div class="dialog-actions"><button class="button secondary" type="button" data-dialog-close>Batal</button><button class="button danger" type="submit">Hapus foto</button></div></form></dialog>
                    </article><?php endforeach; ?>
                </div></div><?php endif; ?>
                <?php if ($roomTypeGalleryPhotos): ?><section class="room-type-gallery-selection">
                    <div class="room-photo-manager-heading"><strong>Foto bersama galeri <?= e($record['room_type']) ?></strong><a class="text-link" href="index.php?page=gallery&amp;building_id=<?= (int) $record['building_id'] ?>&amp;room_type=<?= rawurlencode($record['room_type']) ?>">Kelola galeri ↗</a></div>
                    <div class="room-photo-manager-grid">
                        <?php foreach ($roomTypeGalleryPhotos as $galleryPhoto): ?>
                            <article class="room-photo-manager-card <?= ($record['profile_photo_path'] ?? null) === $galleryPhoto['photo_path'] ? 'is-profile-photo' : '' ?>">
                                <img src="<?= e($galleryPhoto['photo_path']) ?>" alt="Foto bersama <?= e($record['room_type']) ?>">
                                <div class="room-photo-manager-info"><span><?= ($record['profile_photo_path'] ?? null) === $galleryPhoto['photo_path'] ? 'Foto profil saat ini' : 'Foto galeri bersama' ?></span>
                                    <?php if (($record['profile_photo_path'] ?? null) !== $galleryPhoto['photo_path']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="select_room_gallery_photo"><input type="hidden" name="building_id" value="<?= (int) $record['building_id'] ?>"><input type="hidden" name="room_type" value="<?= e($record['room_type']) ?>"><input type="hidden" name="room_id" value="<?= $id ?>"><input type="hidden" name="photo_id" value="<?= (int) $galleryPhoto['id'] ?>"><button class="room-profile-pick-button" type="submit"><span aria-hidden="true">☆</span> Jadikan foto profil</button></form><?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section><?php endif; ?>
                <?php if ($buildings): ?><form method="post" enctype="multipart/form-data" class="form-grid room-editor-form"><?= csrf_field() ?><input type="hidden" name="action" value="save_room"><input type="hidden" name="id" value="<?= $id ?>">
                    <label class="field full-span"><span>Nama kos *</span><select name="building_id" required><option value="">Pilih kos</option><?php foreach ($buildings as $buildingOption): ?><option value="<?= (int) $buildingOption['id'] ?>" <?= (int) ($record['building_id'] ?? 0) === (int) $buildingOption['id'] ? 'selected' : '' ?>><?= e($buildingOption['name']) ?><?= $buildingOption['status'] === 'inactive' ? ' (nonaktif - pindahkan kamar ke kos aktif)' : '' ?></option><?php endforeach; ?></select></label>
                    <?php if (!$record): ?>
                        <?php field('Nomor awal kamar *', 'room_number', '', 'text', ['required' => 'required', 'maxlength' => '30', 'placeholder' => 'A01']); field('Jumlah unit *', 'quantity', '1', 'number', ['required' => 'required', 'min' => '1', 'max' => '100']); ?>
                    <?php else: ?>
                        <?php field('Nomor kamar *', 'room_number', $record['room_number'] ?? '', 'text', ['required' => 'required', 'maxlength' => '30']); field('Lantai', 'floor', $record['floor'] ?? ''); ?>
                    <?php endif; ?>
                    <label class="field"><span>Tipe kamar *</span><select name="room_type" required><?php foreach (['Tipe Standart', 'Tipe Premium', 'Tipe Eksklusif'] as $roomType): ?><option value="<?= e($roomType) ?>" <?= ($record['room_type'] ?? 'Tipe Standart') === $roomType ? 'selected' : '' ?>><?= e($roomType) ?></option><?php endforeach; ?></select></label><?php field('Tarif per unit per bulan (Rp) *', 'monthly_rate', $record['monthly_rate'] ?? '', 'number', ['required' => 'required', 'min' => '0', 'step' => '1000']); ?>
                    <?php if ($record): ?><label class="field"><span>Status kamar</span><select name="status"><?php foreach (['available', 'occupied', 'pending', 'maintenance'] as $option): ?><option value="<?= e($option) ?>" <?= $roomDisplayStatus === $option ? 'selected' : '' ?> <?= in_array($option, ['occupied', 'pending'], true) ? 'disabled' : '' ?>><?= e(status_label($option)) ?><?= in_array($option, ['occupied', 'pending'], true) ? ' (otomatis dari data sewa)' : '' ?></option><?php endforeach; ?></select><small class="field-help">Status terisi mengikuti data sewa; status perawatan dapat dipilih manual.</small></label><?php endif; ?><label class="field"><span>Fasilitas</span><input name="facilities" value="<?= e($record['facilities'] ?? '') ?>" placeholder="Contoh: AC, Wi-Fi, kamar mandi dalam"><small class="field-help">Pisahkan setiap fasilitas dengan koma.</small></label>
                    <?php if ($record): ?>
                    <section class="photo-upload-panel full-span"><div class="photo-upload-heading"><span class="photo-upload-icon">▧</span><div><strong>Foto kamar (opsional)</strong><small>Jika tidak ada foto, KosKosan menampilkan ilustrasi kamar bawaan.</small></div></div><div class="photo-upload-control room-form-upload" data-photo-upload data-photo-upload-saved="<?= count(array_filter($roomPhotoList, static fn(array $photo): bool => $photo['category'] === 'room')) ?>" data-photo-upload-limit="4"><input class="visually-hidden" type="file" name="room_photos[]" accept="image/jpeg,image/png,image/webp" multiple data-photo-upload-input><button class="button secondary" type="button" data-photo-upload-trigger>Upload foto kamar</button><small class="field-help" data-photo-upload-count><?= count(array_filter($roomPhotoList, static fn(array $photo): bool => $photo['category'] === 'room')) ?> foto tersimpan</small><small class="field-help" data-photo-upload-status>Pilih hingga 4 foto, masing-masing maksimal 4 MB.</small><div class="photo-upload-preview-grid" data-photo-upload-grid></div></div></section>
                    <section class="photo-upload-panel full-span"><div class="photo-upload-heading"><span class="photo-upload-icon facility">✳</span><div><strong>Foto fasilitas</strong><small>Tambahkan foto fasilitas kamar, seperti kamar mandi, AC, atau area bersama.</small></div></div><div class="photo-upload-control room-form-upload" data-photo-upload data-photo-upload-saved="<?= count(array_filter($roomPhotoList, static fn(array $photo): bool => $photo['category'] === 'facility')) ?>" data-photo-upload-limit="4"><input class="visually-hidden" type="file" name="facility_photos[]" accept="image/jpeg,image/png,image/webp" multiple data-photo-upload-input><button class="button secondary" type="button" data-photo-upload-trigger>Upload foto fasilitas</button><small class="field-help" data-photo-upload-count><?= count(array_filter($roomPhotoList, static fn(array $photo): bool => $photo['category'] === 'facility')) ?> foto tersimpan</small><small class="field-help" data-photo-upload-status>Pilih hingga 4 foto, masing-masing maksimal 4 MB.</small><div class="photo-upload-preview-grid" data-photo-upload-grid></div></div></section>
                    <?php endif; ?>
                    <label class="field full-span"><span>Catatan</span><textarea name="notes" rows="3"><?= e($record['notes'] ?? '') ?></textarea></label><div class="form-actions full-span"><a class="button secondary" href="index.php?page=rooms">Batal</a><button class="button primary" type="submit"><?= $record ? 'Simpan perubahan' : 'Buat unit kamar' ?></button></div>
                </form><?php endif; ?></div>
            