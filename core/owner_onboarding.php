<?php
declare(strict_types=1);

function owner_profile_completion(PDO $pdo, int $userId): array
{
    $profile = one(
        $pdo,
        'SELECT name,email,phone,gender,profile_photo_path,street_name,address,
                province_code,regency_code,district_code,village_code
         FROM users WHERE id=:id AND role=\'owner\'',
        ['id' => $userId]
    );
    if (!$profile) {
        return ['percent' => 0, 'completed' => 0, 'total' => 6, 'items' => []];
    }

    $hasAddress = trim((string) ($profile['street_name'] ?: $profile['address'])) !== ''
        && !empty($profile['province_code']) && !empty($profile['regency_code'])
        && !empty($profile['district_code']) && !empty($profile['village_code']);
    $items = [
        'Nama lengkap' => trim((string) $profile['name']) !== '',
        'Email' => filter_var($profile['email'], FILTER_VALIDATE_EMAIL) !== false,
        'Nomor telepon' => trim((string) $profile['phone']) !== '',
        'Alamat lengkap' => $hasAddress,
        'Jenis kelamin' => in_array($profile['gender'], ['male', 'female'], true),
        'Foto profil' => trim((string) $profile['profile_photo_path']) !== '',
    ];
    $completed = count(array_filter($items));

    return [
        'percent' => (int) round($completed * 100 / count($items)),
        'completed' => $completed,
        'total' => count($items),
        'items' => $items,
    ];
}

function building_completion(PDO $pdo, int $buildingId): array
{
    return building_completions($pdo, [$buildingId])[$buildingId] ?? [
        'percent' => 0, 'completed' => 0, 'total' => 9, 'core_complete' => false, 'items' => [],
    ];
}

