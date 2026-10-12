<?php
if ($page === 'payment_proof') {
    if (!$user) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
    $proofId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $proofQuery = $pdo->prepare(
        "SELECT p.payment_proof_path
         FROM payments p JOIN invoices i ON i.id=p.invoice_id
         JOIN rentals r ON r.id=i.rental_id
         JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
         LEFT JOIN tenants t ON t.id=r.tenant_id
         WHERE p.id=:payment_id
           AND (:is_admin=1 OR b.owner_user_id=:user_id OR t.user_id=:user_id)
           AND p.payment_proof_path IS NOT NULL"
    );
    $proofQuery->execute([
        'payment_id' => $proofId, 'is_admin' => $user['role'] === 'admin' ? 1 : 0,
        'user_id' => $user['id'],
    ]);
    $proof = $proofQuery->fetch();
    $proofRoot = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'payments');
    $proofPath = $proof && $proofRoot
        ? realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $proof['payment_proof_path']))
        : false;
    if (!$proofPath || !$proofRoot || !str_starts_with($proofPath, $proofRoot . DIRECTORY_SEPARATOR) || !is_file($proofPath)) {
        http_response_code(404);
        exit('Bukti pembayaran tidak ditemukan.');
    }
    $proofMime = (new finfo(FILEINFO_MIME_TYPE))->file($proofPath);
    if (!in_array($proofMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        http_response_code(415);
        exit('Format bukti pembayaran tidak didukung.');
    }
    header('Content-Type: ' . $proofMime);
    header('Content-Length: ' . (string) filesize($proofPath));
    header('Content-Disposition: inline; filename="bukti-pembayaran.' . ($proofMime === 'image/jpeg' ? 'jpg' : substr($proofMime, 6)) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    readfile($proofPath);
    exit;
}

