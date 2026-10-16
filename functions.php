<?php
declare(strict_types=1);

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function avatar_markup(array $user, string $class = 'profile-avatar'): string
{
    $photo = $user['profile_photo_path'] ?? null;
    if ($photo) {
        return '<img class="' . e($class) . ' has-photo" src="' . e($photo) . '" alt="">';
    }
    $gender = $user['gender'] ?? null;
    if (in_array($gender, ['male', 'female'], true)) {
        $label = $gender === 'male' ? 'Avatar pria' : 'Avatar wanita';
        return '<img class="' . e($class) . ' has-photo" src="assets/avatar-' . e($gender) . '.svg" alt="' . $label . '">';
    }
    $initial = strtoupper(substr((string) ($user['name'] ?? '?'), 0, 1));
    return '<span class="' . e($class) . '">' . e($initial) . '</span>';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function valid_csrf(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function post_value(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key])
        ? trim($_POST[$key])
        : '';
}

function indonesia_region_fields(PDO $pdo, array $selectedCodes = [], bool $required = false): string
{
    $levels = [
        'province' => 'Provinsi',
        'regency' => 'Kabupaten/Kota',
        'district' => 'Kecamatan',
        'village' => 'Kelurahan/Desa',
    ];
    $selectedCodes = array_intersect_key($selectedCodes, $levels);
    $selectedNames = [];
    $parameters = [];
    foreach ($levels as $level => $_label) {
        $code = $selectedCodes[$level] ?? '';
        if (is_string($code) && $code !== '') {
            $placeholder = 'code_' . count($parameters);
            $parameters[$placeholder] = $code;
            $selectedNames[$code] = $placeholder;
        }
    }
    if ($parameters) {
        $placeholders = implode(',', array_map(
            static fn (string $placeholder): string => ':' . $placeholder,
            array_values($selectedNames)
        ));
        $statement = $pdo->prepare(
            'SELECT code,name FROM indonesia_regions WHERE code IN (' . $placeholders . ')'
        );
        $statement->execute($parameters);
        foreach ($statement->fetchAll() as $region) {
            $selectedNames[$region['code']] = $region['name'];
        }
    }

    $html = '<div class="indonesia-address-fields full-span" data-region-picker>';
    foreach ($levels as $level => $label) {
        $name = $level . '_code';
        $selectedCode = (string) ($selectedCodes[$level] ?? '');
        $html .= '<label class="field"><span>' . e($label) . ($required ? ' *' : '') . '</span>'
            . '<select name="' . e($name) . '" data-region-level="' . e($level)
            . '" data-selected-code="' . e($selectedCode) . '"' . ($required ? ' required' : '') . '>'
            . '<option value="">Pilih ' . e(strtolower($label)) . '</option>';
        if ($selectedCode !== '' && isset($selectedNames[$selectedCode])) {
            $html .= '<option value="' . e($selectedCode) . '" selected>'
                . e($selectedNames[$selectedCode]) . '</option>';
        }
        $html .= '</select></label>';
    }
    return $html . '</div>';
}

function resolve_indonesia_regions(PDO $pdo, bool $required = false): ?array
{
    $levels = ['province', 'regency', 'district', 'village'];
    $codes = [];
    foreach ($levels as $level) {
        $key = $level . '_code';
        if (isset($_POST[$key]) && !is_string($_POST[$key])) {
            throw new RuntimeException('Kode wilayah yang dipilih tidak valid.');
        }
        $code = post_value($key);
        if (strlen($code) > 13 || ($code !== '' && !preg_match('/^[0-9.]+$/', $code))) {
            throw new RuntimeException('Kode wilayah yang dipilih tidak valid.');
        }
        $codes[$level] = $code;
    }

    $provided = count(array_filter($codes, static fn (string $code): bool => $code !== ''));
    if ($provided === 0 && !$required) {
        return null;
    }
    if ($provided !== count($levels)) {
        throw new RuntimeException('Pilih Provinsi, Kabupaten/Kota, Kecamatan, dan Kelurahan/Desa secara lengkap.');
    }

    $statement = $pdo->prepare(
        "SELECT province.code AS province_code,province.name AS province_name,
                regency.code AS regency_code,regency.name AS regency_name,
                district.code AS district_code,district.name AS district_name,
                village.code AS village_code,village.name AS village_name
         FROM indonesia_regions province
         JOIN indonesia_regions regency
           ON regency.parent_code=province.code AND regency.level='regency'
         JOIN indonesia_regions district
           ON district.parent_code=regency.code AND district.level='district'
         JOIN indonesia_regions village
           ON village.parent_code=district.code AND village.level='village'
         WHERE province.code=:province AND province.level='province'
           AND regency.code=:regency AND district.code=:district AND village.code=:village"
    );
    $statement->execute([
        'province' => $codes['province'],
        'regency' => $codes['regency'],
        'district' => $codes['district'],
        'village' => $codes['village'],
    ]);
    $regions = $statement->fetch();
    if (!$regions) {
        throw new RuntimeException('Kombinasi wilayah yang dipilih tidak sesuai.');
    }

    return $regions;
}

