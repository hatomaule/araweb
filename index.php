<?php
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/modules.php';
require_once __DIR__ . '/accounts/auth_actions.php';
require_once __DIR__ . '/accounts/act.php';
require_once __DIR__ . '/admin/act.php';
require_once __DIR__ . '/portal/act.php';
require_once __DIR__ . '/properties/act.php';
require_once __DIR__ . '/bookings/act.php';
require_once __DIR__ . '/payments/act.php';

$allowedPages = [
    'home', 'search', 'property', 'room', 'dashboard', 'buildings', 'building_form', 'gallery',
    'tenants', 'tenant_form', 'rooms', 'room_form', 'rentals', 'rental_form',
    'invoices', 'invoice_form', 'operator_payment', 'payments', 'bank_settings', 'my_bookings',
    'my_payments', 'tenant_payments', 'booking_form', 'payment_proof', 'payment_confirmations', 'profile', 'search_history',
    'platform_dashboard', 'platform_owners', 'platform_accounts',
    'platform_operator_accounts', 'platform_seeker_accounts', 'platform_unhoused_users',
    'platform_operator_profile', 'platform_user_profile', 'platform_user_payments',
    'platform_listings',
    'platform_reports', 'operator_reports', 'user_reports', 'change_password',
    'operator_bookings', 'tenant_profile', 'facebook_login',
    'facebook_callback', 'login', 'register', 'setup', 'forgot_password', 'reset_password',
];
$page = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : 'home';
if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}
$pageModule = page_module($page);

