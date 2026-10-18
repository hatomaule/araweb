<?php
declare(strict_types=1);

function handle_account_action(PDO $pdo, array $user, string $action): void
{
    if ($action === 'save_account_settings') {
        $email = strtolower(trim(post_value('email')));
        $phone = trim(post_value('phone'));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Format email tidak valid.');
            redirect('account_settings');
        }
        if ($phone !== '' && !preg_match('/^[0-9+().\s-]{7,30}$/', $phone)) {
            set_flash('error', 'Nomor telepon tidak valid.');
            redirect('account_settings');
        }
        if ($email === '' && $phone === '') {
            set_flash('error', 'Pertahankan minimal satu kontak berupa email atau nomor telepon.');
            redirect('account_settings');
        }
        if ($email !== '' && one(
            $pdo,
            'SELECT id FROM users WHERE email=:email AND id<>:id LIMIT 1',
            ['email' => $email, 'id' => $user['id']]
        )) {
            set_flash('error', 'Email tersebut sudah digunakan akun lain.');
            redirect('account_settings');
        }
        if ($phone !== '' && one(
            $pdo,
            'SELECT id FROM users WHERE phone=:phone AND id<>:id LIMIT 1',
            ['phone' => $phone, 'id' => $user['id']]
        )) {
            set_flash('error', 'Nomor telepon tersebut sudah digunakan akun lain.');
            redirect('account_settings');
        }
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare(
                'UPDATE users SET email=:email,phone=:phone,
                    hide_phone_public=:hide_phone,hide_profile_photo_public=:hide_photo
                 WHERE id=:id'
            );
            $update->execute([
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'hide_phone' => post_value('hide_phone_public') === '1' ? 1 : 0,
                'hide_photo' => post_value('hide_profile_photo_public') === '1' ? 1 : 0,
                'id' => $user['id'],
            ]);
            $pdo->prepare(
                'UPDATE tenants SET email=:email,phone=:phone
                 WHERE user_id=:user_id'
            )->execute([
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'user_id' => $user['id'],
            ]);
            notification_set_preference($pdo, post_value('hide_notifications') !== '1');
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        set_flash('success', 'Pengaturan akun berhasil disimpan.');
        redirect('account_settings');
    }

    if (in_array($action, ['request_account_deactivation', 'request_account_deletion'], true)) {
        if ($user['role'] !== 'seeker') {
            set_flash('error', 'Pengaturan ini hanya tersedia untuk akun User.');
            redirect('account_settings');
        }
        if (post_value('confirm_account_lifecycle') !== 'yes') {
            set_flash('error', 'Centang konfirmasi sebelum melanjutkan.');
            redirect('account_settings');
        }
        $activeRental = one(
            $pdo,
            "SELECT r.id FROM rentals r JOIN tenants t ON t.id=r.tenant_id
             WHERE t.user_id=:user_id AND r.status='active'
               AND r.start_date<=CURDATE() AND (r.end_date IS NULL OR r.end_date>=CURDATE())
             LIMIT 1",
            ['user_id' => $user['id']]
        );
        if ($activeRental) {
            set_flash('error', 'Akun belum dapat dinonaktifkan selama masa hunian masih aktif.');
            redirect('account_settings');
        }
        if ($action === 'request_account_deactivation') {
            $pdo->prepare(
                'UPDATE users SET self_deactivated_at=COALESCE(self_deactivated_at,NOW())
                 WHERE id=:id'
            )->execute(['id' => $user['id']]);
            set_flash('success', 'Akun dinonaktifkan dari pencarian dan booking. Anda tetap dapat masuk kembali.');
        } else {
            $pdo->prepare(
                'UPDATE users SET self_deactivated_at=NOW(),
                    deletion_requested_at=NOW(),deletion_due_at=DATE_ADD(NOW(),INTERVAL 7 DAY)
                 WHERE id=:id'
            )->execute(['id' => $user['id']]);
            set_flash('success', 'Permintaan penghapusan dijadwalkan 7 hari lagi. Masuk kembali sebelum batas waktu untuk membatalkannya.');
        }
        redirect('account_settings');
    }

    if ($action === 'cancel_account_deactivation') {
        $pdo->prepare(
            'UPDATE users SET self_deactivated_at=NULL,deletion_requested_at=NULL,deletion_due_at=NULL
             WHERE id=:id'
        )->execute(['id' => $user['id']]);
        set_flash('success', 'Penonaktifan atau permintaan penghapusan akun dibatalkan.');
        redirect('account_settings');
    }

    if ($action === 'change_password') {
        $currentPassword = post_value('current_password');
        $newPassword = post_value('new_password');
        $confirmation = post_value('confirm_password');
        $account = one($pdo, 'SELECT password_hash FROM users WHERE id=:id', ['id' => $user['id']]);
        if (!$account || !password_verify($currentPassword, $account['password_hash'])) {
            set_flash('error', 'Password saat ini tidak sesuai.');
            redirect('change_password');
        }
        if (strlen($newPassword) < 8 || strlen($newPassword) > 72 || $newPassword !== $confirmation) {
            set_flash('error', 'Password baru harus 8 sampai 72 karakter dan kedua isian harus sama.');
            redirect('change_password');
        }
        $updated = $pdo->prepare('UPDATE users SET password_hash=:password_hash WHERE id=:id');
        $updated->execute([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $user['id'],
        ]);
        set_flash('success', 'Password berhasil diganti.');
        redirect('change_password');
    }

    if ($action === 'operator_remove_tenant_from_building') {
        $tenantId = (int) post_value('tenant_id');
        if ($user['role'] !== 'operator' || $tenantId < 1) {
            set_flash('error', 'Hanya operator yang dapat mengeluarkan penghuni dari kos miliknya.');
            redirect('tenants');
        }
        if (post_value('confirm_remove') !== 'yes') {
            set_flash('error', 'Konfirmasi pengeluaran penghuni wajib diberikan.');
            redirect('tenants');
        }

        $pdo->beginTransaction();
        try {
            $rentals = $pdo->prepare(
                "SELECT r.id,r.room_id
                 FROM rentals r
                 JOIN tenants t ON t.id=r.tenant_id
                 JOIN users account ON account.id=t.user_id AND account.role='seeker'
                 JOIN rooms rm ON rm.id=r.room_id
                 JOIN kos_buildings b ON b.id=rm.building_id
                 WHERE t.id=:tenant_id AND t.status='active'
                   AND b.owner_user_id=:owner_id
                   AND r.status='active' AND r.start_date<=CURDATE()
                   AND (r.end_date IS NULL OR r.end_date>=CURDATE())
                 ORDER BY r.id
                 FOR UPDATE"
            );
            $rentals->execute(['tenant_id' => $tenantId, 'owner_id' => $user['id']]);
            $activeRentals = $rentals->fetchAll();
            if (!$activeRentals) {
                $pdo->rollBack();
                set_flash('error', 'Penghuni tidak memiliki sewa aktif di kos yang Anda kelola.');
                redirect('tenants');
            }

            $endRental = $pdo->prepare(
                "UPDATE rentals SET status='ended',end_date=CURDATE()
                 WHERE id=:id AND status='active'"
            );
            $releaseRoom = $pdo->prepare(
                "UPDATE rooms SET status='available'
                 WHERE id=:room_id AND status='occupied'
                   AND NOT EXISTS (
                       SELECT 1 FROM rentals current_rental
                       WHERE current_rental.room_id=rooms.id
                         AND current_rental.status='active'
                         AND current_rental.start_date<=CURDATE()
                         AND (current_rental.end_date IS NULL OR current_rental.end_date>=CURDATE())
                   )"
            );
            foreach ($activeRentals as $rental) {
                $endRental->execute(['id' => $rental['id']]);
                if ($endRental->rowCount() !== 1) {
                    throw new RuntimeException('Masa sewa berubah saat proses pengeluaran. Silakan muat ulang data.');
                }
                $releaseRoom->execute(['room_id' => $rental['room_id']]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        set_flash('success', 'Sewa penghuni berhasil diakhiri. Akun dan statusnya tidak diubah; histori sewa serta pembayaran tetap tersimpan.');
        redirect('tenants');
    }

    if ($action === 'operator_move_tenant_room') {
        $tenantId = (int) post_value('tenant_id');
        $targetRoomId = (int) post_value('target_room_id');
        if ($user['role'] !== 'operator' || $tenantId < 1 || $targetRoomId < 1
            || post_value('confirm_move') !== 'yes') {
            set_flash('error', 'Pilih kamar tujuan dan konfirmasi pemindahan penghuni.');
            redirect('rooms');
        }

        $pdo->beginTransaction();
        try {
            $activeRentalQuery = $pdo->prepare(
                "SELECT r.id,r.room_id
                 FROM rentals r
                 JOIN rooms rm ON rm.id=r.room_id
                 JOIN kos_buildings b ON b.id=rm.building_id
                 WHERE r.tenant_id=:tenant_id AND b.owner_user_id=:owner_id
                   AND r.status='active' AND r.start_date<=CURDATE()
                   AND (r.end_date IS NULL OR r.end_date>=CURDATE())
                 FOR UPDATE"
            );
            $activeRentalQuery->execute(['tenant_id' => $tenantId, 'owner_id' => $user['id']]);
            $activeRentals = $activeRentalQuery->fetchAll();
            if (count($activeRentals) !== 1) {
                throw new RuntimeException('Penghuni harus memiliki tepat satu sewa aktif di properti Anda.');
            }
            $currentRental = $activeRentals[0];
            if ((int) $currentRental['room_id'] === $targetRoomId) {
                throw new RuntimeException('Pilih kamar yang berbeda dari kamar saat ini.');
            }

            $targetQuery = $pdo->prepare(
                "SELECT rm.id,rm.monthly_rate
                 FROM rooms rm JOIN kos_buildings b ON b.id=rm.building_id
                 WHERE rm.id=:room_id AND rm.status='available'
                   AND b.owner_user_id=:owner_id AND b.status='active'
                   AND b.approval_status='approved'
                   AND NOT EXISTS (
                       SELECT 1 FROM rentals target_rental
                       WHERE target_rental.room_id=rm.id
                         AND target_rental.status IN ('active','upcoming')
                         AND (target_rental.status='active'
                              OR target_rental.booking_expires_at IS NULL
                              OR target_rental.booking_expires_at>NOW())
                   )
                 FOR UPDATE"
            );
            $targetQuery->execute(['room_id' => $targetRoomId, 'owner_id' => $user['id']]);
            $targetRoom = $targetQuery->fetch();
            if (!$targetRoom) {
                throw new RuntimeException('Kamar tujuan tidak tersedia atau bukan milik properti Anda.');
            }
            $activeTarget = $pdo->prepare(
                "SELECT COUNT(*) FROM rentals WHERE room_id=:room_id AND status='active'
                   AND start_date<=CURDATE() AND (end_date IS NULL OR end_date>=CURDATE())"
            );
            $activeTarget->execute(['room_id' => $targetRoomId]);
            if ((int) $activeTarget->fetchColumn() > 0) {
                throw new RuntimeException('Kamar tujuan sudah memiliki penghuni aktif.');
            }

            $pdo->prepare('UPDATE rentals SET room_id=:room_id,monthly_rate=:rate WHERE id=:id')
                ->execute([
                    'room_id' => $targetRoomId,
                    'rate' => $targetRoom['monthly_rate'],
                    'id' => $currentRental['id'],
                ]);
            $pdo->prepare("UPDATE rooms SET status='available' WHERE id=:id")
                ->execute(['id' => $currentRental['room_id']]);
            $pdo->prepare("UPDATE rooms SET status='occupied' WHERE id=:id")
                ->execute(['id' => $targetRoomId]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        set_flash('success', 'Penghuni berhasil dipindahkan. Riwayat sewa, tagihan, dan pembayaran tetap terhubung.');
        redirect('rooms');
    }

if ($action === 'operator_add_tenant_account') {
            if ($user['role'] !== 'operator') {
                set_flash('error', 'Hanya operator yang dapat menambahkan penghuni ke kos miliknya.');
                redirect('dashboard');
            }
            $name = post_value('full_name');
            $email = strtolower(post_value('email'));
            $phone = post_value('phone');
            $gender = post_value('gender');
            $password = post_value('password');
            $roomId = (int) post_value('room_id');
            if ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || strlen($email) > 255 || $phone === '' || strlen($phone) > 30
                || !in_array($gender, ['male', 'female'], true)
                || strlen($password) < 8 || strlen($password) > 72 || $roomId < 1) {
                set_flash('error', 'Lengkapi nama, email, telepon, jenis kelamin, kamar, dan password minimal 8 karakter.');
                redirect('tenant_form');
            }

            $profilePath = store_profile_photo_upload();
            $pdo->beginTransaction();
            try {
                $roomQuery = $pdo->prepare(
                    "SELECT rm.id,rm.monthly_rate FROM rooms rm
                     JOIN kos_buildings b ON b.id=rm.building_id
                     WHERE rm.id=:room_id AND b.owner_user_id=:owner_id
                       AND rm.status='available' AND b.status='active'
                     FOR UPDATE"
                );
                $roomQuery->execute(['room_id' => $roomId, 'owner_id' => $user['id']]);
                $room = $roomQuery->fetch();
                if (!$room) {
                    $pdo->rollBack();
                    if ($profilePath !== null && is_file(__DIR__ . '/../' . $profilePath)) {
                        unlink(__DIR__ . '/../' . $profilePath);
                    }
                    set_flash('error', 'Kamar tidak tersedia atau bukan milik kos Anda.');
                    redirect('tenant_form');
                }
                $account = $pdo->prepare(
                    "INSERT INTO users (name,email,phone,gender,profile_photo_path,password_hash,role,status)
                     VALUES (:name,:email,:phone,:gender,:photo,:password,'seeker','active')"
                );
                $account->execute([
                    'name' => $name, 'email' => $email, 'phone' => $phone,
                    'gender' => $gender, 'photo' => $profilePath,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                $userId = (int) $pdo->lastInsertId();
                $tenant = $pdo->prepare(
                    "INSERT INTO tenants (user_id,full_name,phone,email,status)
                     VALUES (:user_id,:name,:phone,:email,'active')"
                );
                $tenant->execute([
                    'user_id' => $userId, 'name' => $name, 'phone' => $phone, 'email' => $email,
                ]);
                $tenantId = (int) $pdo->lastInsertId();
                $rental = $pdo->prepare(
                    "INSERT INTO rentals
                     (tenant_id,room_id,start_date,monthly_rate,deposit_amount,billing_day,
                      status,notes,created_by,booking_status)
                     VALUES (:tenant_id,:room_id,CURDATE(),:rate,0,1,'active',
                      'Penghuni ditambahkan oleh operator.',:operator_id,'not_applicable')"
                );
                $rental->execute([
                    'tenant_id' => $tenantId, 'room_id' => $roomId,
                    'rate' => $room['monthly_rate'], 'operator_id' => $user['id'],
                ]);
                $pdo->prepare("UPDATE rooms SET status='occupied' WHERE id=:id")
                    ->execute(['id' => $roomId]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($profilePath !== null && is_file(__DIR__ . '/../' . $profilePath)) {
                    unlink(__DIR__ . '/../' . $profilePath);
                }
                throw $exception;
            }
            set_flash('success', 'Akun pengguna berhasil dibuat dan ditautkan ke kamar kos Anda.');
            redirect('tenants');
        }

        if ($action === 'operator_reset_tenant_password') {
            $tenantId = (int) post_value('tenant_id');
            $password = post_value('new_password');
            if (post_value('confirm_account_action') !== 'yes') {
                set_flash('error', 'Konfirmasi reset password wajib diberikan.');
                redirect('tenants');
            }
            if ($user['role'] !== 'operator' || $tenantId < 1
                || !can_manage_tenant($pdo, $user, $tenantId)) {
                set_flash('error', 'Anda hanya dapat mengubah password penghuni kos milik Anda.');
                redirect('tenants');
            }
            if (strlen($password) < 8 || strlen($password) > 72) {
                set_flash('error', 'Password baru harus berisi 8 sampai 72 karakter.');
                redirect('tenants');
            }
            $account = $pdo->prepare(
                "UPDATE users u JOIN tenants t ON t.user_id=u.id
                 SET u.password_hash=:password
                 WHERE t.id=:tenant_id AND u.role='seeker'"
            );
            $account->execute([
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'tenant_id' => $tenantId,
            ]);
            if ($account->rowCount() !== 1) {
                set_flash('error', 'Akun penghuni tidak ditemukan atau password tidak berubah.');
                redirect('tenants');
            }
            set_flash('success', 'Password penghuni berhasil diperbarui.');
            redirect('tenants');
        }

if ($action === 'save_profile') {
            $name = post_value('name');
            $phone = post_value('phone');
    $regions = resolve_indonesia_regions($pdo);
    $street = post_value('street_name');
    $rt = post_value('address_rt');
    $rw = post_value('address_rw');
    $detailAddress = post_value('address_block');
    $address = format_indonesia_address($regions, $street, $rt, $rw, $detailAddress);
    $email = strtolower(post_value('email'));
    $gender = post_value('gender');
    if ($name === '' || strlen($name) > 100 || strlen($phone) > 30 || strlen($street) > 1000
        || strlen($rt) > 3 || strlen($rw) > 3 || ($rt !== '' && !ctype_digit($rt))
        || ($rw !== '' && !ctype_digit($rw)) || strlen($detailAddress) > 50
        || strlen($address ?? '') > 1000
        || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255
        || ($gender !== '' && !in_array($gender, ['male', 'female'], true))
        || ($user['role'] !== 'admin' && !in_array($gender, ['male', 'female'], true))) {
        set_flash('error', 'Periksa nama, email, nomor WhatsApp, alamat, dan jenis kelamin.');
        redirect('profile');
    }
    $profilePath = store_profile_photo_upload();
    $profile = $pdo->prepare(
        'UPDATE users SET name=:name, email=:email, phone=:phone, address=:address,
         province_code=:province_code, regency_code=:regency_code,
         district_code=:district_code, village_code=:village_code,
         street_name=:street_name, address_rt=:address_rt, address_rw=:address_rw,
         address_block=:address_block, gender=:gender,
         profile_photo_path=COALESCE(:photo, profile_photo_path)
         WHERE id=:id'
    );
    $regionValues = [
        'province_code' => $regions['province_code'] ?? null,
        'regency_code' => $regions['regency_code'] ?? null,
        'district_code' => $regions['district_code'] ?? null,
        'village_code' => $regions['village_code'] ?? null,
    ];
    $profile->execute([
        'name' => $name, 'email' => $email, 'phone' => $phone ?: null,
        'address' => $address,
        'street_name' => $street !== '' ? $street : null,
        'address_rt' => $rt !== '' ? $rt : null,
        'address_rw' => $rw !== '' ? $rw : null,
        'address_block' => $detailAddress !== '' ? $detailAddress : null,
        ...$regionValues,
        'gender' => $gender !== '' ? $gender : null, 'photo' => $profilePath, 'id' => $user['id'],
    ]);
            if ($user['role'] === 'seeker') {
                $pdo->prepare('UPDATE tenants SET full_name=:name, phone=:phone, email=:email WHERE user_id=:id')
                    ->execute(['name' => $name, 'phone' => $phone, 'email' => $email, 'id' => $user['id']]);
            }
            set_flash('success', 'Profil berhasil diperbarui.');
            redirect('profile');
        }

        
}