function format_indonesia_address(?array $regions, string $street, string $rt, string $rw, string $detailAddress): ?string
{
    $parts = [];
    if ($street !== '') {
        $parts[] = $street;
    }
    if ($rt !== '' || $rw !== '') {
        $parts[] = 'RT ' . ($rt !== '' ? $rt : '-') . '/RW ' . ($rw !== '' ? $rw : '-');
    }
    if ($detailAddress !== '') {
        $parts[] = $detailAddress;
    }
    if ($regions === null) {
        return $parts ? implode(', ', $parts) : null;
    }
    array_push(
        $parts,
        $regions['village_name'],
        'Kecamatan ' . $regions['district_name'],
        $regions['regency_name'],
        $regions['province_name']
    );
    return implode(', ', $parts);
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function analytics_visitor_key(): string
{
    if (!isset($_SESSION['analytics_visitor_key']) || !is_string($_SESSION['analytics_visitor_key'])) {
        $_SESSION['analytics_visitor_key'] = hash('sha256', random_bytes(32));
    }

    return $_SESSION['analytics_visitor_key'];
}

function redirect(string $page = 'dashboard'): void
{
    $fragment = '';
    $fragmentPosition = strpos($page, '#');
    if ($fragmentPosition !== false) {
        $fragment = substr($page, $fragmentPosition + 1);
        $page = substr($page, 0, $fragmentPosition);
    }
    [$pageName, $query] = array_pad(explode('&', $page, 2), 2, '');
    $location = 'index.php?page=' . rawurlencode($pageName);
    if ($query !== '') {
        parse_str($query, $parameters);
        if ($parameters) {
            $location .= '&' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        }
    }
    if ($fragment !== '') {
        $location .= '#' . rawurlencode($fragment);
    }
    header('Location: ' . $location);
    exit;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $statement = db()->prepare(
        "SELECT id, name, email, phone, address, gender, profile_photo_path, avatar_key, role
         FROM users WHERE id = :id AND status = 'active' LIMIT 1"
    );
    $statement->execute(['id' => (int) $_SESSION['user_id']]);
    $user = $statement->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

function is_platform_admin(array $user): bool
{
    return $user['role'] === 'admin';
}

function can_manage_building(PDO $pdo, array $user, int $buildingId): bool
{
    if (is_platform_admin($user)) {
        return true;
    }
    if ($user['role'] !== 'operator') {
        return false;
    }
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM kos_buildings WHERE id=:building_id AND owner_user_id=:owner_id'
    );
    $statement->execute(['building_id' => $buildingId, 'owner_id' => $user['id']]);
    return (int) $statement->fetchColumn() === 1;
}

function can_manage_room(PDO $pdo, array $user, int $roomId): bool
{
    if (is_platform_admin($user)) {
        return true;
    }
    if ($user['role'] !== 'operator') {
        return false;
    }
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM rooms rm JOIN kos_buildings b ON b.id=rm.building_id
         WHERE rm.id=:room_id AND b.owner_user_id=:owner_id'
    );
    $statement->execute(['room_id' => $roomId, 'owner_id' => $user['id']]);
    return (int) $statement->fetchColumn() === 1;
}

function can_manage_rental(PDO $pdo, array $user, int $rentalId): bool
{
    if (is_platform_admin($user)) {
        return true;
    }
    if ($user['role'] !== 'operator') {
        return false;
    }
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM rentals r JOIN rooms rm ON rm.id=r.room_id
         JOIN kos_buildings b ON b.id=rm.building_id
         WHERE r.id=:rental_id AND b.owner_user_id=:owner_id'
    );
    $statement->execute(['rental_id' => $rentalId, 'owner_id' => $user['id']]);
    return (int) $statement->fetchColumn() === 1;
}

function can_manage_tenant(PDO $pdo, array $user, int $tenantId): bool
{
    if (is_platform_admin($user)) {
        return true;
    }
    if ($user['role'] !== 'operator') {
        return false;
    }
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM tenants t
         JOIN rentals r ON r.tenant_id=t.id
         JOIN rooms rm ON rm.id=r.room_id
         JOIN kos_buildings b ON b.id=rm.building_id
         WHERE t.id=:tenant_id AND b.owner_user_id=:owner_id'
    );
    $statement->execute(['tenant_id' => $tenantId, 'owner_id' => $user['id']]);
    return (int) $statement->fetchColumn() > 0;
}

