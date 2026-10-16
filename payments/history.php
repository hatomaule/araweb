<?php
declare(strict_types=1);

$payments = rows(
    $pdo,
    "SELECT p.id,p.amount,p.payment_method,p.status,p.paid_at,p.payment_proof_path,
            i.invoice_number,t.id AS tenant_id,t.full_name,rm.room_number,b.name AS building_name
     FROM payments p
     JOIN invoices i ON i.id=p.invoice_id
     JOIN rentals r ON r.id=i.rental_id
     JOIN tenants t ON t.id=r.tenant_id
     JOIN rooms rm ON rm.id=r.room_id
     JOIN kos_buildings b ON b.id=rm.building_id
     ORDER BY p.paid_at DESC,p.id DESC LIMIT 500"
);
$paymentMethods = ['cash' => 'Tunai', 'bank_transfer' => 'Transfer bank', 'e_wallet' => 'E-wallet', 'other' => 'Lainnya'];
?>
<div class="panel">
    <div class="panel-heading">
        <div><h2>Histori pembayaran</h2><p>Log transaksi pembayaran pada seluruh properti.</p></div>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Tanggal</th><th>Tagihan</th><th>Pembayar / kos / kamar</th><th>Metode</th><th>Nominal</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?= e($payment['paid_at'] ? date_id($payment['paid_at']) : '—') ?></td>
                        <td><strong><?= e($payment['invoice_number']) ?></strong>
                            <?php if ($payment['payment_proof_path']): ?><small class="subline"><a class="text-link" href="index.php?page=payment_proof&amp;id=<?= (int) $payment['id'] ?>">Lihat bukti</a></small><?php endif; ?>
                        </td>
                        <td><a class="text-link" href="index.php?page=tenant_payments&amp;id=<?= (int) $payment['tenant_id'] ?>"><?= e($payment['full_name']) ?></a><small class="subline"><?= e($payment['building_name']) ?> · Kamar <?= e($payment['room_number']) ?></small></td>
                        <td><?= e($paymentMethods[$payment['payment_method']] ?? $payment['payment_method']) ?></td>
                        <td><?= e(money($payment['amount'])) ?></td>
                        <td><span class="badge <?= e($payment['status']) ?>"><?= e(status_label($payment['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$payments): ?><tr><td colspan="6" class="no-data">Belum ada histori pembayaran.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
