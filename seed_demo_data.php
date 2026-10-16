<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config.php';

$demoPassword = getenv('ARA_DEMO_PASSWORD') ?: '';
if (strlen($demoPassword) < 12) {
    fwrite(STDERR, "Set ARA_DEMO_PASSWORD (minimum 12 characters) before running this local demo seeder.\n");
    exit(1);
}

$pdo = db();
$platformAdminId = (int) $pdo->query(
    "SELECT id FROM users WHERE role='admin' AND status='active' ORDER BY id LIMIT 1"
)->fetchColumn();
if ($platformAdminId < 1) {
    fwrite(STDERR, "An active platform admin is required to approve demo listings.\n");
    exit(1);
}
$operators = [
    ['name' => 'Operator Kenanga Demo', 'email' => 'operator.kenanga@araweb.test', 'gender' => 'male', 'kos' => 'Pondok Kenanga Gambesi (Demo)', 'lat' => 0.75612, 'lon' => 127.33572],
    ['name' => 'Operator Cempaka Demo', 'email' => 'operator.cempaka@araweb.test', 'gender' => 'male', 'kos' => 'Kos Taman Cempaka (Demo)', 'lat' => 0.75521, 'lon' => 127.33655],
    ['name' => 'Operator Lestari Demo', 'email' => 'operator.lestari@araweb.test', 'gender' => 'male', 'kos' => 'Paviliun Lestari Gambesi (Demo)', 'lat' => 0.75603, 'lon' => 127.33708],
];
$roomTypes = ['Tipe Standart', 'Tipe Premium', 'Tipe Eksklusif'];
$facilities = [
    'Kipas angin, Wi-Fi, lemari pakaian',
    'AC, Wi-Fi, kamar mandi dalam',
    'AC, Wi-Fi, kamar mandi dalam, meja belajar',
    'Kipas angin, meja belajar, lemari pakaian',
    'AC, Wi-Fi, kasur, kamar mandi dalam',
];
$tenantsByKos = [
    ['Ayu Lestari', 'Bima Pratama', 'Citra Maharani'],
    ['Dimas Saputra', 'Nadia Putri', 'Raka Firmansyah'],
    ['Salsa Nabila', 'Fajar Ramadhan', 'Intan Permata'],
];
$tenantGenders = [
    'Ayu Lestari' => 'female', 'Bima Pratama' => 'male', 'Citra Maharani' => 'female',
    'Dimas Saputra' => 'male', 'Nadia Putri' => 'female', 'Raka Firmansyah' => 'male',
    'Salsa Nabila' => 'female', 'Fajar Ramadhan' => 'male', 'Intan Permata' => 'female',
];
$roomRates = [
    [850000, 1100000, 1450000],
    [900000, 1200000, 1550000],
    [800000, 1150000, 1500000],
];
$roomTypeIndexes = [
    [0, 1, 2, 0, 1],
    [1, 0, 2, 1, 0],
    [2, 1, 0, 2, 1],
];
$demoEmails = [];
foreach ($operators as $kosIndex => $operator) {
    $demoEmails[] = $operator['email'];
    foreach ($tenantsByKos[$kosIndex] as $index => $_) {
        $demoEmails[] = 'penghuni.' . ['kenanga', 'cempaka', 'lestari'][$kosIndex]
            . ($index + 1) . '@araweb.test';
    }
}
$placeholders = implode(',', array_fill(0, count($demoEmails), '?'));
$existing = $pdo->prepare("SELECT email FROM users WHERE email IN ({$placeholders}) LIMIT 1");
$existing->execute($demoEmails);
if ($existing->fetchColumn()) {
    fwrite(STDERR, "Demo accounts already exist. Seeder stopped without changing any data.\n");
    exit(1);
}

$demoKosNames = array_column($operators, 'kos');
$kosPlaceholders = implode(',', array_fill(0, count($demoKosNames), '?'));
$existingKos = $pdo->prepare("SELECT id FROM kos_buildings WHERE name IN ({$kosPlaceholders}) LIMIT 1");
$existingKos->execute($demoKosNames);
if ($existingKos->fetchColumn()) {
    fwrite(STDERR, "Demo kos data already exists. Seeder stopped without changing any data.\n");
    exit(1);
}