try {
    $pdo = db();
    $userCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    $pdo->beginTransaction();
    $expireInvoices = $pdo->prepare(
        "UPDATE invoices i JOIN rentals r ON r.id=i.rental_id
         SET i.status='void'
         WHERE i.status='open' AND r.status='upcoming'
           AND r.booking_expires_at IS NOT NULL AND r.booking_expires_at<=NOW()
           AND NOT EXISTS (
               SELECT 1 FROM payments p WHERE p.invoice_id=i.id AND p.status='pending'
           )"
    );
    $expireInvoices->execute();
    $expireBookings = $pdo->prepare(
        "UPDATE rentals r SET r.status='cancelled'
         WHERE r.status='upcoming' AND r.booking_expires_at IS NOT NULL
           AND r.booking_expires_at<=NOW()
           AND NOT EXISTS (
               SELECT 1 FROM invoices i JOIN payments p ON p.invoice_id=i.id
               WHERE i.rental_id=r.id AND p.status='pending'
           )"
    );
    $expireBookings->execute();
    $pdo->commit();
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Kos application database error: ' . $exception->getMessage());
    http_response_code(500);
    ?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Database belum siap</title><link rel="stylesheet" href="style.css"></head><body><main class="auth-wrap"><section class="auth-card"><div class="brand-mark">K</div><h1>Database belum dapat diakses</h1><p>Pastikan MySQL berjalan dan database <code>manajemen_kos</code> beserta tabelnya sudah dibuat. Periksa juga pengaturan koneksi di <code>config.php</code>.</p></section></main></body></html><?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf()) {
        set_flash('error', 'Formulir kedaluwarsa. Silakan coba lagi.');
        redirect($page);
    }

    $action = post_value('action');
    try {
        handle_auth_action($pdo, $userCount, $page, $action);
        $user = current_user();
        if (!$user) {
            redirect($userCount === 0 ? 'setup' : 'login');
        }

        handle_admin_action($pdo, $user, $action);
        handle_operator_action($pdo, $user, $action);

        handle_account_action($pdo, $user, $action);
        handle_portal_action($pdo, $user, $action);
        handle_customer_booking_action($pdo, $user, $action);
        handle_booking_payment_action($pdo, $user, $action);
        handle_bank_settings_action($pdo, $user, $action);
        handle_property_action($pdo, $user, $action);
        handle_rental_action($pdo, $user, $action);
        handle_invoice_action($pdo, $user, $action);
        handle_manual_payment_action($pdo, $user, $action);
        set_flash('error', 'Aksi tidak dikenal.');
        redirect('dashboard');
    } catch (RuntimeException $exception) {
        error_log('Kos application operation failed: ' . $exception->getMessage());
        set_flash('error', $exception->getMessage());
        if ($action === 'save_room') {
            $roomId = (int) post_value('id');
            redirect('room_form' . ($roomId > 0 ? '&id=' . $roomId : ''));
        }
        if ($action === 'update_building_profile_photo') {
            redirect('building_form&id=' . max(0, (int) post_value('building_id')));
        }
        if ($action === 'update_room_profile_photo') {
            redirect('room_form&id=' . max(0, (int) post_value('room_id')));
        }
        if ($action === 'delete_room_photo') {
            redirect('room_form&id=' . max(0, (int) post_value('room_id')));
        }
        if ($action === 'admin_update_operator_profile') {
            redirect((post_value('account_role') === 'seeker' ? 'platform_user_profile' : 'platform_operator_profile')
                . '&id=' . max(0, (int) post_value('user_id')));
        }
        if (in_array($action, ['upload_room_type_gallery_photos', 'delete_room_type_gallery_photo', 'select_room_gallery_photo'], true)) {
            redirect('gallery&building_id=' . max(0, (int) post_value('building_id'))
                . '&room_type=' . rawurlencode(post_value('room_type')));
        }
        if ($action === 'save_profile') {
            redirect('profile');
        }
        redirect($page);
    } catch (PDOException $exception) {
        error_log('Kos application database operation failed: ' . $exception->getMessage());
        $databaseErrorMessage = in_array($action, ['upload_room_type_gallery_photos', 'delete_room_type_gallery_photo', 'select_room_gallery_photo'], true)
            ? 'Galeri tidak dapat diperbarui. Pastikan migrasi galeri tipe kamar sudah diterapkan, lalu coba lagi.'
            : (in_array($action, ['save_profile', 'admin_update_operator_profile'], true)
            ? 'Profil tidak dapat disimpan. Pastikan migrasi wilayah Indonesia sudah diterapkan dan email belum digunakan akun lain.'
            : ($action === 'save_building'
                ? 'Data gedung tidak dapat disimpan. Pastikan migrasi wilayah Indonesia sudah diterapkan.'
            : ($action === 'save_operator_payment'
                ? 'Pembayaran tidak dapat disimpan. Periksa email/telepon, kamar, dan periode pembayaran.'
                : (in_array($action, ['operator_review_reply', 'operator_review_visibility'], true)
                    ? 'Ulasan tidak dapat diperbarui. Pastikan migrasi balasan ulasan telah diterapkan.'
                    : 'Data tidak dapat disimpan. Pastikan tidak ada nomor identitas atau kamar yang duplikat.'))));
        set_flash('error', $databaseErrorMessage);
        if ($action === 'admin_update_operator_profile') {
            redirect((post_value('account_role') === 'seeker' ? 'platform_user_profile' : 'platform_operator_profile')
                . '&id=' . max(0, (int) post_value('user_id')));
        }
        if (in_array($action, ['upload_room_type_gallery_photos', 'delete_room_type_gallery_photo', 'select_room_gallery_photo'], true)) {
            redirect('gallery&building_id=' . max(0, (int) post_value('building_id'))
                . '&room_type=' . rawurlencode(post_value('room_type')));
        }
        if ($action === 'save_profile') {
            redirect('profile');
        }
        if ($action === 'update_building_profile_photo') {
            redirect('building_form&id=' . max(0, (int) post_value('building_id')));
        }
        if ($action === 'update_room_profile_photo') {
            redirect('room_form&id=' . max(0, (int) post_value('room_id')));
        }
        redirect($page);
    }
}