function building_completions(PDO $pdo, array $buildingIds): array
{
    $buildingIds = array_values(array_unique(array_filter(
        array_map('intval', $buildingIds),
        static fn(int $buildingId): bool => $buildingId > 0
    )));
    if (!$buildingIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($buildingIds), '?'));
    $buildingQuery = $pdo->prepare(
        "SELECT id,name,address,latitude,longitude,description,profile_photo_path,
                general_facilities,house_rules
         FROM kos_buildings WHERE id IN ({$placeholders})"
    );
    $buildingQuery->execute($buildingIds);
    $buildings = [];
    foreach ($buildingQuery->fetchAll() as $building) {
        $buildings[(int) $building['id']] = $building;
    }

    $photoQuery = $pdo->prepare(
        "SELECT building_id,COUNT(*) AS photo_count
         FROM (
             SELECT building_id FROM property_photos WHERE building_id IN ({$placeholders})
             UNION ALL
             SELECT building_id FROM room_type_gallery_photos
             WHERE building_id IN ({$placeholders}) AND category='building'
         ) building_photos GROUP BY building_id"
    );
    $photoQuery->execute(array_merge($buildingIds, $buildingIds));
    $propertyPhotoCounts = [];
    foreach ($photoQuery->fetchAll() as $photoCount) {
        $propertyPhotoCounts[(int) $photoCount['building_id']] = (int) $photoCount['photo_count'];
    }

    $roomQuery = $pdo->prepare(
        "SELECT rm.id,rm.building_id,rm.room_number,rm.monthly_rate,rm.facilities,rm.profile_photo_path,
                (SELECT COUNT(*) FROM room_photos rp
                 WHERE rp.room_id=rm.id AND rp.category='room') AS photo_count,
                (SELECT COUNT(*) FROM room_type_gallery_photos rg
                 WHERE rg.category='room'
                   AND (rg.room_id=rm.id
                     OR (rg.room_id IS NULL AND rg.building_id=rm.building_id
                       AND BINARY rg.room_type=BINARY rm.room_type))) AS gallery_photo_count
         FROM rooms rm WHERE rm.building_id IN ({$placeholders}) ORDER BY rm.building_id,rm.id"
    );
    $roomQuery->execute($buildingIds);
    $roomsByBuilding = [];
    foreach ($roomQuery->fetchAll() as $room) {
        $roomsByBuilding[(int) $room['building_id']][] = $room;
    }

    $completions = [];
    foreach ($buildings as $buildingId => $building) {
        $rooms = $roomsByBuilding[$buildingId] ?? [];
        $roomImagesComplete = $rooms !== [];
        $roomRatesComplete = $rooms !== [];
        $roomFacilitiesComplete = $rooms !== [];
        $roomsMissingRate = [];
        $roomsMissingPhoto = [];
        $roomsMissingFacilities = [];
        foreach ($rooms as $room) {
            $roomLabel = 'Kamar ' . trim((string) $room['room_number']);
            $hasRoomPhoto = trim((string) $room['profile_photo_path']) !== ''
                || (int) $room['photo_count'] > 0 || (int) $room['gallery_photo_count'] > 0;
            $roomImagesComplete = $roomImagesComplete && $hasRoomPhoto;
            if (!$hasRoomPhoto) {
                $roomsMissingPhoto[] = $roomLabel;
            }
            $hasRoomRate = (float) $room['monthly_rate'] > 0;
            $roomRatesComplete = $roomRatesComplete && $hasRoomRate;
            if (!$hasRoomRate) {
                $roomsMissingRate[] = $roomLabel;
            }
            $hasRoomFacilities = trim((string) $room['facilities']) !== '';
            $roomFacilitiesComplete = $roomFacilitiesComplete
                && $hasRoomFacilities;
            if (!$hasRoomFacilities) {
                $roomsMissingFacilities[] = $roomLabel;
            }
        }

        $hasPropertyPhoto = trim((string) $building['profile_photo_path']) !== ''
            || ($propertyPhotoCounts[$buildingId] ?? 0) > 0;
        $missingDetails = [];
        foreach ([
            'Nama gedung' => trim((string) $building['name']) !== '',
            'Alamat gedung' => trim((string) $building['address']) !== '',
            'Titik lokasi peta' => $building['latitude'] !== null && $building['longitude'] !== null,
            'Deskripsi kos' => trim((string) $building['description']) !== '',
            'Foto gedung' => $hasPropertyPhoto,
            'Fasilitas umum' => trim((string) $building['general_facilities']) !== '',
            'Aturan kos' => trim((string) $building['house_rules']) !== '',
        ] as $label => $isComplete) {
            if (!$isComplete) {
                $missingDetails[] = $label;
            }
        }
        if ($rooms === []) {
            $missingDetails[] = 'Tambahkan minimal satu kamar';
        } else {
            if ($roomsMissingRate !== []) {
                $missingDetails[] = 'Isi tarif bulanan: ' . implode(', ', $roomsMissingRate);
            }
            if ($roomsMissingPhoto !== []) {
                $missingDetails[] = 'Tambahkan foto: ' . implode(', ', $roomsMissingPhoto);
            }
            if ($roomsMissingFacilities !== []) {
                $missingDetails[] = 'Isi fasilitas untuk ' . count($roomsMissingFacilities) . ' kamar';
            }
        }
        $items = [
            'Nama gedung' => trim((string) $building['name']) !== '',
            'Alamat gedung' => trim((string) $building['address']) !== '',
            'Titik lokasi peta' => $building['latitude'] !== null && $building['longitude'] !== null,
            'Deskripsi kos' => trim((string) $building['description']) !== '',
            'Foto gedung' => $hasPropertyPhoto,
            'Fasilitas umum' => trim((string) $building['general_facilities']) !== '',
            'Aturan kos' => trim((string) $building['house_rules']) !== '',
            'Data dan foto seluruh kamar' => $roomRatesComplete && $roomImagesComplete,
            'Fasilitas kamar' => $roomFacilitiesComplete,
        ];
        $completed = count(array_filter($items));
        $coreComplete = $items['Nama gedung'] && $items['Alamat gedung']
            && $items['Titik lokasi peta'] && $items['Foto gedung']
            && $rooms !== [] && $roomRatesComplete && $roomImagesComplete;
        $coreMissingDetails = [];
        foreach (['Nama gedung', 'Alamat gedung', 'Titik lokasi peta', 'Foto gedung'] as $coreItem) {
            if (!$items[$coreItem]) {
                $coreMissingDetails[] = $coreItem;
            }
        }
        if ($rooms === []) {
            $coreMissingDetails[] = 'Tambahkan minimal satu kamar';
        } else {
            if ($roomsMissingRate !== []) {
                $coreMissingDetails[] = 'Tarif bulanan belum diisi: ' . implode(', ', $roomsMissingRate);
            }
            if ($roomsMissingPhoto !== []) {
                $coreMissingDetails[] = 'Foto belum ditambahkan: ' . implode(', ', $roomsMissingPhoto);
            }
        }

        $completions[$buildingId] = [
            'percent' => (int) round($completed * 100 / count($items)),
            'completed' => $completed,
            'total' => count($items),
            'core_complete' => $coreComplete,
            'items' => $items,
            'missing_details' => $missingDetails,
            'core_missing_details' => $coreMissingDetails,
        ];
    }

    return $completions;
}

function owner_has_gold_verification(PDO $pdo, int $userId): bool
{
    return one(
        $pdo,
        "SELECT id FROM kos_buildings
         WHERE owner_user_id=:owner_id AND gold_verification_status='verified' LIMIT 1",
        ['owner_id' => $userId]
    ) !== null;
}