$passwordHash = password_hash($demoPassword, PASSWORD_DEFAULT);
$periodStart = new DateTimeImmutable('first day of this month -6 months');
$pdo->beginTransaction();
try {
    $createUser = $pdo->prepare(
        'INSERT INTO users (name,email,phone,gender,password_hash,role,status)
         VALUES (:name,:email,:phone,:gender,:password_hash,:role,\'active\')'
    );
    $createBuilding = $pdo->prepare(
        "INSERT INTO kos_buildings
         (owner_user_id,name,address,description,gender_policy,general_facilities,house_rules,
          latitude,longitude,status,approval_status,reviewed_by,reviewed_at)
         VALUES (:owner_id,:name,:address,:description,'mixed',:facilities,:rules,
          :latitude,:longitude,'active','approved',:reviewed_by,NOW())"
    );
    $createRoom = $pdo->prepare(
        'INSERT INTO rooms (building_id,room_number,floor,room_type,monthly_rate,facilities,status)
         VALUES (:building_id,:room_number,:floor,:room_type,:monthly_rate,:facilities,\'available\')'
    );
    $markRoomOccupied = $pdo->prepare("UPDATE rooms SET status='occupied' WHERE id=:room_id");
    $createTenant = $pdo->prepare(
        'INSERT INTO tenants (user_id,full_name,phone,email,address,status)
         VALUES (:user_id,:name,:phone,:email,:address,\'active\')'
    );
    $createRental = $pdo->prepare(
        "INSERT INTO rentals
         (tenant_id,room_id,start_date,end_date,monthly_rate,deposit_amount,billing_day,
          status,notes,created_by,booking_status)
         VALUES (:tenant_id,:room_id,:start_date,NULL,:monthly_rate,0,1,
          'active','Data sewa contoh/demo untuk pengujian.',:created_by,'not_applicable')"
    );
    $createInvoice = $pdo->prepare(
        "INSERT INTO invoices
         (rental_id,invoice_number,period_start,period_end,due_date,amount_due,status,notes)
         VALUES (:rental_id,:invoice_number,:period_start,:period_end,:due_date,:amount,'paid',
          'Tagihan contoh/demo; sudah dibayar.')"
    );
    $createPayment = $pdo->prepare(
        "INSERT INTO payments
         (invoice_id,received_by,amount,payment_method,status,transaction_reference,paid_at,notes)
         VALUES (:invoice_id,:received_by,:amount,'bank_transfer','confirmed',:reference,:paid_at,
          'Transaksi contoh/demo untuk pengujian laporan.')"
    );
    $createBank = $pdo->prepare(
        'INSERT INTO bank_transfer_settings
         (building_id,bank_name,account_name,account_number,instructions,updated_by)
         VALUES (:building_id,\'BANK DEMO\',:account_name,:account_number,
          \'Data rekening contoh. Jangan digunakan untuk transfer nyata.\',:updated_by)'
    );

    foreach ($operators as $kosIndex => $operator) {
        $createUser->execute([
            'name' => $operator['name'], 'email' => $operator['email'], 'phone' => null,
            'gender' => $operator['gender'],
            'password_hash' => $passwordHash, 'role' => 'operator',
        ]);
        $operatorId = (int) $pdo->lastInsertId();
        $createBuilding->execute([
            'owner_id' => $operatorId,
            'name' => $operator['kos'],
            'address' => 'Gambesi, Ternate Selatan, Kota Ternate, Maluku Utara',
            'description' => 'Listing contoh KosKosan di sekitar Tiara Kost. Foto properti dapat dilengkapi operator.',
            'facilities' => 'Area parkir, Wi-Fi bersama, dapur bersama, ruang santai',
            'rules' => 'Jaga kebersihan dan ketenangan bersama.',
            'latitude' => $operator['lat'], 'longitude' => $operator['lon'],
            'reviewed_by' => $platformAdminId,
        ]);
        $buildingId = (int) $pdo->lastInsertId();
        $createBank->execute([
            'building_id' => $buildingId,
            'account_name' => 'DEMO ' . strtoupper(['KENANGA', 'CEMPAKA', 'LESTARI'][$kosIndex]),
            'account_number' => '000000000' . ($kosIndex + 1),
            'updated_by' => $operatorId,
        ]);

        $roomIds = [];
        for ($roomIndex = 0; $roomIndex < 5; $roomIndex++) {
            $typeIndex = $roomTypeIndexes[$kosIndex][$roomIndex];
            $createRoom->execute([
                'building_id' => $buildingId,
                'room_number' => chr(65 + $roomIndex),
                'floor' => $roomIndex < 3 ? '1' : '2',
                'room_type' => $roomTypes[$typeIndex],
                'monthly_rate' => $roomRates[$kosIndex][$typeIndex],
                'facilities' => $facilities[($kosIndex + $roomIndex) % count($facilities)],
            ]);
            $roomIds[] = (int) $pdo->lastInsertId();
        }

        foreach ($tenantsByKos[$kosIndex] as $tenantIndex => $tenantName) {
            $tenantEmail = 'penghuni.' . ['kenanga', 'cempaka', 'lestari'][$kosIndex]
                . ($tenantIndex + 1) . '@araweb.test';
            $createUser->execute([
                'name' => $tenantName, 'email' => $tenantEmail, 'phone' => null,
                'gender' => $tenantGenders[$tenantName],
                'password_hash' => $passwordHash, 'role' => 'seeker',
            ]);
            $tenantUserId = (int) $pdo->lastInsertId();
            $createTenant->execute([
                'user_id' => $tenantUserId,
                'name' => $tenantName,
                'phone' => '0000000' . ($kosIndex + 1) . ($tenantIndex + 1),
                'email' => $tenantEmail,
                'address' => 'Gambesi, Ternate Selatan, Kota Ternate',
            ]);
            $tenantId = (int) $pdo->lastInsertId();
            $roomId = $roomIds[$tenantIndex];
            $rate = $roomRates[$kosIndex][$roomTypeIndexes[$kosIndex][$tenantIndex]];
            $createRental->execute([
                'tenant_id' => $tenantId, 'room_id' => $roomId,
                'start_date' => $periodStart->format('Y-m-d'),
                'monthly_rate' => $rate, 'created_by' => $operatorId,
            ]);
            $rentalId = (int) $pdo->lastInsertId();
            $markRoomOccupied->execute(['room_id' => $roomId]);

            for ($monthOffset = 0; $monthOffset < 6; $monthOffset++) {
                $period = $periodStart->modify('+' . $monthOffset . ' months');
                $periodEnd = $period->modify('last day of this month');
                $dueDate = $period->modify('first day of next month')->modify('+4 days');
                $paidAt = $period->modify('first day of next month')->modify('+2 days');
                $invoiceNumber = sprintf(
                    'DEMO-%02d%02d-%s%02d',
                    (int) $period->format('y'),
                    (int) $period->format('m'),
                    ['K', 'C', 'L'][$kosIndex],
                    $tenantIndex + 1
                );
                $createInvoice->execute([
                    'rental_id' => $rentalId,
                    'invoice_number' => $invoiceNumber,
                    'period_start' => $period->format('Y-m-d'),
                    'period_end' => $periodEnd->format('Y-m-d'),
                    'due_date' => $dueDate->format('Y-m-d'),
                    'amount' => $rate,
                ]);
                $invoiceId = (int) $pdo->lastInsertId();
                $createPayment->execute([
                    'invoice_id' => $invoiceId,
                    'received_by' => $operatorId,
                    'amount' => $rate,
                    'reference' => $invoiceNumber,
                    'paid_at' => $paidAt->format('Y-m-d 10:00:00'),
                ]);
            }
        }
    }
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

echo "Created 3 demo operators, 3 approved kos, 15 rooms, 9 seeker accounts, 9 active rentals, and 54 paid invoices/payments.\n";
echo "Demo password for all demo users: {$demoPassword}\n";
echo "Use the operator.kenanga/cempaka/lestari@araweb.test accounts to manage each kos.\n";
