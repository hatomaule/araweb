<?php
declare(strict_types=1);

function handle_property_action(PDO $pdo, array $user, string $action): void
{
if ($action === 'upload_room_type_gallery_photos') {
            if (!in_array($user['role'], ['admin', 'operator'], true)) {
                set_flash('error', 'Anda tidak memiliki akses mengelola galeri foto.');
                redirect('home');
            }
            $buildingId = (int) post_value('building_id');
            $roomType = post_value('room_type');
            $returnToGallery = 'gallery&building_id=' . $buildingId . '&room_type=' . rawurlencode($roomType);
            if (!can_manage_building($pdo, $user, $buildingId)) {
                set_flash('error', 'Anda tidak dapat mengelola galeri gedung tersebut.');
                redirect('gallery');
            }
            if (!one($pdo, 'SELECT id FROM rooms WHERE building_id=:building_id AND room_type=:room_type LIMIT 1', [
                'building_id' => $buildingId,
                'room_type' => $roomType,
            ])) {
                set_flash('error', 'Pilih tipe kamar galeri yang valid.');
                redirect('gallery&building_id=' . $buildingId);
            }
            $categoryUploads = [];
            foreach (['building', 'room', 'room_facility', 'general_facility'] as $category) {
                $uploads = collect_photo_uploads('gallery_photos_' . $category);
                if ($uploads) {
                    $categoryUploads[$category] = $uploads;
                }
            }
            if (!$categoryUploads) {
                set_flash('error', 'Pilih minimal satu foto untuk diunggah.');
                redirect($returnToGallery);
            }
            $movedFiles = [];
            $uploadedCount = 0;
            $pdo->beginTransaction();
            try {
                $insert = $pdo->prepare(
                    'INSERT INTO room_type_gallery_photos (building_id,room_type,category,photo_path)
                     VALUES (:building_id,:room_type,:category,:photo_path)'
                );
                foreach ($categoryUploads as $category => $uploads) {
                    $uploadSubdirectory = $category === 'building' ? 'properties' : 'rooms';
                    $uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads'
                        . DIRECTORY_SEPARATOR . $uploadSubdirectory;
                    if (!is_dir($uploadDirectory)
                        && !mkdir($uploadDirectory, 0755, true)
                        && !is_dir($uploadDirectory)) {
                        throw new RuntimeException('Folder penyimpanan galeri tidak dapat dibuat. Periksa izin folder uploads.');
                    }
                    foreach ($uploads as $upload) {
                        $filename = bin2hex(random_bytes(16)) . '.' . $upload['extension'];
                        $filePath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
                        if (!move_uploaded_file($upload['tmp_name'], $filePath)) {
                            throw new RuntimeException('Foto gagal disimpan. Periksa izin folder uploads/' . $uploadSubdirectory . '.');
                        }
                        $movedFiles[] = $filePath;
                        $insert->execute([
                            'building_id' => $buildingId,
                            'room_type' => $roomType,
                            'category' => $category,
                            'photo_path' => 'uploads/' . $uploadSubdirectory . '/' . $filename,
                        ]);
                        $uploadedCount++;
                    }
                }
                if (isset($categoryUploads['building'])) {
                    $firstBuildingPhoto = one(
                        $pdo,
                        "SELECT photo_path FROM room_type_gallery_photos
                         WHERE building_id=:building_id AND category='building' ORDER BY id LIMIT 1",
                        ['building_id' => $buildingId]
                    );
                    $pdo->prepare(
                        'UPDATE kos_buildings SET profile_photo_path=:photo_path
                         WHERE id=:building_id AND profile_photo_path IS NULL'
                    )->execute([
                        'photo_path' => $firstBuildingPhoto['photo_path'],
                        'building_id' => $buildingId,
                    ]);
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                foreach ($movedFiles as $movedFile) {
                    if (is_file($movedFile) && !unlink($movedFile)) {
                        error_log('Unable to remove uncommitted gallery photo: ' . $movedFile);
                    }
                }
                throw $exception;
            }
            set_flash('success', $uploadedCount . ' foto berhasil disimpan ke galeri ' . $roomType . '.');
            redirect($returnToGallery);
        }

        if ($action === 'select_room_gallery_photo') {
            if (!in_array($user['role'], ['admin', 'operator'], true)) {
                set_flash('error', 'Anda tidak memiliki akses mengubah foto kamar.');
                redirect('home');
            }
            $buildingId = (int) post_value('building_id');
            $roomId = (int) post_value('room_id');
            $photoId = (int) post_value('photo_id');
            $roomType = post_value('room_type');
            $returnToGallery = 'gallery&building_id=' . $buildingId . '&room_type=' . rawurlencode($roomType);
            if (!can_manage_building($pdo, $user, $buildingId) || !can_manage_room($pdo, $user, $roomId)) {
                set_flash('error', 'Anda tidak dapat mengubah foto kamar tersebut.');
                redirect('gallery');
            }
            $photo = one(
                $pdo,
                "SELECT photo_path FROM room_type_gallery_photos
                 WHERE id=:photo_id AND building_id=:building_id
                   AND room_type=:room_type AND category='room'",
                ['photo_id' => $photoId, 'building_id' => $buildingId, 'room_type' => $roomType]
            );
            $room = one(
                $pdo,
                'SELECT id FROM rooms WHERE id=:room_id AND building_id=:building_id AND room_type=:room_type',
                ['room_id' => $roomId, 'building_id' => $buildingId, 'room_type' => $roomType]
            );
            if (!$photo || !$room) {
                set_flash('error', 'Foto atau kamar tidak sesuai dengan tipe galeri yang dipilih.');
                redirect($returnToGallery);
            }
            $pdo->prepare('UPDATE rooms SET profile_photo_path=:photo_path WHERE id=:room_id')
                ->execute(['photo_path' => $photo['photo_path'], 'room_id' => $roomId]);
            set_flash('success', 'Foto galeri diterapkan sebagai foto profil kamar.');
            redirect($returnToGallery);
        }

        if ($action === 'delete_room_type_gallery_photo') {
            if (!in_array($user['role'], ['admin', 'operator'], true)) {
                set_flash('error', 'Anda tidak memiliki akses menghapus foto galeri.');
                redirect('home');
            }
            $buildingId = (int) post_value('building_id');
            $photoId = (int) post_value('photo_id');
            $roomType = post_value('room_type');
            $returnToGallery = 'gallery&building_id=' . $buildingId . '&room_type=' . rawurlencode($roomType);
            if (!can_manage_building($pdo, $user, $buildingId)) {
                set_flash('error', 'Anda tidak dapat mengelola galeri gedung tersebut.');
                redirect('gallery');
            }
            $photo = one(
                $pdo,
                'SELECT id,room_type,category,photo_path FROM room_type_gallery_photos
                 WHERE id=:photo_id AND building_id=:building_id AND room_type=:room_type',
                ['photo_id' => $photoId, 'building_id' => $buildingId, 'room_type' => $roomType]
            );
            if (!$photo) {
                set_flash('error', 'Foto galeri tidak ditemukan.');
                redirect($returnToGallery);
            }
            $replacementPhoto = null;
            if ($photo['category'] === 'building') {
                $replacementPhoto = one(
                    $pdo,
                    "SELECT photo_path FROM room_type_gallery_photos
                     WHERE building_id=:building_id AND category='building' AND id<>:photo_id
                     ORDER BY id LIMIT 1",
                    ['building_id' => $buildingId, 'photo_id' => $photoId]
                );
                if (!$replacementPhoto) {
                    $replacementPhoto = one(
                        $pdo,
                        'SELECT photo_path FROM property_photos WHERE building_id=:building_id ORDER BY display_order,id LIMIT 1',
                        ['building_id' => $buildingId]
                    );
                }
            } elseif ($photo['category'] === 'room') {
                $replacementPhoto = one(
                    $pdo,
                    "SELECT photo_path FROM room_type_gallery_photos
                     WHERE building_id=:building_id AND room_type=:room_type AND category='room' AND id<>:photo_id
                     ORDER BY id LIMIT 1",
                    ['building_id' => $buildingId, 'room_type' => $roomType, 'photo_id' => $photoId]
                );
            }
            $uploadSubdirectory = $photo['category'] === 'building' ? 'properties' : 'rooms';
            $pdo->beginTransaction();
            try {
                if ($photo['category'] === 'building') {
                    $pdo->prepare(
                        'UPDATE kos_buildings SET profile_photo_path=:replacement
                         WHERE id=:building_id AND profile_photo_path=:photo_path'
                    )->execute([
                        'replacement' => $replacementPhoto['photo_path'] ?? null,
                        'building_id' => $buildingId,
                        'photo_path' => $photo['photo_path'],
                    ]);
                } elseif ($photo['category'] === 'room') {
                    $pdo->prepare(
                        'UPDATE rooms SET profile_photo_path=:replacement
                         WHERE building_id=:building_id AND room_type=:room_type AND profile_photo_path=:photo_path'
                    )->execute([
                        'replacement' => $replacementPhoto['photo_path'] ?? null,
                        'building_id' => $buildingId,
                        'room_type' => $roomType,
                        'photo_path' => $photo['photo_path'],
                    ]);
                }
                $pdo->prepare('DELETE FROM room_type_gallery_photos WHERE id=:id')->execute(['id' => $photoId]);
                delete_uploaded_image(
                    $photo['photo_path'],
                    dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $uploadSubdirectory,
                    'foto galeri'
                );
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            set_flash('success', 'Foto galeri berhasil dihapus.');
            redirect($returnToGallery);
        }

if ($action === 'update_building_profile_photo') {
            $buildingId = (int) post_value('building_id');
            if (!can_manage_building($pdo, $user, $buildingId)) {
                set_flash('error', 'Anda tidak dapat mengelola foto kos tersebut.');
                redirect('buildings');
            }
            $building = one($pdo, 'SELECT profile_photo_path FROM kos_buildings WHERE id=:id', ['id' => $buildingId]);
            if (!$building) {
                set_flash('error', 'Data gedung tidak ditemukan.');
                redirect('buildings');
            }
            $upload = validate_image_upload('profile_photo', 'Foto profil kos');
            $removeRequested = post_value('remove_profile_photo') === 'yes';
            if (!$upload && !$removeRequested) {
                set_flash('error', 'Pilih foto profil kos untuk diunggah.');
                redirect('building_form&id=' . $buildingId);
            }

            $newPath = null;
            if ($upload) {
                $uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads'
                    . DIRECTORY_SEPARATOR . 'properties';
                if (!is_dir($uploadDirectory)
                    && !mkdir($uploadDirectory, 0755, true)
                    && !is_dir($uploadDirectory)) {
                    throw new RuntimeException('Folder foto kos tidak dapat dibuat. Periksa izin folder uploads/properties.');
                }
                $filename = bin2hex(random_bytes(16)) . '.' . $upload['extension'];
                $filePath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
                if (!move_uploaded_file($upload['tmp_name'], $filePath)) {
                    throw new RuntimeException('Foto profil kos gagal disimpan. Periksa izin folder uploads/properties.');
                }
                $newPath = 'uploads/properties/' . $filename;
            }

            try {
                $pdo->prepare('UPDATE kos_buildings SET profile_photo_path=:path WHERE id=:id')
                    ->execute(['path' => $newPath, 'id' => $buildingId]);
            } catch (Throwable $exception) {
                if ($newPath && is_file($filePath) && !unlink($filePath)) {
                    error_log('Unable to remove uncommitted property profile photo: ' . $filePath);
                }
                throw $exception;
            }
            if ($building['profile_photo_path'] && $building['profile_photo_path'] !== $newPath) {
                try {
                    delete_uploaded_image(
                        $building['profile_photo_path'],
                        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'properties',
                        'foto profil kos'
                    );
                } catch (RuntimeException $exception) {
                    error_log('Unable to remove replaced property profile photo: ' . $exception->getMessage());
                    set_flash('error', 'Foto profil baru tersimpan, tetapi foto lama tidak dapat dibersihkan. Periksa log server.');
                    redirect('building_form&id=' . $buildingId);
                }
            }
            set_flash('success', $newPath ? 'Foto profil kos berhasil diperbarui.' : 'Foto profil kos berhasil dihapus.');
            redirect('building_form&id=' . $buildingId);
        }

        if ($action === 'update_room_profile_photo') {
            $roomId = (int) post_value('room_id');
            if (!can_manage_room($pdo, $user, $roomId)) {
                set_flash('error', 'Anda tidak dapat mengelola foto kamar tersebut.');
                redirect('rooms');
            }
            $room = one($pdo, 'SELECT profile_photo_path FROM rooms WHERE id=:id', ['id' => $roomId]);
            if (!$room) {
                set_flash('error', 'Kamar tidak ditemukan.');
                redirect('rooms');
            }
            $upload = validate_image_upload('profile_photo', 'Foto profil kamar');
            $removeRequested = post_value('remove_profile_photo') === 'yes';
            if (!$upload && !$removeRequested) {
                set_flash('error', 'Pilih foto profil kamar untuk diunggah.');
                redirect('room_form&id=' . $roomId);
            }

            $newPath = null;
            if ($upload) {
                $uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads'
                    . DIRECTORY_SEPARATOR . 'rooms';
                if (!is_dir($uploadDirectory)
                    && !mkdir($uploadDirectory, 0755, true)
                    && !is_dir($uploadDirectory)) {
                    throw new RuntimeException('Folder foto kamar tidak dapat dibuat. Periksa izin folder uploads/rooms.');
                }
                $filename = bin2hex(random_bytes(16)) . '.' . $upload['extension'];
                $filePath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
                if (!move_uploaded_file($upload['tmp_name'], $filePath)) {
                    throw new RuntimeException('Foto profil kamar gagal disimpan. Periksa izin folder uploads/rooms.');
                }
                $newPath = 'uploads/rooms/' . $filename;
            }

            try {
                $pdo->prepare('UPDATE rooms SET profile_photo_path=:path WHERE id=:id')
                    ->execute(['path' => $newPath, 'id' => $roomId]);
            } catch (Throwable $exception) {
                if ($newPath && is_file($filePath) && !unlink($filePath)) {
                    error_log('Unable to remove uncommitted room profile photo: ' . $filePath);
                }
                throw $exception;
            }
            if ($room['profile_photo_path'] && $room['profile_photo_path'] !== $newPath) {
                try {
                    delete_uploaded_image(
                        $room['profile_photo_path'],
                        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'rooms',
                        'foto profil kamar'
                    );
                } catch (RuntimeException $exception) {
                    error_log('Unable to remove replaced room profile photo: ' . $exception->getMessage());
                    set_flash('error', 'Foto profil baru tersimpan, tetapi foto lama tidak dapat dibersihkan. Periksa log server.');
                    redirect('room_form&id=' . $roomId);
                }
            }
            set_flash('success', $newPath ? 'Foto profil kamar berhasil diperbarui.' : 'Foto profil kamar berhasil dihapus.');
            redirect('room_form&id=' . $roomId);
        }

if ($action === 'save_building') {
            if (!in_array($user['role'], ['admin', 'operator'], true)) {
                set_flash('error', 'Anda tidak memiliki akses mengelola data gedung.');
                redirect('my_bookings');
            }
            $id = (int) post_value('id');
            $name = post_value('name');
            $address = post_value('address');
            $latitude = post_value('latitude');
            $longitude = post_value('longitude');
            $coordinatesProvided = $latitude !== '' || $longitude !== '';
            if ($name === '' || strlen($name) > 150 || $address === ''
                || ($coordinatesProvided && (!is_numeric($latitude) || !is_numeric($longitude)
                    || (float) $latitude < -90 || (float) $latitude > 90
                    || (float) $longitude < -180 || (float) $longitude > 180))) {
                set_flash('error', 'Isi nama dan alamat. Koordinat harus diisi berpasangan dan berada dalam rentang yang valid.');
                redirect('building_form' . ($id ? '&id=' . $id : ''));
            }
            if ($id && !can_manage_building($pdo, $user, $id)) {
                set_flash('error', 'Anda hanya dapat mengelola kos milik akun Anda.');
                redirect('buildings');
            }
            $status = $user['role'] === 'admin' ? (post_value('status') ?: 'active') : 'active';
            if (!in_array($status, ['active', 'inactive'], true)) {
                set_flash('error', 'Status gedung tidak valid.');
                redirect('building_form' . ($id ? '&id=' . $id : ''));
            }
            $photos = collect_photo_uploads('property_photos');
            $values = [
                'name' => $name, 'address' => $address,
                'latitude' => $coordinatesProvided ? number_format((float) $latitude, 7, '.', '') : null,
                'longitude' => $coordinatesProvided ? number_format((float) $longitude, 7, '.', '') : null,
                'description' => post_value('description') ?: null,
                'gender_policy' => in_array(post_value('gender_policy'), ['mixed', 'male', 'female'], true)
                    ? post_value('gender_policy') : 'mixed',
                'general_facilities' => post_value('general_facilities') ?: null,
                'house_rules' => post_value('house_rules') ?: null,
                'status' => $status,
            ];
            if ($id > 0) {
                $values['id'] = $id;
                $approvalSql = $user['role'] === 'operator'
                    ? ", approval_status='approved', approval_note=NULL, reviewed_by=NULL, reviewed_at=NOW()"
                    : '';
                $building = $pdo->prepare(
                    'UPDATE kos_buildings SET name=:name, address=:address, latitude=:latitude,
                     longitude=:longitude, description=:description, gender_policy=:gender_policy,
                     general_facilities=:general_facilities, house_rules=:house_rules, status=:status'
                     . $approvalSql . ' WHERE id=:id'
                );
            } else {
                $values['owner_user_id'] = $user['role'] === 'operator' ? $user['id'] : null;
                $building = $pdo->prepare(
                    "INSERT INTO kos_buildings
                     (owner_user_id, name, address, latitude, longitude, description, gender_policy,
                      general_facilities, house_rules, status, approval_status)
                     VALUES (:owner_user_id, :name, :address, :latitude, :longitude, :description,
                      :gender_policy, :general_facilities, :house_rules, :status, 'approved')"
                );
            }
            $building->execute($values);
            if ($id === 0) {
                $id = (int) $pdo->lastInsertId();
            }
            foreach ($photos as $photo) {
                $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'properties';
                if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                    throw new RuntimeException('Folder foto properti tidak dapat dibuat.');
                }
                $filename = bin2hex(random_bytes(16)) . '.' . $photo['extension'];
                if (!move_uploaded_file($photo['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
                    throw new RuntimeException('Foto properti gagal disimpan.');
                }
                $pdo->prepare(
                    'INSERT INTO property_photos (building_id, photo_path, display_order)
                     VALUES (:building_id, :photo_path,
                       (SELECT COALESCE(MAX(photo_order), -1) + 1 FROM
                         (SELECT display_order AS photo_order FROM property_photos WHERE building_id=:building_id_2) p))'
                )->execute([
                    'building_id' => $id, 'photo_path' => 'uploads/properties/' . $filename,
                    'building_id_2' => $id,
                ]);
            }
            set_flash('success', $user['role'] === 'operator'
                ? 'Data kos disimpan dan dikirim untuk persetujuan admin platform.'
                : 'Data gedung kos berhasil disimpan.');
            redirect('buildings');
        }

        if ($action === 'building_status') {
            if (!in_array($user['role'], ['admin', 'operator'], true)) {
                set_flash('error', 'Anda tidak memiliki akses mengelola data gedung.');
                redirect('my_bookings');
            }
            $buildingId = (int) post_value('id');
            if (!can_manage_building($pdo, $user, $buildingId)) {
                set_flash('error', 'Anda hanya dapat mengelola kos milik akun Anda.');
                redirect('buildings');
            }
            $status = post_value('status');
            if (!in_array($status, ['active', 'inactive'], true)) {
                set_flash('error', 'Status gedung tidak valid.');
                redirect('buildings');
            }
            $building = $pdo->prepare('UPDATE kos_buildings SET status=:status WHERE id=:id');
            $building->execute(['status' => $status, 'id' => $buildingId]);
            set_flash('success', 'Status gedung diperbarui.');
            redirect('buildings');
        }

        if ($action === 'delete_property_photo') {
            $buildingId = (int) post_value('building_id');
            $photoId = (int) post_value('photo_id');
            if (!can_manage_building($pdo, $user, $buildingId)) {
                set_flash('error', 'Anda tidak dapat mengelola foto kos tersebut.');
                redirect('buildings');
            }
            $photo = one(
                $pdo,
                'SELECT id,photo_path FROM property_photos WHERE id=:photo_id AND building_id=:building_id',
                ['photo_id' => $photoId, 'building_id' => $buildingId]
            );
            if (!$photo) {
                set_flash('error', 'Foto properti tidak ditemukan.');
                redirect('building_form&id=' . $buildingId);
            }
            $photoDirectory = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'properties');
            $photoFile = $photoDirectory === false
                ? false
                : realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $photo['photo_path']));
            if ($photoFile === false || dirname($photoFile) !== $photoDirectory) {
                throw new RuntimeException('Lokasi berkas foto tidak valid; foto tidak dihapus.');
            }
            $pdo->prepare(
                'UPDATE kos_buildings SET profile_photo_path=NULL
                 WHERE id=:building_id AND profile_photo_path=:photo_path'
            )->execute(['building_id' => $buildingId, 'photo_path' => $photo['photo_path']]);
            if (!unlink($photoFile)) {
                throw new RuntimeException('Foto tidak dapat dihapus. Periksa izin folder uploads/properties.');
            }
            $pdo->prepare('DELETE FROM property_photos WHERE id=:id')->execute(['id' => $photoId]);
            set_flash('success', 'Foto properti berhasil dihapus.');
            redirect('building_form&id=' . $buildingId);
        }

        if ($user['role'] === 'seeker') {
            set_flash('error', 'Fitur tersebut hanya tersedia untuk pengelola kos.');
            redirect('home');
        }

        if ($action === 'save_tenant') {
            if ($user['role'] !== 'admin') {
                set_flash('error', 'Data penyewa hanya dapat dikelola melalui booking kos milik Anda.');
                redirect('dashboard');
            }
            $id = (int) post_value('id');
            $name = post_value('full_name');
            $phone = post_value('phone');
            $email = post_value('email');
            $identity = post_value('identity_number');
            if ($name === '' || strlen($name) > 150 || $phone === '' || strlen($phone) > 30
                || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                set_flash('error', 'Nama dan nomor telepon wajib diisi; periksa format email.');
                redirect('tenant_form' . ($id ? '&id=' . $id : ''));
            }

            $data = [
                'full_name' => $name, 'phone' => $phone,
                'email' => $email !== '' ? $email : null,
                'identity_number' => $identity !== '' ? $identity : null,
                'address' => post_value('address') ?: null,
                'emergency_contact_name' => post_value('emergency_contact_name') ?: null,
                'emergency_contact_phone' => post_value('emergency_contact_phone') ?: null,
                'notes' => post_value('notes') ?: null,
            ];
            if ($id > 0) {
                $data['id'] = $id;
                $statement = $pdo->prepare(
                    'UPDATE tenants SET full_name=:full_name, phone=:phone, email=:email, identity_number=:identity_number, address=:address, emergency_contact_name=:emergency_contact_name, emergency_contact_phone=:emergency_contact_phone, notes=:notes WHERE id=:id'
                );
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO tenants (full_name, phone, email, identity_number, address, emergency_contact_name, emergency_contact_phone, notes) VALUES (:full_name, :phone, :email, :identity_number, :address, :emergency_contact_name, :emergency_contact_phone, :notes)'
                );
            }
            $statement->execute($data);
            set_flash('success', 'Data pelanggan berhasil disimpan.');
            redirect('tenants');
        }

        if ($action === 'tenant_status') {
            if ($user['role'] !== 'admin') {
                set_flash('error', 'Data penyewa hanya dapat dikelola oleh admin platform.');
                redirect('dashboard');
            }
            $statement = $pdo->prepare('UPDATE tenants SET status = :status WHERE id = :id');
            $statement->execute(['status' => post_value('status'), 'id' => (int) post_value('id')]);
            set_flash('success', 'Status pelanggan diperbarui.');
            redirect('tenants');
        }

        if ($action === 'save_room') {
            $id = (int) post_value('id');
            $number = post_value('room_number');
            $rate = post_value('monthly_rate');
            $buildingId = (int) post_value('building_id');
            $quantity = $id > 0 ? 1 : (int) post_value('quantity');
            $roomUploads = collect_photo_uploads('room_photos');
            $facilityUploads = collect_photo_uploads('facility_photos');
            $roomType = post_value('room_type');
            if (!$buildingId || $number === '' || strlen($number) > 30 || !is_numeric($rate) || (float) $rate < 0
                || !in_array($roomType, ['Tipe Standart', 'Tipe Premium', 'Tipe Eksklusif'], true)
                || $quantity < 1 || $quantity > 100) {
                set_flash('error', 'Pilih gedung, nomor awal, tipe, tarif yang valid, dan jumlah 1 sampai 100 kamar.');
                redirect('room_form' . ($id ? '&id=' . $id : ''));
            }
            if ($id === 0 && $quantity > 1
                && ($roomUploads !== [] || $facilityUploads !== [])) {
                set_flash('error', 'Foto unit dapat ditambahkan setelah kamar dibuat melalui halaman edit masing-masing kamar.');
                redirect('room_form');
            }
            $buildingCheck = $pdo->prepare("SELECT COUNT(*) FROM kos_buildings WHERE id=:id AND status='active'");
            if ($user['role'] === 'operator') {
                $buildingCheck = $pdo->prepare(
                    "SELECT COUNT(*) FROM kos_buildings WHERE id=:id AND owner_user_id=:owner_id
                     AND status='active' AND approval_status IN ('approved','revision','pending')"
                );
                $buildingCheck->execute(['id' => $buildingId, 'owner_id' => $user['id']]);
            } else {
                $buildingCheck->execute(['id' => $buildingId]);
            }
            if ((int) $buildingCheck->fetchColumn() !== 1) {
                set_flash('error', 'Pilih gedung yang aktif sebelum menyimpan kamar.');
                redirect('room_form' . ($id ? '&id=' . $id : ''));
            }

            $data = [
                'building_id' => $buildingId,
                'room_number' => $number, 'floor' => post_value('floor') ?: null,
                'room_type' => $roomType, 'monthly_rate' => $rate,
                'facilities' => post_value('facilities') ?: null, 'notes' => post_value('notes') ?: null,
                'status' => post_value('status') ?: 'available',
            ];
            if (!in_array($data['status'], ['available', 'maintenance'], true)) {
                set_flash('error', 'Status kamar tidak valid.');
                redirect('room_form' . ($id ? '&id=' . $id : ''));
            }
            $movedFiles = [];
            $pdo->beginTransaction();
            try {
                if ($id > 0) {
                    $existingRoom = one(
                        $pdo,
                        'SELECT building_id, EXISTS(SELECT 1 FROM rentals WHERE room_id=rooms.id) AS has_rentals
                         FROM rooms WHERE id=:id FOR UPDATE',
                        ['id' => $id]
                    );
                    if (!$existingRoom || !can_manage_building($pdo, $user, (int) $existingRoom['building_id'])) {
                        throw new RuntimeException('Anda tidak memiliki akses untuk mengubah kamar tersebut.');
                    }
                    if ((int) $existingRoom['building_id'] !== $buildingId && (int) $existingRoom['has_rentals'] > 0) {
                        throw new RuntimeException('Kamar yang memiliki histori booking atau sewa tidak dapat dipindahkan ke kos lain.');
                    }
                    $data['id'] = $id;
                    $statement = $pdo->prepare(
                        'UPDATE rooms SET building_id=:building_id, room_number=:room_number, floor=:floor, room_type=:room_type, monthly_rate=:monthly_rate, facilities=:facilities, notes=:notes, status=:status WHERE id=:id'
                    );
                    $statement->execute($data);
                    if ($statement->rowCount() === 0) {
                        $roomExists = $pdo->prepare('SELECT COUNT(*) FROM rooms WHERE id=:id');
                        $roomExists->execute(['id' => $id]);
                        if ((int) $roomExists->fetchColumn() === 0) {
                            throw new RuntimeException('Kamar tidak ditemukan.');
                        }
                    }
                } else {
                    $buildingLock = $pdo->prepare('SELECT id FROM kos_buildings WHERE id=:id FOR UPDATE');
                    $buildingLock->execute(['id' => $buildingId]);
                    if (!$buildingLock->fetchColumn()) {
                        throw new RuntimeException('Kos tidak ditemukan saat membuat kamar.');
                    }
                    if (!preg_match('/^(.*?)(\d+)$/', $number, $numberParts)) {
                        throw new RuntimeException('Nomor awal kamar harus diakhiri angka, misalnya A01.');
                    }
                    $prefix = $numberParts[1];
                    $numberWidth = strlen($numberParts[2]);
                    $nextNumber = (int) $numberParts[2];
                    $existingNumbers = $pdo->prepare('SELECT room_number FROM rooms WHERE building_id=:building_id');
                    $existingNumbers->execute(['building_id' => $buildingId]);
                    $usedNumbers = array_fill_keys($existingNumbers->fetchAll(PDO::FETCH_COLUMN), true);
                    $statement = $pdo->prepare(
                        'INSERT INTO rooms (building_id, room_number, floor, room_type, monthly_rate, facilities, notes, status)
                         VALUES (:building_id, :room_number, :floor, :room_type, :monthly_rate, :facilities, :notes, :status)'
                    );
                    $createdRoomIds = [];
                    while (count($createdRoomIds) < $quantity) {
                        $candidate = $prefix . str_pad((string) $nextNumber, $numberWidth, '0', STR_PAD_LEFT);
                        $nextNumber++;
                        if (strlen($candidate) > 30) {
                            throw new RuntimeException('Nomor kamar otomatis melebihi batas panjang yang didukung.');
                        }
                        if (isset($usedNumbers[$candidate])) {
                            continue;
                        }
                        $roomData = $data;
                        $roomData['room_number'] = $candidate;
                        $statement->execute($roomData);
                        $createdRoomIds[] = (int) $pdo->lastInsertId();
                        $usedNumbers[$candidate] = true;
                    }
                    $id = $createdRoomIds[0];
                }

                $uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads'
                    . DIRECTORY_SEPARATOR . 'rooms';
                if (($roomUploads || $facilityUploads)
                    && !is_dir($uploadDirectory)
                    && !mkdir($uploadDirectory, 0755, true)
                    && !is_dir($uploadDirectory)) {
                    throw new RuntimeException('Folder penyimpanan foto tidak dapat dibuat. Periksa izin folder aplikasi.');
                }

                $photoInsert = $pdo->prepare(
                    'INSERT INTO room_photos (room_id, category, photo_path) VALUES (:room_id, :category, :photo_path)'
                );
                foreach (['room' => $roomUploads, 'facility' => $facilityUploads] as $category => $uploads) {
                    foreach ($uploads as $upload) {
                        $filename = bin2hex(random_bytes(16)) . '.' . $upload['extension'];
                        $filePath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
                        if (!move_uploaded_file($upload['tmp_name'], $filePath)) {
                            throw new RuntimeException('Foto gagal disimpan. Periksa izin folder uploads/rooms.');
                        }
                        $movedFiles[] = $filePath;
                        $photoInsert->execute([
                            'room_id' => $id,
                            'category' => $category,
                            'photo_path' => 'uploads/rooms/' . $filename,
                        ]);
                    }
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                foreach ($movedFiles as $movedFile) {
                    if (is_file($movedFile) && !unlink($movedFile)) {
                        error_log('Unable to remove uncommitted room photo: ' . $movedFile);
                    }
                }
                throw $exception;
            }

            $photoCount = count($roomUploads) + count($facilityUploads);
            set_flash('success', $id > 0 && (int) post_value('id') === 0
                ? $quantity . ' unit kamar berhasil dibuat dengan nomor otomatis.'
                : ($photoCount
                    ? 'Data kamar dan ' . $photoCount . ' foto berhasil disimpan.'
                    : 'Data kamar berhasil disimpan.'));
            redirect('rooms');
        }

        if ($action === 'delete_room_photo') {
            $roomId = (int) post_value('room_id');
            $photoId = (int) post_value('photo_id');
            if (!can_manage_room($pdo, $user, $roomId)) {
                set_flash('error', 'Anda tidak dapat mengelola foto kamar tersebut.');
                redirect('rooms');
            }
            $photo = one(
                $pdo,
                'SELECT id, photo_path FROM room_photos WHERE id=:photo_id AND room_id=:room_id',
                ['photo_id' => $photoId, 'room_id' => $roomId]
            );
            if (!$photo) {
                set_flash('error', 'Foto kamar tidak ditemukan.');
                redirect('room_form&id=' . $roomId);
            }

            $photoDirectory = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'rooms');
            $photoFile = $photoDirectory === false
                ? false
                : realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $photo['photo_path']));
            if ($photoFile === false || dirname($photoFile) !== $photoDirectory) {
                throw new RuntimeException('Lokasi berkas foto tidak valid; foto tidak dihapus.');
            }
            $pdo->prepare(
                'UPDATE rooms SET profile_photo_path=NULL
                 WHERE id=:room_id AND profile_photo_path=:photo_path'
            )->execute(['room_id' => $roomId, 'photo_path' => $photo['photo_path']]);
            if (!unlink($photoFile)) {
                throw new RuntimeException('Foto tidak dapat dihapus. Periksa izin folder uploads/rooms.');
            }
            $delete = $pdo->prepare('DELETE FROM room_photos WHERE id=:id');
            $delete->execute(['id' => $photoId]);
            set_flash('success', 'Foto berhasil dihapus.');
            redirect('room_form&id=' . $roomId);
        }

        
}

