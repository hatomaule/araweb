<?php
declare(strict_types=1);

function handle_booking_payment_action(PDO $pdo, array $user, string $action): void
{
if ($action === 'submit_booking_proof') {
            if ($user['role'] !== 'seeker') {
                set_flash('error', 'Hanya akun pelanggan yang dapat mengunggah bukti transfer.');
                redirect('dashboard');
            }
            $tenant = one($pdo, "SELECT id FROM tenants WHERE user_id=:user_id AND status='active'", ['user_id' => $user['id']]);
            $invoiceId = (int) post_value('invoice_id');
            $upload = validate_payment_proof('payment_proof');
            if (!$tenant || !$invoiceId || !$upload) {
                set_flash('error', 'Pilih dan unggah bukti transfer JPG, PNG, atau WebP (maksimal 4 MB).');
                redirect('my_bookings');
            }

            $paymentPath = null;
            $pdo->beginTransaction();
            try {
                $invoiceQuery = $pdo->prepare(
                    "SELECT i.id, i.amount_due, r.id AS rental_id, r.booking_expires_at,
                            r.status AS rental_status
                     FROM invoices i JOIN rentals r ON r.id=i.rental_id
                     WHERE i.id=:invoice_id AND r.tenant_id=:tenant_id
                       AND r.status IN ('upcoming','active') AND i.status='open'
                       AND (r.status='active' OR r.booking_status='approved')
                       AND (r.status='active' OR r.booking_expires_at IS NULL OR r.booking_expires_at>NOW())
                     FOR UPDATE"
                );
                $invoiceQuery->execute(['invoice_id' => $invoiceId, 'tenant_id' => $tenant['id']]);
                $invoice = $invoiceQuery->fetch();
                if (!$invoice) {
                    $pdo->rollBack();
                    set_flash('error', 'Tagihan tidak lagi dapat dibayar dari akun ini.');
                    redirect('my_bookings');
                }
                $confirmedTotal = $pdo->prepare(
                    "SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=:invoice_id AND status='confirmed'"
                );
                $confirmedTotal->execute(['invoice_id' => $invoiceId]);
                $paymentAmount = (float) $invoice['amount_due'] - (float) $confirmedTotal->fetchColumn();
                if ($paymentAmount <= 0) {
                    $pdo->rollBack();
                    set_flash('error', 'Tagihan ini sudah lunas.');
                    redirect('my_bookings');
                }
                $existing = $pdo->prepare(
                    "SELECT COUNT(*) FROM payments WHERE invoice_id=:invoice_id AND status='pending'"
                );
                $existing->execute(['invoice_id' => $invoiceId]);
                if ((int) $existing->fetchColumn() > 0) {
                    $pdo->rollBack();
                    set_flash('error', 'Bukti pembayaran untuk booking ini sedang menunggu verifikasi pemilik kos.');
                    redirect('my_bookings');
                }

                $uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads'
                    . DIRECTORY_SEPARATOR . 'payments';
                if (!is_dir($uploadDirectory)
                    && !mkdir($uploadDirectory, 0755, true)
                    && !is_dir($uploadDirectory)) {
                    throw new RuntimeException('Folder penyimpanan bukti transfer tidak dapat dibuat.');
                }
                $filename = bin2hex(random_bytes(16)) . '.' . $upload['extension'];
                $filePath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
                if (!move_uploaded_file($upload['tmp_name'], $filePath)) {
                    throw new RuntimeException('Bukti transfer gagal disimpan. Periksa izin folder uploads/payments.');
                }
                $paymentPath = $filePath;
                $payment = $pdo->prepare(
                    "INSERT INTO payments (invoice_id, received_by, amount, payment_method, status, transaction_reference, payment_proof_path, paid_at, notes)
                     VALUES (:invoice_id, :received_by, :amount, 'bank_transfer', 'pending', :reference, :proof, NOW(), 'Menunggu verifikasi pemilik kos.')"
                );
                $payment->execute([
                    'invoice_id' => $invoiceId, 'received_by' => $user['id'],
                    'amount' => $paymentAmount,
                    'reference' => post_value('transaction_reference') ?: null,
                    'proof' => 'uploads/payments/' . $filename,
                ]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($paymentPath && is_file($paymentPath) && !unlink($paymentPath)) {
                    error_log('Unable to remove uncommitted payment proof: ' . $paymentPath);
                }
                throw $exception;
            }
            set_flash('success', 'Bukti transfer berhasil dikirim dan sedang menunggu verifikasi pemilik kos.');
            redirect('my_bookings');
        }

        if (in_array($action, ['approve_payment_proof', 'reject_payment_proof'], true)) {
            if (!in_array($user['role'], ['admin', 'operator'], true)) {
                set_flash('error', 'Hanya pemilik kos yang dapat memverifikasi pembayaran.');
                redirect('home');
            }
            $paymentId = (int) post_value('payment_id');
            $decision = $action === 'approve_payment_proof' ? 'confirmed' : 'rejected';
            $returnPage = $user['role'] === 'admin' ? 'payments' : 'payment_confirmations';
            $pdo->beginTransaction();
            try {
                $paymentQuery = $pdo->prepare(
                    "SELECT p.id, p.invoice_id, p.amount, i.amount_due, i.status AS invoice_status,
                           r.id AS rental_id, r.status AS rental_status, b.owner_user_id
                     FROM payments p JOIN invoices i ON i.id=p.invoice_id
                     JOIN rentals r ON r.id=i.rental_id
                    JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
                    WHERE p.id=:payment_id AND p.status='pending'
                      AND (:is_admin=1 OR b.owner_user_id=:owner_id)
                    FOR UPDATE"
                );
                $paymentQuery->execute([
                    'payment_id' => $paymentId,
                    'is_admin' => $user['role'] === 'admin' ? 1 : 0,
                    'owner_id' => $user['id'],
                ]);
                $payment = $paymentQuery->fetch();
                if (!$payment) {
                    $pdo->rollBack();
                    set_flash('error', 'Bukti transfer tidak ditemukan atau sudah pernah diproses.');
                    redirect($returnPage);
                }
                $update = $pdo->prepare('UPDATE payments SET status=:status WHERE id=:id');
                $update->execute(['status' => $decision, 'id' => $paymentId]);
                if ($decision === 'confirmed') {
                    if ($payment['invoice_status'] !== 'open'
                        || !in_array($payment['rental_status'], ['upcoming', 'active'], true)) {
                        $pdo->rollBack();
                        set_flash('error', 'Tagihan atau masa booking sudah tidak dapat dikonfirmasi.');
                        redirect($returnPage);
                    }
                    $total = $pdo->prepare(
                        "SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=:invoice_id AND status='confirmed'"
                    );
                    $total->execute(['invoice_id' => $payment['invoice_id']]);
                    if ((float) $total->fetchColumn() >= (float) $payment['amount_due']) {
                        $invoiceUpdate = $pdo->prepare("UPDATE invoices SET status='paid' WHERE id=:id");
                        $invoiceUpdate->execute(['id' => $payment['invoice_id']]);
                        $rentalUpdate = $pdo->prepare(
                            "UPDATE rentals SET status='active', booking_expires_at=NULL WHERE id=:id AND status='upcoming'"
                        );
                        $rentalUpdate->execute(['id' => $payment['rental_id']]);
                    }
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            set_flash('success', $decision === 'confirmed'
                ? 'Pembayaran disetujui. Booking telah dikonfirmasi dan kamar tercatat terisi.'
                : 'Bukti pembayaran ditolak. Pelanggan dapat mengunggah bukti baru.');
            redirect($returnPage);
        }

        
}

function handle_bank_settings_action(PDO $pdo, array $user, string $action): void
{
if ($action === 'save_bank_settings') {
            if (!in_array($user['role'], ['admin', 'operator'], true)) {
                set_flash('error', 'Anda tidak memiliki akses mengatur rekening kos.');
                redirect('my_bookings');
            }
            $buildingId = (int) post_value('building_id');
            if (!$buildingId || !can_manage_building($pdo, $user, $buildingId)) {
                set_flash('error', 'Pilih kos yang dapat Anda kelola.');
                redirect('bank_settings');
            }
            $bankName = post_value('bank_name');
            $accountName = post_value('account_name');
            $accountNumber = post_value('account_number');
            if ($bankName === '' || strlen($bankName) > 100 || $accountName === ''
                || strlen($accountName) > 150 || $accountNumber === '' || strlen($accountNumber) > 60) {
                set_flash('error', 'Nama bank, nama pemilik rekening, dan nomor rekening wajib diisi.');
                redirect('bank_settings&id=' . $buildingId);
            }
            $settings = $pdo->prepare(
                'INSERT INTO bank_transfer_settings (building_id, bank_name, account_name, account_number, instructions, updated_by)
                 VALUES (:building_id, :bank_name, :account_name, :account_number, :instructions, :updated_by)
                 ON DUPLICATE KEY UPDATE bank_name=VALUES(bank_name), account_name=VALUES(account_name),
                    account_number=VALUES(account_number), instructions=VALUES(instructions), updated_by=VALUES(updated_by)'
            );
            $settings->execute([
                'building_id' => $buildingId,
                'bank_name' => $bankName, 'account_name' => $accountName,
                'account_number' => $accountNumber,
                'instructions' => post_value('instructions') ?: null, 'updated_by' => $user['id'],
            ]);
            set_flash('success', 'Informasi rekening transfer berhasil disimpan.');
            redirect('bank_settings&id=' . $buildingId);
        }

        
}

function handle_invoice_action(PDO $pdo, array $user, string $action): void
{
if ($action === 'create_invoice') {
            if (!in_array($user['role'], ['admin', 'operator'], true)) {
                set_flash('error', 'Anda tidak memiliki akses membuat tagihan.');
                redirect('dashboard');
            }
            $rentalId = (int) post_value('rental_id');
            $periodStart = post_value('period_start');
            $periodEnd = post_value('period_end');
            $dueDate = post_value('due_date');
            $amount = post_value('amount_due');
            $rentalQuery = $pdo->prepare('SELECT id, monthly_rate FROM rentals WHERE id=:id');
            $rentalQuery->execute(['id' => $rentalId]);
            $rental = $rentalQuery->fetch();
            if (!$rental || ($user['role'] === 'operator' && !can_manage_rental($pdo, $user, $rentalId))
                || !$periodStart || !$periodEnd || !$dueDate || $periodEnd < $periodStart
                || !is_numeric($amount) || (float) $amount <= 0) {
                set_flash('error', 'Periksa sewa, rentang periode, tanggal jatuh tempo, dan nominal tagihan.');
                redirect('invoice_form');
            }

            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $statement = $pdo->prepare(
                "INSERT INTO invoices (rental_id, invoice_number, period_start, period_end, due_date, amount_due, status, notes) VALUES (:rental_id, :invoice_number, :period_start, :period_end, :due_date, :amount_due, 'open', :notes)"
            );
            $statement->execute([
                'rental_id' => $rentalId, 'invoice_number' => $invoiceNumber,
                'period_start' => $periodStart, 'period_end' => $periodEnd,
                'due_date' => $dueDate, 'amount_due' => $amount,
                'notes' => post_value('notes') ?: null,
            ]);
            set_flash('success', 'Tagihan ' . $invoiceNumber . ' berhasil dibuat.');
            redirect('invoices');
        }

        
}

function handle_manual_payment_action(PDO $pdo, array $user, string $action): void
{
if ($action === 'save_operator_payment') {
            if ($user['role'] !== 'operator' || post_value('confirm_operator_payment') !== 'yes') {
                set_flash('error', 'Konfirmasi pencatatan pembayaran wajib diberikan.');
                redirect('operator_payment');
            }
            $accountType = post_value('account_type');
            $roomId = (int) post_value('room_id');
            $periodValue = post_value('period');
            $amountType = post_value('amount_type');
            $paymentMethod = post_value('payment_method');
            $amountValue = post_value('custom_amount');
            $parsedPeriod = preg_match('/^\d{4}-\d{2}$/', $periodValue)
                ? DateTimeImmutable::createFromFormat('!Y-m', $periodValue)
                : false;
            if (!in_array($accountType, ['existing', 'new'], true)
                || !in_array($amountType, ['room_rate', 'custom'], true)
                || !in_array($paymentMethod, ['cash', 'bank_transfer', 'e_wallet', 'other'], true)
                || !$parsedPeriod || $parsedPeriod->format('Y-m') !== $periodValue
                || !in_array($periodValue, [
                    date('Y-m'),
                    date('Y-m', strtotime('first day of next month')),
                ], true)
                || $roomId < 1) {
                set_flash('error', 'Periksa jenis akun, periode, kamar, dan metode pembayaran.');
                redirect('operator_payment');
            }
            if ($amountType === 'custom' && (!is_numeric($amountValue) || (float) $amountValue <= 0)) {
                set_flash('error', 'Nominal khusus harus lebih besar dari nol.');
                redirect('operator_payment');
            }

            $profilePath = null;
            if ($accountType === 'new') {
                $name = post_value('new_name');
                $email = strtolower(post_value('new_email'));
                $phone = post_value('new_phone');
                $gender = post_value('new_gender');
                $password = post_value('new_password');
                if ($name === '' || strlen($name) > 100
                    || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255
                    || $phone === '' || strlen($phone) > 30
                    || !in_array($gender, ['male', 'female'], true)
                    || strlen($password) < 8 || strlen($password) > 72) {
                    set_flash('error', 'Lengkapi data pengguna baru dan gunakan password minimal 8 karakter.');
                    redirect('operator_payment');
                }
                $profilePath = store_profile_photo_upload('new_profile_photo');
            } else {
                $name = $email = $phone = $gender = $password = null;
                if ((int) post_value('existing_user_id') < 1) {
                    set_flash('error', 'Pilih akun Araweb dari hasil pencarian terlebih dahulu.');
                    redirect('operator_payment');
                }
            }

            $periodStart = DateTimeImmutable::createFromFormat('!Y-m-d', $periodValue . '-01');
            if (!$periodStart) {
                set_flash('error', 'Periode pembayaran tidak valid.');
                redirect('operator_payment');
            }
            $periodEnd = $periodStart->modify('last day of this month');
            $pdo->beginTransaction();
            try {
                $roomQuery = $pdo->prepare(
                    "SELECT rm.id,rm.monthly_rate,rm.status FROM rooms rm
                     JOIN kos_buildings b ON b.id=rm.building_id
                     WHERE rm.id=:room_id AND b.owner_user_id=:owner_id
                       AND b.status='active' AND b.approval_status='approved'
                     FOR UPDATE"
                );
                $roomQuery->execute(['room_id' => $roomId, 'owner_id' => $user['id']]);
                $room = $roomQuery->fetch();
                if (!$room) {
                    throw new RuntimeException('Kamar tidak tersedia atau bukan milik properti Anda.');
                }

                $existingRental = null;
                if ($accountType === 'new') {
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
                } else {
                    $existingAccountQuery = $pdo->prepare(
                        "SELECT id,name,email,phone,gender,status
                         FROM users WHERE id=:id AND role='seeker' AND status='active' FOR UPDATE"
                    );
                    $existingAccountQuery->execute(['id' => (int) post_value('existing_user_id')]);
                    $existingAccount = $existingAccountQuery->fetch();
                    if (!$existingAccount) {
                        throw new RuntimeException('Akun Araweb tidak ditemukan atau tidak aktif. Cari dan pilih ulang.');
                    }
                    $userId = (int) $existingAccount['id'];
                    $name = $existingAccount['name'];
                    $email = $existingAccount['email'];
                    $phone = $existingAccount['phone'];
                    $gender = $existingAccount['gender'];
                    $currentRental = $pdo->prepare(
                        "SELECT r.id,r.room_id,r.monthly_rate,r.tenant_id
                         FROM rentals r JOIN tenants t ON t.id=r.tenant_id
                         WHERE t.user_id=:user_id AND r.status='active'
                           AND r.start_date<=CURDATE()
                           AND (r.end_date IS NULL OR r.end_date>=CURDATE())
                         ORDER BY r.start_date DESC,r.id DESC LIMIT 1 FOR UPDATE"
                    );
                    $currentRental->execute(['user_id' => $userId]);
                    $existingRental = $currentRental->fetch() ?: null;
                    if ($existingRental && (int) $existingRental['room_id'] !== $roomId) {
                        throw new RuntimeException('Pilih kamar hunian aktif milik akun tersebut untuk mencatat pembayaran bulan berikutnya.');
                    }
                }

                if ($existingRental) {
                    $rentalId = (int) $existingRental['id'];
                    $room['monthly_rate'] = $existingRental['monthly_rate'];
                } else {
                    $roomAvailability = $pdo->prepare(
                        "SELECT COUNT(*) FROM rentals r
                         WHERE r.room_id=:room_id AND r.status IN ('active','upcoming')
                           AND (r.status='active' OR r.booking_expires_at IS NULL OR r.booking_expires_at>NOW())"
                    );
                    $roomAvailability->execute(['room_id' => $roomId]);
                    if ($room['status'] !== 'available' || (int) $roomAvailability->fetchColumn() > 0) {
                        throw new RuntimeException('Kamar tidak tersedia atau sudah dihuni akun lain.');
                    }
                }

                $tenantQuery = $pdo->prepare(
                    'SELECT id FROM tenants WHERE user_id=:user_id FOR UPDATE'
                );
                $tenantQuery->execute(['user_id' => $userId]);
                $tenantId = $tenantQuery->fetchColumn();
                if ($tenantId) {
                    $pdo->prepare(
                        "UPDATE tenants SET full_name=:name,phone=:phone,email=:email,status='active'
                         WHERE id=:id"
                    )->execute([
                        'name' => $name, 'phone' => $phone ?: '-', 'email' => $email,
                        'id' => $tenantId,
                    ]);
                } else {
                    $pdo->prepare(
                        "INSERT INTO tenants (user_id,full_name,phone,email,status)
                         VALUES (:user_id,:name,:phone,:email,'active')"
                    )->execute([
                        'user_id' => $userId, 'name' => $name,
                        'phone' => $phone ?: '-', 'email' => $email,
                    ]);
                    $tenantId = (int) $pdo->lastInsertId();
                }

                $amount = $amountType === 'room_rate' ? (float) $room['monthly_rate'] : (float) $amountValue;
                if ($amount <= 0) {
                    throw new RuntimeException('Tarif kamar harus lebih besar dari nol.');
                }
                if (!$existingRental) {
                    $billingDay = min((int) date('j'), 28);
                    $rental = $pdo->prepare(
                        "INSERT INTO rentals
                         (tenant_id,room_id,start_date,monthly_rate,billing_day,next_due_date,
                          status,notes,created_by,booking_status)
                         VALUES (:tenant_id,:room_id,CURDATE(),:rate,:billing_day,
                          DATE_ADD(CURDATE(),INTERVAL 1 MONTH),'active',
                          'Hunian dibuat melalui input pembayaran operator.',:operator_id,'not_applicable')"
                    );
                    $rental->execute([
                        'tenant_id' => $tenantId, 'room_id' => $roomId,
                        'rate' => $room['monthly_rate'], 'billing_day' => $billingDay,
                        'operator_id' => $user['id'],
                    ]);
                    $rentalId = (int) $pdo->lastInsertId();
                }

                $invoiceLookup = $pdo->prepare(
                    'SELECT id,amount_due,status FROM invoices
                     WHERE rental_id=:rental_id AND period_start=:period_start
                     ORDER BY id DESC LIMIT 1 FOR UPDATE'
                );
                $invoiceLookup->execute([
                    'rental_id' => $rentalId,
                    'period_start' => $periodStart->format('Y-m-d'),
                ]);
                $existingInvoice = $invoiceLookup->fetch() ?: null;
                if ($existingInvoice && $existingInvoice['status'] === 'paid') {
                    throw new RuntimeException(
                        'Tagihan periode ' . date_id($periodStart->format('Y-m-d'))
                        . ' sudah dibayar. Ganti kolom periode ke bulan berikutnya.'
                    );
                }

                if ($existingInvoice) {
                    if ($existingInvoice['status'] !== 'open') {
                        throw new RuntimeException('Tagihan periode ini tidak dapat menerima pembayaran baru.');
                    }
                    $invoiceId = (int) $existingInvoice['id'];
                    $confirmedTotal = $pdo->prepare(
                        "SELECT COALESCE(SUM(amount),0) FROM payments
                         WHERE invoice_id=:invoice_id AND status='confirmed'"
                    );
                    $confirmedTotal->execute(['invoice_id' => $invoiceId]);
                    $remainingAmount = (float) $existingInvoice['amount_due'] - (float) $confirmedTotal->fetchColumn();
                    if ($remainingAmount <= 0) {
                        throw new RuntimeException(
                            'Tagihan periode ' . date_id($periodStart->format('Y-m-d'))
                            . ' sudah dibayar. Ganti kolom periode ke bulan berikutnya.'
                        );
                    }
                    if ($amount > $remainingAmount) {
                        throw new RuntimeException('Nominal pembayaran melebihi sisa tagihan periode ini.');
                    }
                } else {
                    $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                    $invoice = $pdo->prepare(
                        "INSERT INTO invoices
                         (rental_id,invoice_number,period_start,period_end,due_date,amount_due,status,notes)
                         VALUES (:rental_id,:invoice_number,:period_start,:period_end,:due_date,:amount,'open',:notes)"
                    );
                    $invoice->execute([
                        'rental_id' => $rentalId, 'invoice_number' => $invoiceNumber,
                        'period_start' => $periodStart->format('Y-m-d'),
                        'period_end' => $periodEnd->format('Y-m-d'),
                        'due_date' => $periodStart->format('Y-m-d'), 'amount' => $amount,
                        'notes' => $amountType === 'custom'
                            ? 'Tarif khusus untuk transaksi ini; harga kamar tidak diubah.'
                            : 'Pembayaran menggunakan tarif kamar.',
                    ]);
                    $invoiceId = (int) $pdo->lastInsertId();
                }
                $pdo->prepare(
                    "INSERT INTO payments (invoice_id,received_by,amount,payment_method,status,paid_at,notes)
                     VALUES (:invoice_id,:operator_id,:amount,:method,'confirmed',NOW(),:notes)"
                )->execute([
                    'invoice_id' => $invoiceId, 'operator_id' => $user['id'],
                    'amount' => $amount, 'method' => $paymentMethod,
                    'notes' => 'Pembayaran dicatat langsung oleh operator.',
                ]);
                $paymentTotal = $pdo->prepare(
                    "SELECT COALESCE(SUM(amount),0) FROM payments
                     WHERE invoice_id=:invoice_id AND status='confirmed'"
                );
                $paymentTotal->execute(['invoice_id' => $invoiceId]);
                if ((float) $paymentTotal->fetchColumn() >= (float) ($existingInvoice['amount_due'] ?? $amount)) {
                    $pdo->prepare("UPDATE invoices SET status='paid' WHERE id=:id")
                        ->execute(['id' => $invoiceId]);
                }
                if (!$existingRental) {
                    $pdo->prepare("UPDATE rooms SET status='occupied' WHERE id=:id")
                        ->execute(['id' => $roomId]);
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($profilePath !== null && is_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . $profilePath)
                    && !unlink(dirname(__DIR__) . DIRECTORY_SEPARATOR . $profilePath)) {
                    error_log('Unable to remove uncommitted profile photo: ' . $profilePath);
                }
                throw $exception;
            }
            set_flash('success', 'Pembayaran dan hunian berhasil dicatat.');
            redirect('payments');
        }

if ($action === 'create_payment') {
            if (!in_array($user['role'], ['admin', 'operator'], true)) {
                set_flash('error', 'Anda tidak memiliki akses mencatat pembayaran.');
                redirect('dashboard');
            }
            $invoiceId = (int) post_value('invoice_id');
            $amount = post_value('amount');
            if (!is_numeric($amount) || (float) $amount <= 0) {
                set_flash('error', 'Pembayaran tidak valid atau melebihi sisa tagihan.');
                redirect('payments');
            }
            $method = post_value('payment_method');
            if (!in_array($method, ['cash', 'bank_transfer', 'e_wallet', 'other'], true)) {
                set_flash('error', 'Metode pembayaran tidak valid.');
                redirect('payments');
            }

            $pdo->beginTransaction();
            try {
                $invoiceQuery = $pdo->prepare(
                    "SELECT i.amount_due,r.id AS rental_id,r.status AS rental_status,
                            r.booking_status,r.booking_expires_at,b.owner_user_id
                     FROM invoices i JOIN rentals r ON r.id=i.rental_id
                     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
                     WHERE i.id=:id AND i.status<>'void' FOR UPDATE"
                );
                $invoiceQuery->execute(['id' => $invoiceId]);
                $invoice = $invoiceQuery->fetch();
                $pending = $pdo->prepare(
                    "SELECT COUNT(*) FROM payments WHERE invoice_id=:id AND status='pending'"
                );
                $pending->execute(['id' => $invoiceId]);
                $hasPendingProof = (int) $pending->fetchColumn() > 0;
                $sum = $pdo->prepare(
                    "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id=:id AND status='confirmed'"
                );
                $sum->execute(['id' => $invoiceId]);
                $paid = (float) $sum->fetchColumn();
                if (!$invoice || ($user['role'] === 'operator'
                        && (int) $invoice['owner_user_id'] !== (int) $user['id'])
                    || ($invoice['rental_status'] === 'upcoming'
                        && (!in_array($invoice['booking_status'], ['approved', 'not_applicable'], true)
                            || ($invoice['booking_status'] === 'approved'
                                && $invoice['booking_expires_at']
                                && strtotime($invoice['booking_expires_at']) <= time())))
                    || $hasPendingProof
                    || (float) $amount > (float) $invoice['amount_due'] - $paid) {
                    $pdo->rollBack();
                    set_flash('error', 'Pembayaran tidak valid atau melebihi sisa tagihan.');
                    redirect('payments');
                }

                $payment = $pdo->prepare(
                    "INSERT INTO payments (invoice_id, received_by, amount, payment_method, status, transaction_reference, paid_at, notes) VALUES (:invoice_id, :received_by, :amount, :payment_method, 'confirmed', :transaction_reference, NOW(), :notes)"
                );
                $payment->execute([
                    'invoice_id' => $invoiceId, 'received_by' => $user['id'],
                    'amount' => $amount, 'payment_method' => $method,
                    'transaction_reference' => post_value('transaction_reference') ?: null,
                    'notes' => post_value('notes') ?: null,
                ]);
                $invoiceStatus = $paid + (float) $amount >= (float) $invoice['amount_due'] ? 'paid' : 'open';
                $update = $pdo->prepare('UPDATE invoices SET status=:status WHERE id=:id');
                $update->execute(['status' => $invoiceStatus, 'id' => $invoiceId]);
                if ($invoiceStatus === 'paid') {
                    $activateRental = $pdo->prepare(
                        "UPDATE rentals r JOIN invoices i ON i.rental_id=r.id
                         SET r.status='active', r.booking_expires_at=NULL
                         WHERE i.id=:invoice_id AND r.status='upcoming'
                           AND r.booking_status IN ('approved','not_applicable')"
                    );
                    $activateRental->execute(['invoice_id' => $invoiceId]);
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            set_flash('success', 'Pembayaran berhasil dicatat.');
            redirect('payments');
        }

        
}