function facebook_oauth_config(): ?array
{
    $appId = getenv('FACEBOOK_APP_ID') ?: '';
    $appSecret = getenv('FACEBOOK_APP_SECRET') ?: '';
    $redirectUri = getenv('FACEBOOK_REDIRECT_URI') ?: '';
    if ($appId === '' || $appSecret === '' || !filter_var($redirectUri, FILTER_VALIDATE_URL)
        || !in_array(parse_url($redirectUri, PHP_URL_SCHEME), ['http', 'https'], true)) {
        return null;
    }
    return ['app_id' => $appId, 'app_secret' => $appSecret, 'redirect_uri' => $redirectUri];
}

function facebook_graph_get(string $url): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Ekstensi cURL diperlukan untuk login Facebook.');
    }
    $handle = curl_init($url);
    if ($handle === false) {
        throw new RuntimeException('Facebook login tidak dapat dimulai di server ini.');
    }
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $response = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);
    if (!is_string($response) || $response === '' || $status < 200 || $status >= 300) {
        error_log('Facebook Graph API request failed: ' . ($curlError ?: 'HTTP ' . $status));
        throw new RuntimeException('Facebook tidak dapat memverifikasi akun saat ini. Silakan coba lagi.');
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Facebook mengirim respons yang tidak dapat dibaca.');
    }
    return $decoded;
}

function money($amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

function date_id(?string $date): string
{
    if (!$date) {
        return '-';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d-m-Y', $timestamp) : '-';
}

function field(string $label, string $name, string $value = '', string $type = 'text', array $attributes = []): void
{
    echo '<label class="field"><span>' . e($label) . '</span><input type="' . e($type)
        . '" name="' . e($name) . '" value="' . e($value) . '"';
    foreach ($attributes as $attribute => $attributeValue) {
        echo ' ' . e($attribute) . '="' . e($attributeValue) . '"';
    }
    echo '></label>';
}

function status_label(string $status): string
{
    $labels = [
        'active' => 'Aktif', 'inactive' => 'Nonaktif', 'available' => 'Kosong',
        'occupied' => 'Terisi', 'pending' => 'Pending', 'maintenance' => 'Perawatan',
        'reserved' => 'Booking disetujui',
        'upcoming' => 'Akan datang', 'ended' => 'Berakhir',
        'cancelled' => 'Dibatalkan', 'open' => 'Belum lunas', 'paid' => 'Lunas',
        'void' => 'Dibatalkan', 'confirmed' => 'Dikonfirmasi',
        'rejected' => 'Ditolak', 'approved' => 'Disetujui',
        'not_applicable' => 'Tidak berlaku',
    ];
    return $labels[$status] ?? ucfirst($status);
}

function store_profile_photo_upload(string $field = 'profile_photo'): ?string
{
    $photo = validate_image_upload($field, 'Foto profil');
    if (!$photo) {
        return null;
    }
    $directory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder foto profil tidak dapat dibuat.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $photo['extension'];
    if (!move_uploaded_file($photo['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Foto profil gagal disimpan.');
    }
    return 'uploads/profiles/' . $filename;
}

function validate_image_upload(string $field, string $purpose): ?array
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }

    $file = $_FILES[$field];
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK || !isset($file['tmp_name'])
        || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Unggah ' . $purpose . ' gagal. Silakan pilih ulang berkas.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > 4 * 1024 * 1024) {
        throw new RuntimeException($purpose . ' harus berukuran maksimal 4 MB.');
    }
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $dimensions = @getimagesize($file['tmp_name']);
    if (!isset($types[$mime]) || !$dimensions || $dimensions[0] > 8000 || $dimensions[1] > 8000) {
        throw new RuntimeException($purpose . ' harus berupa gambar JPG, PNG, atau WebP yang valid.');
    }
    return ['tmp_name' => $file['tmp_name'], 'extension' => $types[$mime]];
}

function delete_uploaded_image(string $relativePath, string $uploadDirectory, string $purpose): void
{
    $photoDirectory = realpath($uploadDirectory);
    $photoFile = realpath(__DIR__ . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    if ($photoDirectory === false || $photoFile === false || dirname($photoFile) !== $photoDirectory) {
        throw new RuntimeException('Lokasi berkas ' . $purpose . ' tidak valid; berkas tidak dihapus.');
    }
    if (!unlink($photoFile)) {
        throw new RuntimeException($purpose . ' tidak dapat dihapus. Periksa izin folder upload.');
    }
}

function validate_payment_proof(string $field): ?array
{
    return validate_image_upload($field, 'Bukti transfer');
}