function mark_owner_listing_for_review(PDO $pdo, array $user, int $buildingId): void
{
    if (($user['role'] ?? null) !== 'owner' || $buildingId < 1) {
        return;
    }
    $pdo->prepare(
        "UPDATE kos_buildings
         SET listing_review_note='Perubahan tersimpan. Ajukan kembali agar Admin meninjau data terbaru.',
             publication_status='revision',approval_note='Perubahan Owner menunggu tinjauan Admin.',
             approval_status='revision',
             gold_verification_status=IF(gold_verification_status='verified','not_requested',gold_verification_status)
         WHERE id=:id AND owner_user_id=:owner_id
           AND publication_status IN ('published','pending')"
    )->execute(['id' => $buildingId, 'owner_id' => $user['id']]);
}

function owner_verification_storage_directory(): string
{
    $configured = getenv('OWNER_VERIFICATION_UPLOAD_DIR') ?: '';
    return $configured !== ''
        ? rtrim($configured, DIRECTORY_SEPARATOR)
        : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'ekosan-private'
            . DIRECTORY_SEPARATOR . 'owner_verification';
}

function store_owner_verification_evidence(): array
{
    $file = $_FILES['evidence_file'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !isset($file['tmp_name'], $file['name'], $file['size'])
        || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Pilih satu dokumen bukti yang berhasil diunggah.');
    }
    if ((int) $file['size'] < 1 || (int) $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Ukuran dokumen bukti maksimal 5 MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedTypes = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!is_string($mime) || !isset($allowedTypes[$mime])) {
        throw new RuntimeException('Dokumen bukti harus berupa PDF, JPG, PNG, atau WebP.');
    }
    $originalName = trim(basename(str_replace('\\', '/', (string) $file['name'])));
    $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?? '';
    $originalName = function_exists('mb_substr')
        ? mb_substr($originalName, 0, 255, 'UTF-8')
        : substr($originalName, 0, 255);
    if ($originalName === '') {
        throw new RuntimeException('Nama dokumen bukti tidak valid.');
    }

    $directory = owner_verification_storage_directory();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder bukti verifikasi privat tidak dapat dibuat.');
    }
    if (!chmod($directory, 0700)) {
        throw new RuntimeException('Izin folder bukti verifikasi privat tidak dapat diatur.');
    }
    $realDirectory = realpath($directory);
    $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if (!$realDirectory || ($documentRoot
        && ($realDirectory === $documentRoot
            || str_starts_with($realDirectory, $documentRoot . DIRECTORY_SEPARATOR)))) {
        throw new RuntimeException('Penyimpanan bukti harus berada di luar document root.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mime];
    $path = $directory . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $path)) {
        throw new RuntimeException('Dokumen bukti gagal disimpan. Periksa izin folder privat.');
    }
    if (!chmod($path, 0600)) {
        if (!unlink($path)) {
            error_log('Unable to remove owner verification file with unsafe permissions: ' . $path);
        }
        throw new RuntimeException('Izin dokumen bukti privat tidak dapat diatur.');
    }

    return [
        'filename' => $filename,
        'mime' => $mime,
        'original_name' => $originalName,
        'path' => $path,
    ];
}

function serve_owner_verification_evidence(PDO $pdo, ?array $user, int $requestId): void
{
    if (!$user || !in_array($user['role'], ['admin', 'owner'], true) || $requestId < 1) {
        http_response_code(403);
        exit('Akses dokumen ditolak.');
    }
    $request = one(
        $pdo,
        'SELECT evidence_file,evidence_mime,evidence_original_name,owner_user_id
         FROM owner_gold_verification_requests WHERE id=:id',
        ['id' => $requestId]
    );
    if (!$request || ($user['role'] !== 'admin'
        && (int) $request['owner_user_id'] !== (int) $user['id'])) {
        http_response_code(404);
        exit('Dokumen tidak ditemukan.');
    }
    $filename = (string) $request['evidence_file'];
    $mime = (string) $request['evidence_mime'];
    if (!preg_match('/\A[a-f0-9]{32}\.(?:pdf|jpg|png|webp)\z/', $filename)
        || !in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
        error_log('Invalid owner verification evidence metadata for request ID ' . $requestId);
        http_response_code(404);
        exit('Dokumen tidak ditemukan.');
    }
    $directory = realpath(owner_verification_storage_directory());
    $path = $directory
        ? realpath($directory . DIRECTORY_SEPARATOR . $filename)
        : false;
    $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if (!$directory || !$path || !str_starts_with($path, $directory . DIRECTORY_SEPARATOR)
        || ($documentRoot && ($directory === $documentRoot
            || str_starts_with($directory, $documentRoot . DIRECTORY_SEPARATOR)))
        || !is_file($path) || !is_readable($path)) {
        error_log('Owner verification evidence file is missing for request ID ' . $requestId);
        http_response_code(404);
        exit('Dokumen tidak ditemukan.');
    }
    $downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'evidence';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: inline; filename="' . $downloadName . '"');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: sandbox; default-src 'none'");
    readfile($path);
    exit;
}