$user = current_user();
if (isset($_GET['region_options'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Silakan masuk untuk memuat data wilayah.']);
        exit;
    }

    $level = isset($_GET['level']) && is_string($_GET['level']) ? $_GET['level'] : '';
    $parentCode = isset($_GET['parent_code']) && is_string($_GET['parent_code'])
        ? $_GET['parent_code']
        : '';
    $parentLevels = [
        'regency' => 'province',
        'district' => 'regency',
        'village' => 'district',
    ];
    if ($level !== 'province'
        && (!isset($parentLevels[$level]) || !preg_match('/^[0-9.]{1,13}$/', $parentCode))) {
        http_response_code(400);
        echo json_encode(['error' => 'Permintaan data wilayah tidak valid.']);
        exit;
    }

    try {
        if ($level === 'province') {
            $statement = $pdo->query(
                "SELECT code,name FROM indonesia_regions WHERE level='province' ORDER BY name"
            );
        } else {
            $statement = $pdo->prepare(
                'SELECT child.code,child.name
                 FROM indonesia_regions child
                 JOIN indonesia_regions parent ON parent.code=child.parent_code
                 WHERE child.level=:level AND parent.level=:parent_level AND parent.code=:parent_code
                 ORDER BY child.name'
            );
            $statement->execute([
                'level' => $level,
                'parent_level' => $parentLevels[$level],
                'parent_code' => $parentCode,
            ]);
        }
        echo json_encode(['regions' => $statement->fetchAll()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (PDOException $exception) {
        error_log('Indonesia region lookup failed: ' . $exception->getMessage());
        http_response_code(503);
        echo json_encode(['error' => 'Data wilayah belum tersedia. Pastikan migrasi wilayah Indonesia telah diterapkan.']);
    } catch (JsonException $exception) {
        error_log('Indonesia region response encoding failed: ' . $exception->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Data wilayah tidak dapat dikirim saat ini.']);
    }
    exit;
}
if (isset($_GET['reverse_geocode'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    if (!$user || !in_array($user['role'], ['admin', 'operator'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Akses pencarian alamat ditolak.']);
        exit;
    }

    $latitude = isset($_GET['lat']) && is_string($_GET['lat']) ? $_GET['lat'] : '';
    $longitude = isset($_GET['lon']) && is_string($_GET['lon']) ? $_GET['lon'] : '';
    if (!is_numeric($latitude) || !is_numeric($longitude)
        || !is_finite((float) $latitude) || !is_finite((float) $longitude)
        || (float) $latitude < -90 || (float) $latitude > 90
        || (float) $longitude < -180 || (float) $longitude > 180) {
        http_response_code(400);
        echo json_encode(['error' => 'Koordinat lokasi tidak valid.']);
        exit;
    }

    $latitude = number_format((float) $latitude, 7, '.', '');
    $longitude = number_format((float) $longitude, 7, '.', '');
    $lockName = 'araweb_nominatim_reverse_geocode';
    $lockAcquired = false;
    try {
        $lock = $pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
        $lock->execute(['lock_name' => $lockName]);
        $lockAcquired = (int) $lock->fetchColumn() === 1;
        if (!$lockAcquired) {
            http_response_code(429);
            echo json_encode(['error' => 'Pencarian alamat sedang sibuk. Coba lagi sebentar.']);
            exit;
        }

        $cachedAddress = $pdo->prepare(
            'SELECT address,street_name FROM kos_buildings
             WHERE latitude=:latitude AND longitude=:longitude AND address IS NOT NULL
             ORDER BY id DESC LIMIT 1'
        );
        $cachedAddress->execute(['latitude' => $latitude, 'longitude' => $longitude]);
        $cached = $cachedAddress->fetch();
        if ($cached) {
            echo json_encode([
                'display_name' => $cached['address'],
                'street' => $cached['street_name'] ?? '',
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            exit;
        }

        sleep(1);
        $endpoint = getenv('ARAWEB_REVERSE_GEOCODER_URL')
            ?: 'https://nominatim.openstreetmap.org/reverse';
        $query = http_build_query([
            'format' => 'jsonv2',
            'lat' => $latitude,
            'lon' => $longitude,
            'zoom' => 18,
            'addressdetails' => 1,
            'accept-language' => 'id',
        ]);
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\n"
                    . "User-Agent: Araweb/1.0 (+https://github.com/hatomaule/araweb)\r\n",
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);
        set_error_handler(static function (int $severity, string $message): bool {
            throw new ErrorException($message, 0, $severity);
        });
        try {
            $responseBody = file_get_contents($endpoint . '?' . $query, false, $context);
            $responseHeaders = $http_response_header ?? [];
        } finally {
            restore_error_handler();
        }
        if ($responseBody === false
            || !isset($responseHeaders[0])
            || !preg_match('/\s200\s/', $responseHeaders[0])) {
            throw new RuntimeException('Reverse geocoder returned an unsuccessful response.');
        }

        $result = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($result) || empty($result['display_name'])) {
            throw new RuntimeException('Reverse geocoder response did not include an address.');
        }
        $addressDetails = is_array($result['address'] ?? null) ? $result['address'] : [];
        $street = $addressDetails['road']
            ?? $addressDetails['pedestrian']
            ?? $addressDetails['footway']
            ?? $addressDetails['path']
            ?? '';
        if (!empty($addressDetails['house_number'])) {
            $street = trim($street . ' ' . $addressDetails['house_number']);
        }
        echo json_encode([
            'display_name' => $result['display_name'],
            'street' => $street,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (PDOException $exception) {
        error_log('Reverse geocoding database request failed: ' . $exception->getMessage());
        http_response_code(503);
        echo json_encode(['error' => 'Pencarian alamat tidak tersedia saat ini.']);
    } catch (ErrorException|JsonException|RuntimeException $exception) {
        error_log('Reverse geocoding failed: ' . $exception->getMessage());
        http_response_code(502);
        echo json_encode(['error' => 'Alamat otomatis gagal dimuat. Lokasi tetap dapat disimpan.']);
    } finally {
        if ($lockAcquired) {
            try {
                $releaseLock = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
                $releaseLock->execute(['lock_name' => $lockName]);
            } catch (PDOException $exception) {
                error_log('Reverse geocoding rate-limit lock release failed: ' . $exception->getMessage());
            }
        }
    }
    exit;
}
if ($user) {
    $pdo->prepare('UPDATE users SET last_seen_at=NOW() WHERE id=:id')
        ->execute(['id' => $user['id']]);
    $activityLoggedAt = (int) ($_SESSION['activity_logged_at'][$user['id']] ?? 0);
    if ($activityLoggedAt <= time() - 300) {
        try {
            $pdo->prepare(
                "INSERT INTO user_activity_events (user_id,account_role,occurred_at)
                 VALUES (:user_id,:account_role,NOW())"
            )->execute(['user_id' => $user['id'], 'account_role' => $user['role']]);
            $_SESSION['activity_logged_at'][$user['id']] = time();
            unset($_SESSION['activity_log_migration_notice']);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1146
                || !str_contains($exception->getMessage(), 'user_activity_events')) {
                throw $exception;
            }
            if (empty($_SESSION['activity_log_migration_notice'])) {
                set_flash(
                    'error',
                    'Log aktivitas belum aktif. Buat backup database, lalu jalankan migration_account_activity_and_deletion.sql.'
                );
                $_SESSION['activity_log_migration_notice'] = true;
            }
        }
    }
}
if (isset($_GET['account_lookup'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    if (!$user || $user['role'] !== 'operator') {
        http_response_code(403);
        echo json_encode(['error' => 'Akses pencarian akun ditolak.']);
        exit;
    }
    $term = substr(trim((string) ($_GET['term'] ?? '')), 0, 100);
    if (strlen($term) < 2) {
        echo json_encode(['accounts' => []]);
        exit;
    }
    $lookup = $pdo->prepare(
        "SELECT u.id,u.name,u.email,u.phone,u.gender
         FROM users u
         WHERE u.role='seeker' AND u.status='active'
           AND (u.name LIKE :name_term ESCAPE '!'
                OR u.email LIKE :email_term ESCAPE '!'
                OR u.phone LIKE :phone_term ESCAPE '!')
         ORDER BY u.name,u.id LIMIT 10"
    );
    $searchTerm = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';
    $lookup->execute([
        'name_term' => $searchTerm, 'email_term' => $searchTerm, 'phone_term' => $searchTerm,
    ]);
    echo json_encode(['accounts' => $lookup->fetchAll()], JSON_UNESCAPED_UNICODE);
    exit;
}
if (in_array($page, ['home', 'search', 'property', 'room'], true)) {
    $pdo->prepare(
        "INSERT INTO property_activity_events (user_id,visitor_key,event_type)
         VALUES (:user_id,:visitor_key,'visit')"
    )->execute([
        'user_id' => $user['id'] ?? null,
        'visitor_key' => analytics_visitor_key(),
    ]);
}
if ($userCount === 0 && (!$user || $user['role'] !== 'admin')
    && !in_array($page, ['home', 'search', 'property', 'room', 'setup', 'login', 'register',
        'forgot_password', 'reset_password',
        'facebook_login', 'facebook_callback'], true)) {
    redirect('setup');
}
if ($userCount > 0 && !$user
    && !in_array($page, ['home', 'search', 'property', 'room', 'login', 'register',
        'forgot_password', 'reset_password',
        'facebook_login', 'facebook_callback'], true)) {
    redirect('login');
}
if ($user && in_array($page, ['login', 'register', 'setup', 'forgot_password', 'reset_password'], true)) {
    redirect($user['role'] === 'admin' ? 'platform_dashboard'
        : ($user['role'] === 'operator' ? 'dashboard' : 'home'));
}
if ($user && $user['role'] === 'admin' && $page === 'platform_accounts') {
    redirect('platform_operator_accounts');
}
if ($user && $user['role'] === 'admin' && $page === 'platform_owners') {
    redirect('platform_operator_accounts');
}
if ($user && $user['role'] === 'admin' && $page === 'platform_reports') {
    redirect('platform_dashboard');
}
if ($user && $user['role'] === 'admin'
    && in_array($page, [
        'search_history', 'my_bookings', 'booking_form', 'my_payments', 'operator_reports', 'user_reports',
    ], true)) {
    redirect('platform_dashboard');
}
if ($user && $user['role'] === 'seeker'
    && !in_array($page, ['home', 'search', 'property', 'room', 'my_bookings', 'my_payments', 'booking_form',
        'payment_proof', 'profile', 'search_history', 'user_reports', 'change_password'], true)) {
    redirect('home');
}
if ($user && $user['role'] === 'operator' && $page === 'operator_reports') {
    redirect('dashboard');
}
if ($user && $user['role'] === 'operator'
    && !in_array($page, ['home', 'search', 'dashboard', 'buildings', 'building_form', 'gallery', 'rooms',
        'room_form', 'tenants', 'tenant_form', 'tenant_payments', 'invoices',
        'invoice_form', 'operator_payment', 'payments', 'tenant_profile',
        'operator_bookings', 'payment_confirmations', 'bank_settings', 'profile', 'payment_proof',
        'property', 'room', 'change_password'], true)) {
    redirect('dashboard');
}
if ($user && $user['role'] === 'admin'
    && in_array($page, ['operator_bookings', 'payment_confirmations'], true)) {
    redirect('platform_dashboard');
}
require __DIR__ . '/payments/proof.php';
$flash = take_flash();
require __DIR__ . '/accounts/auth.php';

function rows(PDO $pdo, string $sql, array $params = []): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function one(PDO $pdo, string $sql, array $params = []): ?array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $record = $statement->fetch();
    return $record ?: null;
}

function page_title(string $page): string
{
    $titles = [
        'dashboard' => 'Laporan', 'tenants' => 'Penghuni', 'tenant_form' => 'Tambah penghuni',
        'buildings' => 'Gedung kos', 'building_form' => 'Form gedung', 'gallery' => 'Galeri foto',
        'rooms' => 'Kamar', 'room_form' => 'Form kamar', 'rentals' => 'Masa sewa',
        'rental_form' => 'Sewa baru', 'invoices' => 'Tagihan', 'invoice_form' => 'Buat tagihan',
        'payments' => 'Histori pembayaran', 'operator_payment' => 'Input Pembayaran',
        'payment_confirmations' => 'Konfirmasi Pembayaran', 'bank_settings' => 'Rekening',
        'my_bookings' => 'Pesanan saya', 'booking_form' => 'Pesan kamar',
        'profile' => 'Profil akun', 'platform_operator_profile' => 'Profil operator',
        'platform_user_profile' => 'Profil User',
        'platform_user_payments' => 'Profil pembayaran User',
        'operator_bookings' => 'Booking', 'tenant_profile' => 'Profil penghuni',
        'my_payments' => 'Riwayat pembayaran', 'tenant_payments' => 'Histori penghuni',
        'platform_dashboard' => 'Dashboard', 'platform_owners' => 'Akun operator',
        'platform_accounts' => 'Manajemen akun',
        'platform_operator_accounts' => 'Akun operator',
        'platform_seeker_accounts' => 'Akun User',
        'platform_unhoused_users' => 'User pengguna',
        'platform_listings' => 'Daftar kos', 'platform_reports' => 'Dashboard',
        'operator_reports' => 'Laporan', 'user_reports' => 'Laporan pembayaran',
        'change_password' => 'Ganti password',
    ];
    return $titles[$page] ?? 'Ringkasan';
}

function collect_photo_uploads(string $field): array
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]['name'] ?? null)) {
        return [];
    }

    $files = $_FILES[$field];
    $selected = [];
    foreach ($files['name'] as $index => $name) {
        $error = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Unggah foto gagal. Periksa ukuran berkas dan pengaturan upload PHP.');
        }
        $selected[] = [
            'name' => (string) $name,
            'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
            'size' => (int) ($files['size'][$index] ?? 0),
        ];
    }

    if (count($selected) > 4) {
        throw new RuntimeException('Maksimal 4 foto untuk setiap kategori dalam satu kali unggah.');
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($selected as &$file) {
        if ($file['size'] < 1 || $file['size'] > 4 * 1024 * 1024
            || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Setiap foto harus berukuran maksimal 4 MB.');
        }

        $mimeType = $fileInfo->file($file['tmp_name']);
        $dimensions = @getimagesize($file['tmp_name']);
        if (!isset($allowedTypes[$mimeType]) || !$dimensions
            || $dimensions[0] > 8000 || $dimensions[1] > 8000) {
            throw new RuntimeException('Gunakan foto JPG, PNG, atau WebP dengan ukuran gambar maksimal 8000 × 8000 piksel.');
        }
        $file['extension'] = $allowedTypes[$mimeType];
    }
    unset($file);

    return $selected;
}

require __DIR__ . '/portal/public.php';
?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e(page_title($page)) ?> - Araweb</title><link rel="stylesheet" href="style.css?v=20261016-gallery-save"></head>
<body><div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="index.php"><span class="brand-mark">A</span><span>Araweb<small>Manajemen kos</small></span></a>
        <div class="nav-caption">MENU UTAMA</div>
        <nav class="main-nav">
            <?php
            $nav = [
                        'dashboard' => ['▤', 'Laporan'],
                        'operator_payment' => ['＋', 'Input Pembayaran'],
                        'payments' => ['▤', 'Histori pembayaran'],
            ];
            if ($user['role'] === 'seeker') {
                $nav = [
                    'home' => ['⌕', 'Cari kos'], 'my_bookings' => ['⌂', 'Booking saya'],
                    'my_payments' => ['▤', 'Riwayat pembayaran'],
                    'search_history' => ['◷', 'Riwayat pencarian'],
                ];
            } elseif ($user['role'] === 'admin') {
                $nav = [
                    'platform_dashboard' => ['⌂', 'Dashboard'],
                    'platform_listings' => ['⌖', 'Daftar kos'],
                ];
            }
            foreach ($nav as $target => [$icon, $label]):
            ?>
                <a class="<?= $page === $target || str_starts_with($page, rtrim($target, 's') . '_') ? 'active' : '' ?>" href="index.php?page=<?= e($target) ?>"><span class="nav-icon"><?= e($icon) ?></span><?= e($label) ?></a>
                <?php if ($user['role'] === 'operator' && $target === 'dashboard'): ?>
                    <?php $propertyMenuPages = ['buildings', 'building_form', 'rooms', 'room_form', 'gallery']; ?>
                    <details class="nav-group" <?= in_array($page, $propertyMenuPages, true) ? 'open' : '' ?>>
                        <summary class="<?= in_array($page, $propertyMenuPages, true) ? 'active' : '' ?>"><span class="nav-icon">⌂</span>Properti<span class="nav-chevron">⌄</span></summary>
                        <div class="nav-submenu">
                            <a class="<?= in_array($page, ['buildings', 'building_form'], true) ? 'active' : '' ?>" href="index.php?page=buildings">Gedung</a>
                            <a class="<?= in_array($page, ['rooms', 'room_form'], true) ? 'active' : '' ?>" href="index.php?page=rooms">Kamar</a>
                            <a class="<?= $page === 'gallery' ? 'active' : '' ?>" href="index.php?page=gallery">Galeri</a>
                        </div>
                    </details>
                    <a class="<?= in_array($page, ['tenants', 'tenant_form', 'tenant_profile', 'tenant_payments'], true) ? 'active' : '' ?>" href="index.php?page=tenants"><span class="nav-icon">♙</span>Penghuni</a>
                <?php endif; ?>
                <?php if ($user['role'] === 'operator' && $target === 'payments'): ?>
                    <?php $bookingMenuPages = ['operator_bookings', 'payment_confirmations']; ?>
                    <details class="nav-group" <?= in_array($page, $bookingMenuPages, true) ? 'open' : '' ?>>
                        <summary class="<?= in_array($page, $bookingMenuPages, true) ? 'active' : '' ?>"><span class="nav-icon">▣</span>Booking<span class="nav-chevron">⌄</span></summary>
                        <div class="nav-submenu">
                            <a class="<?= $page === 'operator_bookings' ? 'active' : '' ?>" href="index.php?page=operator_bookings">Daftar Booking</a>
                            <a class="<?= $page === 'payment_confirmations' ? 'active' : '' ?>" href="index.php?page=payment_confirmations">Konfirmasi Pembayaran</a>
                        </div>
                    </details>
                <?php endif; ?>
                <?php if ($user['role'] === 'admin' && $target === 'platform_dashboard'): ?>
                    <details class="nav-group" <?= in_array($page, ['platform_accounts', 'platform_operator_accounts', 'platform_seeker_accounts', 'platform_unhoused_users', 'platform_operator_profile', 'platform_user_profile', 'platform_user_payments'], true) ? 'open' : '' ?>>
                        <summary class="<?= in_array($page, ['platform_accounts', 'platform_operator_accounts', 'platform_seeker_accounts', 'platform_unhoused_users', 'platform_operator_profile', 'platform_user_profile', 'platform_user_payments'], true) ? 'active' : '' ?>"><span class="nav-icon">⚙</span>Manajemen akun<span class="nav-chevron">⌄</span></summary>
                        <div class="nav-submenu">
                            <a class="<?= in_array($page, ['platform_accounts', 'platform_operator_accounts', 'platform_operator_profile'], true) ? 'active' : '' ?>" href="index.php?page=platform_operator_accounts">Akun operator</a>
                            <a class="<?= in_array($page, ['platform_seeker_accounts', 'platform_user_profile', 'platform_user_payments'], true) ? 'active' : '' ?>" href="index.php?page=platform_seeker_accounts">Akun User</a>
                            <a class="<?= $page === 'platform_unhoused_users' ? 'active' : '' ?>" href="index.php?page=platform_unhoused_users">User pengguna</a>
                        </div>
                    </details>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </aside>
    <main class="main-area">
        <header class="topbar"><div><div class="eyebrow">MANAJEMEN KOS</div><h1><?= e(page_title($page)) ?></h1></div><div class="topbar-right"><span class="today"><?= e(date('d M Y')) ?></span><details class="account-menu"><summary><?= avatar_markup($user, 'profile-avatar small') ?><span><?= e($user['name']) ?><small><?= e(['admin'=>'Admin','operator'=>'Operator','seeker'=>'User'][$user['role']] ?? $user['role']) ?></small></span><span class="account-menu-chevron">⌄</span></summary>        <div class="account-menu-dropdown"><a href="index.php?page=profile">Profil</a><?php if ($user['role'] === 'operator'): ?><a href="index.php?page=bank_settings">Rekening</a><?php endif; ?><a href="index.php?page=change_password">Ganti password</a><button type="button" data-dialog-open="logout-dialog">Keluar</button></div></details></div></header>
        <section class="content">
            <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
            <?php if ($page === 'change_password'):
                require __DIR__ . '/accounts/change_password.php';
            elseif ($user['role'] === 'admin' && in_array($pageModule, ['admin', 'reports'], true)):
                require __DIR__ . '/admin/form.php';
            elseif ($user['role'] === 'operator' && $page === 'dashboard'):
                require __DIR__ . '/properties/operator_dashboard.php';
            elseif ($user['role'] === 'seeker' && $page === 'user_reports'):
                require __DIR__ . '/reports/user.php';
            elseif ($user['role'] === 'admin' && in_array($page, ['platform_operator_profile', 'platform_user_profile'], true)):
                require __DIR__ . '/admin/operator_profile.php';
            elseif ($page === 'profile' && $user['role'] !== 'seeker'):
                require __DIR__ . '/accounts/form.php';
            ?>
            <?php elseif ($user['role'] === 'seeker' && $page === 'profile'):
                require __DIR__ . '/accounts/form.php';
            ?>
            <?php elseif ($user['role'] === 'seeker' && $page === 'search_history'):
                require __DIR__ . '/portal/form.php';
            ?>
            <?php elseif ($user['role'] === 'operator' && $page === 'operator_bookings'):
                require __DIR__ . '/bookings/form.php';
            ?>
            <?php elseif ($user['role'] === 'seeker' && $page === 'my_bookings'):
                require __DIR__ . '/bookings/form.php';
            ?>
            <?php elseif ($user['role'] === 'seeker' && $page === 'booking_form'):
                require __DIR__ . '/bookings/form.php';
            ?>
            <?php elseif ($pageModule === 'properties'):
                require __DIR__ . '/properties/form.php';
            elseif ($pageModule === 'payments'):
                require __DIR__ . '/payments/form.php';
            endif; ?>
        </section>
    </main>
</div>
<dialog class="action-dialog logout-dialog" id="logout-dialog">
    <form method="post" class="action-dialog-form"><?= csrf_field() ?><input type="hidden" name="action" value="logout">
        <button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button>
        <span class="dialog-eyebrow">KEAMANAN AKUN</span><h2>Konfirmasi keluar</h2><p>Apakah kamu yakin ingin keluar dari akun?</p>
        <div class="dialog-actions"><button class="button secondary" type="button" data-dialog-close>Batal</button><button class="button danger" type="submit">Ya, keluar</button></div>
    </form>
</dialog>
<script src="app.js?v=20261016-data-tables"></script></body></html>
