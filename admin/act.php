<?php
declare(strict_types=1);

function admin_account_list_route(array $target = []): string
{
    if (($target['role'] ?? null) === 'owner') {
        return 'platform_operator_accounts';
    }

    return post_value('account_list_page') === 'platform_unhoused_users'
        ? 'platform_unhoused_users'
        : 'platform_seeker_accounts';
}

function handle_admin_action(PDO $pdo, array $user, string $action): void
{
    if ($user['role'] !== 'admin') {
        return;
    }

    if ($action === 'delete_activity_logs') {
        if (post_value('confirm_delete_logs') !== 'yes') {
            set_flash('error', 'Konfirmasi penghapusan log wajib diberikan.');
            redirect('activity_log');
        }
        [$where, $params] = activity_log_filter_query($_POST, $user);
        $delete = $pdo->prepare("DELETE al FROM activity_logs al {$where}");
        $delete->execute($params);
        set_flash('success', number_format($delete->rowCount(), 0, ',', '.')
            . ' log audit dihapus. Statistik pencarian dan kunjungan tetap tersimpan.');
        $filterQuery = http_build_query(array_intersect_key($_POST, array_flip(['role', 'actor_id', 'category', 'outcome', 'q'])));
        redirect('activity_log' . ($filterQuery !== '' ? '&' . $filterQuery : ''));
    }

    if ($action === 'admin_reset_account_password') {
        $targetId = (int) post_value('user_id');
        $newPassword = post_value('new_password');
        if (post_value('confirm_account_action') !== 'yes') {
            set_flash('error', 'Konfirmasi reset password wajib diberikan.');
            redirect(admin_account_list_route());
        }
        if ($targetId < 1 || strlen($newPassword) < 8 || strlen($newPassword) > 72) {
            set_flash('error', 'Password baru harus berisi 8 sampai 72 karakter.');
            redirect(admin_account_list_route());
        }

        $target = one(
            $pdo,
            'SELECT id, role FROM users WHERE id=:id AND role IN (\'owner\', \'seeker\') LIMIT 1',
            ['id' => $targetId]
        );
        if (!$target) {
            set_flash('error', 'Akun target tidak ditemukan.');
            redirect(admin_account_list_route());
        }

        $updated = $pdo->prepare('UPDATE users SET password_hash=:password_hash WHERE id=:id');
        $updated->execute([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $targetId,
        ]);
        if ($updated->rowCount() !== 1) {
            set_flash('error', 'Password akun tidak dapat diperbarui.');
            redirect(admin_account_list_route($target));
        }

        set_flash('success', 'Password akun berhasil diperbarui.');
        redirect(admin_account_list_route($target));
    }

    if ($action === 'admin_set_account_status') {
        $targetId = (int) post_value('user_id');
        $status = post_value('status');
        if (post_value('confirm_account_action') !== 'yes') {
            set_flash('error', 'Konfirmasi perubahan status akun wajib diberikan.');
            redirect(admin_account_list_route());
        }
        if ($targetId < 1 || !in_array($status, ['active', 'inactive'], true)) {
            set_flash('error', 'Status akun tidak valid.');
            redirect(admin_account_list_route());
        }

        if ($targetId === (int) $user['id']) {
            set_flash('error', 'Anda tidak dapat menonaktifkan akun admin sendiri.');
            redirect('platform_dashboard');
        }

        $target = one(
            $pdo,
            'SELECT id, role FROM users WHERE id=:id AND role IN (\'owner\', \'seeker\') LIMIT 1',
            ['id' => $targetId]
        );
        if (!$target) {
            set_flash('error', 'Akun target tidak ditemukan.');
            redirect(admin_account_list_route());
        }

        $updated = $pdo->prepare('UPDATE users SET status=:status WHERE id=:id');
        $updated->execute(['status' => $status, 'id' => $targetId]);
        if ($updated->rowCount() !== 1) {
            set_flash('error', 'Status akun tidak dapat diperbarui.');
            redirect(admin_account_list_route($target));
        }

        set_flash('success', 'Status akun berhasil diperbarui.');
        redirect(admin_account_list_route($target));
    }

    if ($action === 'admin_delete_account') {
        $targetId = (int) post_value('user_id');
        if ($targetId < 1 || post_value('confirm_account_action') !== 'yes'
            || !in_array(post_value('account_list_page'), ['platform_seeker_accounts', 'platform_unhoused_users'], true)) {
            set_flash('error', 'Konfirmasi penghapusan akun wajib diberikan.');
            redirect(admin_account_list_route());
        }

        $pdo->beginTransaction();
        try {
            $target = one(
                $pdo,
                "SELECT u.id
                 FROM users u
                 WHERE u.id=:id AND u.role='seeker'
                   AND NOT EXISTS (
                       SELECT 1 FROM tenants t
                       JOIN rentals r ON r.tenant_id=t.id
                       WHERE t.user_id=u.id AND t.status='active'
                         AND (
                             (r.status='active'
                              AND (r.end_date IS NULL OR r.end_date>=CURDATE()))
                             OR (r.status='upcoming'
                                 AND (r.booking_expires_at IS NULL OR r.booking_expires_at>NOW()))
                         )
                   )
                   AND NOT EXISTS (
                       SELECT 1 FROM bookings active_booking
                       WHERE active_booking.user_id=u.id
                         AND active_booking.status IN ('awaiting_payment','proof_pending','queued','room_offered')
                   )
                 LIMIT 1 FOR UPDATE",
                ['id' => $targetId]
            );
            if (!$target) {
                $pdo->rollBack();
                set_flash('error', 'Akun User tidak ditemukan atau masih memiliki sewa/booking berjalan.');
                redirect(admin_account_list_route());
            }

            foreach (['bookings', 'search_history', 'user_activity_events'] as $historyTable) {
                $pdo->prepare(
                    "UPDATE {$historyTable} SET former_user_id=user_id,user_id=NULL WHERE user_id=:user_id"
                )->execute(['user_id' => $targetId]);
            }
            $deleted = $pdo->prepare("DELETE FROM users WHERE id=:id AND role='seeker'");
            $deleted->execute(['id' => $targetId]);
            if ($deleted->rowCount() !== 1) {
                throw new RuntimeException('Akun User tidak dapat dihapus.');
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof PDOException
                && in_array($exception->errorInfo[1] ?? null, [1054, 1146], true)) {
                set_flash(
                    'error',
                    'Penghapusan akun belum aktif karena struktur database belum diperbarui. Buat backup, lalu jalankan migration_account_activity_and_deletion.sql.'
                );
                redirect(admin_account_list_route());
            }
            throw $exception;
        }

        set_flash('success', 'Akun User berhasil dihapus. Histori sewa, booking, pencarian, aktivitas, dan pembayaran tetap tersimpan.');
        redirect(admin_account_list_route());
    }

    if ($action === 'set_operator_status') {
        $targetId = (int) post_value('user_id');
        $status = post_value('status');
        if ($targetId < 1 || !in_array($status, ['active', 'inactive'], true)) {
            set_flash('error', 'Status Owner tidak valid.');
            redirect('platform_operator_accounts');
        }

        if ($targetId === (int) $user['id']) {
            set_flash('error', 'Anda tidak dapat mengubah status akun admin sendiri.');
            redirect('platform_dashboard');
        }

        $updated = $pdo->prepare('UPDATE users SET status=:status WHERE id=:id AND role=:role');
        $updated->execute(['status' => $status, 'id' => $targetId, 'role' => 'owner']);
        if ($updated->rowCount() !== 1) {
            set_flash('error', 'Owner yang dipilih tidak ditemukan.');
            redirect('platform_operator_accounts');
        }

        set_flash('success', 'Status Owner berhasil diperbarui.');
        redirect('platform_operator_accounts');
    }

    if ($action === 'admin_update_operator_profile') {
        $targetId = (int) post_value('user_id');
        $accountRole = post_value('account_role');
        $name = post_value('name');
        $username = normalize_username(post_value('username'));
        $email = strtolower(post_value('email'));
        $phone = post_value('phone');
        $gender = post_value('gender');
        $regions = resolve_indonesia_regions($pdo);
        $street = post_value('street_name');
        $rt = post_value('address_rt');
        $rw = post_value('address_rw');
        $detailAddress = post_value('address_block');
        $address = format_indonesia_address($regions, $street, $rt, $rw, $detailAddress);

        if ($targetId < 1 || !in_array($accountRole, ['owner', 'seeker'], true)
            || $name === '' || strlen($name) > 100 || !username_is_valid($username)
            || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) || strlen($email) > 255
            || strlen($phone ?? '') > 30 || strlen($street) > 1000
            || strlen($rt) > 3 || strlen($rw) > 3 || ($rt !== '' && !ctype_digit($rt))
            || ($rw !== '' && !ctype_digit($rw)) || strlen($detailAddress) > 50
            || strlen($address ?? '') > 1000
            || ($gender !== '' && !in_array($gender, ['male', 'female'], true))) {
            set_flash('error', 'Periksa nama, username unik (huruf A-Z saja), email, nomor HP, jenis kelamin, dan alamat profil.');
            redirect($accountRole === 'owner' ? 'platform_operator_accounts' : 'platform_seeker_accounts');
        }

        $target = one(
            $pdo,
            'SELECT id FROM users WHERE id=:id AND role=:role LIMIT 1',
            ['id' => $targetId, 'role' => $accountRole]
        );
        if (!$target) {
            set_flash('error', 'Profil akun tidak ditemukan.');
            redirect($accountRole === 'owner' ? 'platform_operator_accounts' : 'platform_seeker_accounts');
        }

        if (!username_is_available($pdo, $username, $targetId)) {
            set_flash('error', 'Username tersebut sudah digunakan akun lain.');
            redirect($accountRole === 'owner' ? 'platform_operator_profile&id=' . $targetId : 'platform_user_profile&id=' . $targetId);
        }

        $existingEmail = $email !== '' ? one(
            $pdo,
            'SELECT id FROM users WHERE email=:email AND id != :id LIMIT 1',
            ['email' => $email, 'id' => $targetId]
        ) : null;
        if ($existingEmail) {
            set_flash('error', 'Email tersebut sudah dipakai akun lain.');
            redirect($accountRole === 'owner' ? 'platform_operator_profile&id=' . $targetId : 'platform_user_profile&id=' . $targetId);
        }
        $updated = $pdo->prepare(
            'UPDATE users
             SET name=:name,
                 username=:username,
                 email=:email,
                 phone=:phone,
                 address=:address,
                 province_code=:province_code,
                 regency_code=:regency_code,
                 district_code=:district_code,
                 village_code=:village_code,
                 street_name=:street_name,
                 address_rt=:address_rt,
                 address_rw=:address_rw,
                 address_block=:address_block,
                 gender=:gender
             WHERE id=:id AND role=:role'
        );
        $updated->execute([
            'name' => $name,
            'username' => $username,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'address' => $address,
            'province_code' => $regions['province_code'] ?? null,
            'regency_code' => $regions['regency_code'] ?? null,
            'district_code' => $regions['district_code'] ?? null,
            'village_code' => $regions['village_code'] ?? null,
            'street_name' => $street !== '' ? $street : null,
            'address_rt' => $rt !== '' ? $rt : null,
            'address_rw' => $rw !== '' ? $rw : null,
            'address_block' => $detailAddress !== '' ? $detailAddress : null,
            'gender' => $gender !== '' ? $gender : null,
            'id' => $targetId,
            'role' => $accountRole,
        ]);
        if ($updated->rowCount() !== 1) {
            set_flash('error', 'Profil akun tidak dapat diperbarui.');
            redirect($accountRole === 'owner' ? 'platform_operator_profile&id=' . $targetId : 'platform_user_profile&id=' . $targetId);
        }

        if ($accountRole === 'seeker') {
            $pdo->prepare(
                'UPDATE tenants SET full_name=:name, phone=:phone, email=:email WHERE user_id=:user_id'
            )->execute([
                'name' => $name,
                'phone' => $phone !== '' ? $phone : null,
                'email' => $email !== '' ? $email : null,
                'user_id' => $targetId,
            ]);
        }

        set_flash('success', 'Profil akun berhasil diperbarui.');
        redirect($accountRole === 'owner' ? 'platform_operator_profile&id=' . $targetId : 'platform_user_profile&id=' . $targetId);
    }
}
