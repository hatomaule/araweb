<?php
declare(strict_types=1);

$paymentId = max(0, (int) ($_GET['id'] ?? 0));
$scope = '';
$parameters = ['id' => $paymentId];
if ($user['role'] === 'owner') {
    $scope = 'AND b.owner_user_id=:owner_id';
    $parameters['owner_id'] = (int) $user['id'];
} elseif ($user['role'] === 'seeker') {
    $scope = 'AND t.user_id=:user_id';
    $parameters['user_id'] = (int) $user['id'];
}
$receipt = one(
    $pdo,
    "SELECT p.id,p.amount,p.payment_method,p.status,p.paid_at,p.transaction_reference,
            i.invoice_number,i.period_start,i.period_end,i.amount_due,
            t.user_id AS tenant_user_id,t.full_name,t.email,t.phone,
            rm.room_number,b.name AS building_name,o.name AS owner_name
     FROM payments p JOIN invoices i ON i.id=p.invoice_id
     JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id
     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
     JOIN users o ON o.id=b.owner_user_id
     WHERE p.id=:id {$scope}",
    $parameters
);
if (!$receipt) {
    http_response_code(404);
    echo '<div class="alert error">Struk pembayaran tidak ditemukan atau tidak dapat diakses.</div>';
    return;
}
if ($receipt['status'] !== 'confirmed') {
    echo '<div class="alert info">Struk akan tersedia setelah pembayaran dikonfirmasi.</div>';
    return;
}
$paymentMethods = ['cash' => 'Tunai', 'bank_transfer' => 'Transfer bank', 'e_wallet' => 'E-wallet', 'other' => 'Lainnya'];
$receiptRows = [
    'Nomor struk' => 'KS-' . str_pad((string) $receipt['id'], 8, '0', STR_PAD_LEFT),
    'Penghuni' => $receipt['full_name'],
    'Kos / kamar' => $receipt['building_name'] . ' · ' . $receipt['room_number'],
    'Tagihan' => $receipt['invoice_number'],
    'Periode' => date_id($receipt['period_start']) . ' - ' . date_id($receipt['period_end']),
    'Tanggal bayar' => $receipt['paid_at'] ? date_id($receipt['paid_at']) : '—',
    'Nominal' => money($receipt['amount']),
    'Metode' => $paymentMethods[$receipt['payment_method']] ?? $receipt['payment_method'],
];
if ($receipt['transaction_reference']) {
    $receiptRows['Referensi'] = $receipt['transaction_reference'];
}
$receiptRows['Status'] = status_label($receipt['status']);
$receiptMessage = "*BUKTI PEMBAYARAN SEWA KOSKOSAN*\n"
    . "```\n"
    . implode("\n", array_map(
        static fn (string $label, $value): string => str_pad($label, 16) . ' : ' . (string) $value,
        array_keys($receiptRows),
        array_values($receiptRows)
    ))
    . "\n```";
$whatsappReceiptUrl = $user['role'] !== 'seeker' && $receipt['phone']
    ? whatsapp_url((string) $receipt['phone'], $receiptMessage) : null;
$emailReceiptUrl = $user['role'] !== 'seeker' && $receipt['email']
    ? 'mailto:' . rawurlencode((string) $receipt['email'])
        . '?subject=' . rawurlencode('Struk pembayaran ' . $receipt['invoice_number'])
        . '&body=' . rawurlencode(strip_tags(str_replace(['```', '*'], '', $receiptMessage)) . "\n\nLampirkan PDF struk yang telah disimpan.")
    : null;
?>
<div class="receipt-page">
    <div class="receipt-actions">
        <button class="button primary" type="button" data-print-page>Cetak / Simpan PDF</button>
        <button class="button secondary" type="button" data-download-receipt-image>Unduh gambar struk</button>
        <?php if ($emailReceiptUrl): ?><a class="button secondary" href="<?= e($emailReceiptUrl) ?>">Kirim via Email</a><?php endif; ?>
        <?php if ($whatsappReceiptUrl): ?><a class="button secondary" href="<?= e($whatsappReceiptUrl) ?>" target="_blank" rel="noopener noreferrer">Kirim teks struk via WhatsApp</a><?php endif; ?>
        <a class="button secondary" href="index.php?page=<?= $user['role'] === 'seeker' ? 'my_payments' : 'payments' ?>">Kembali ke histori</a>
    </div>
    <article class="payment-receipt">
        <header class="receipt-heading"><span class="brand-mark">K</span><div><strong>KosKosan</strong><small>Struk pembayaran sewa</small></div><span class="receipt-status"><?= e(status_label($receipt['status'])) ?></span></header>
        <div class="receipt-number"><span>Nomor struk</span><strong>KS-<?= str_pad((string) $receipt['id'], 8, '0', STR_PAD_LEFT) ?></strong></div>
        <dl class="receipt-details">
            <div><dt>Penghuni</dt><dd><?= e($receipt['full_name']) ?></dd></div>
            <div><dt>Pemilik kos</dt><dd><?= e($receipt['owner_name']) ?></dd></div>
            <div><dt>Gedung / kamar</dt><dd><?= e($receipt['building_name']) ?> · <?= e($receipt['room_number']) ?></dd></div>
            <div><dt>Nomor tagihan</dt><dd><?= e($receipt['invoice_number']) ?></dd></div>
            <div><dt>Periode sewa</dt><dd><?= e(date_id($receipt['period_start'])) ?> – <?= e(date_id($receipt['period_end'])) ?></dd></div>
            <div><dt>Tanggal pembayaran</dt><dd><?= e($receipt['paid_at'] ? date_id($receipt['paid_at']) : '—') ?></dd></div>
            <div><dt>Metode pembayaran</dt><dd><?= e($paymentMethods[$receipt['payment_method']] ?? $receipt['payment_method']) ?></dd></div>
            <?php if ($receipt['transaction_reference']): ?><div><dt>Referensi transaksi</dt><dd><?= e($receipt['transaction_reference']) ?></dd></div><?php endif; ?>
        </dl>
        <footer class="receipt-total"><span>Jumlah dibayarkan</span><strong><?= e(money($receipt['amount'])) ?></strong></footer>
        <p class="receipt-note">Struk ini diterbitkan oleh sistem KosKosan berdasarkan transaksi yang tercatat.</p>
    </article>
</div>