function handle_rental_action(PDO $pdo, array $user, string $action): void
{
if ($action === 'create_rental') {
            if ($user['role'] !== 'admin') {
                set_flash('error', 'Masa sewa baru dibuat melalui persetujuan booking User.');
                redirect('dashboard');
            }
            $tenantId = (int) post_value('tenant_id');
            $roomId = (int) post_value('room_id');
            $start = post_value('start_date');
            $end = post_value('end_date') ?: null;
            $rate = post_value('monthly_rate');
            $deposit = post_value('deposit_amount') ?: '0';
            $billingDay = (int) post_value('billing_day');
            if (!$tenantId || !$roomId || !$start || !is_numeric($rate) || (float) $rate <= 0
                || !is_numeric($deposit) || (float) $deposit < 0 || $billingDay < 1 || $billingDay > 28
                || ($end !== null && $end < $start)) {
                set_flash('error', 'Periksa data sewa, tanggal, tarif, deposit, dan tanggal tagihan (1–28).');
                redirect('rental_form');
            }

            $status = $start > date('Y-m-d') ? 'upcoming' : 'active';
            $pdo->beginTransaction();
            try {
                $roomCheck = $pdo->prepare(
                    'SELECT rm.status, b.status AS building_status
                     FROM rooms rm JOIN kos_buildings b ON b.id=rm.building_id
                     WHERE rm.id=:id FOR UPDATE'
                );
                $roomCheck->execute(['id' => $roomId]);
                $roomAvailability = $roomCheck->fetch();
                if (!$roomAvailability || $roomAvailability['status'] !== 'available'
                    || $roomAvailability['building_status'] !== 'active') {
                    $pdo->rollBack();
                    set_flash('error', 'Pilih kamar yang berstatus kosong.');
                    redirect('rental_form');
                }

                $check = $pdo->prepare(
                    "SELECT COUNT(*) FROM rentals r WHERE r.room_id=:room_id
                     AND r.status IN ('active','upcoming')
                     AND (r.end_date IS NULL OR r.end_date >= :start_date)
                     AND (:end_date_null IS NULL OR r.start_date <= :end_date_compare)
                     AND (r.booking_expires_at IS NULL OR r.booking_expires_at>NOW()
                          OR EXISTS (SELECT 1 FROM invoices i JOIN payments p ON p.invoice_id=i.id
                                     WHERE i.rental_id=r.id AND p.status='pending'))"
                );
                $check->execute([
                    'room_id' => $roomId, 'start_date' => $start,
                    'end_date_null' => $end, 'end_date_compare' => $end,
                ]);
                if ((int) $check->fetchColumn() > 0) {
                    $pdo->rollBack();
                    set_flash('error', 'Kamar sudah memiliki masa sewa yang bertabrakan dengan tanggal tersebut.');
                    redirect('rental_form');
                }

                $statement = $pdo->prepare(
                    'INSERT INTO rentals (tenant_id, room_id, start_date, end_date, monthly_rate, deposit_amount, billing_day, status, notes, created_by) VALUES (:tenant_id, :room_id, :start_date, :end_date, :monthly_rate, :deposit_amount, :billing_day, :status, :notes, :created_by)'
                );
                $statement->execute([
                    'tenant_id' => $tenantId, 'room_id' => $roomId, 'start_date' => $start,
                    'end_date' => $end, 'monthly_rate' => $rate, 'deposit_amount' => $deposit,
                    'billing_day' => $billingDay, 'status' => $status,
                    'notes' => post_value('notes') ?: null, 'created_by' => $user['id'],
                ]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            set_flash('success', 'Masa sewa berhasil dibuat.');
            redirect('rentals');
        }

        if ($action === 'rental_status') {
            if ($user['role'] !== 'admin') {
                set_flash('error', 'Status masa sewa hanya dapat diubah oleh admin platform.');
                redirect('dashboard');
            }
            $status = post_value('status');
            if (!in_array($status, ['ended', 'cancelled'], true)) {
                set_flash('error', 'Status sewa tidak valid.');
                redirect('rentals');
            }
            $statement = $pdo->prepare(
                "UPDATE rentals SET status=:status WHERE id=:id AND status IN ('active','upcoming')"
            );
            $statement->execute(['status' => $status, 'id' => (int) post_value('id')]);
            set_flash('success', 'Status sewa diperbarui.');
            redirect('rentals');
        }

        
}
