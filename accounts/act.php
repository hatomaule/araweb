<?php
declare(strict_types=1);

function handle_account_action(PDO $pdo, array $user, string $action): void
{
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
            $address = post_value('address');
            $email = strtolower(post_value('email'));
            $gender = post_value('gender');
            if ($name === '' || strlen($name) > 100 || strlen($phone) > 30 || strlen($address) > 1000
                || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255
                || ($gender !== '' && !in_array($gender, ['male', 'female'], true))
                || ($user['role'] !== 'admin' && !in_array($gender, ['male', 'female'], true))) {
                set_flash('error', 'Periksa nama, email, nomor WhatsApp, alamat, dan jenis kelamin.');
                redirect('profile');
            }
            $profilePath = store_profile_photo_upload();
            $profile = $pdo->prepare(
                'UPDATE users SET name=:name, email=:email, phone=:phone, address=:address,
                 gender=:gender, profile_photo_path=COALESCE(:photo, profile_photo_path)
                 WHERE id=:id'
            );
            $profile->execute([
                'name' => $name, 'email' => $email, 'phone' => $phone ?: null,
                'address' => $address ?: null,
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
