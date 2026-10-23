<?php
declare(strict_types=1);

$payments = rows(
    $pdo,
    "SELECT p.id,p.amount,p.payment_method,p.status,p.paid_at,p.payment_proof_path,
            i.invoice_number,i.period_start,i.period_end,t.id AS tenant_id,t.full_name,
            rm.room_number,b.name AS building_name
     FROM payments p
     JOIN invoices i ON i.id=p.invoice_id
     JOIN rentals r ON r.id=i.rental_id
     JOIN tenants t ON t.id=r.tenant_id
     JOIN rooms rm ON rm.id=r.room_id
     JOIN kos_buildings b ON b.id=rm.building_id
     WHERE b.owner_user_id=:owner_id
     ORDER BY p.paid_at DESC,p.id DESC LIMIT 500",
    ['owner_id' => $user['id']]
);
?>
<div class="panel">
    <div class="panel-heading">
        <div><h2>Histori pembayaran</h2><p>Daftar pembayaran pada properti yang Anda kelola.</p></div>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Tanggal</th><th>Tagihan / periode</th><th>Penghuni / kamar</th><th>Metode</th><th>Nominal</th><th>Status</th><th>Struk</th></tr></thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?= e($payment['paid_at'] ? date_id($payment['paid_at']) : '—') ?></td>
                        <td><strong><?= e($payment['invoice_number']) ?></strong><small class="subline"><?= e(date_id($payment['period_start'])) ?> – <?= e(date_id($payment['period_end'])) ?></small>
                            <?php if ($payment['payment_proof_path']): ?><small class="subline"><a class="text-link" href="index.php?page=payment_proof&amp;id=<?= (int) $payment['id'] ?>">Lihat bukti</a></small><?php endif; ?>
                        </td>
                        <td><a class="text-link" href="index.php?page=tenant_profile&amp;id=<?= (int) $payment['tenant_id'] ?>"><?= e($payment['full_name']) ?></a><small class="subline"><?= e($payment['building_name']) ?> · Kamar <?= e($payment['room_number']) ?></small></td>
                        <td><?= e(['cash' => 'Tunai', 'bank_transfer' => 'Transfer bank', 'e_wallet' => 'E-wallet', 'other' => 'Lainnya'][$payment['payment_method']] ?? $payment['payment_method']) ?></td>
                        <td><?= e(money($payment['amount'])) ?></td>
                        <td><span class="badge <?= e($payment['status']) ?>"><?= e(status_label($payment['status'])) ?></span></td>
                        <td><?php if ($payment['status'] === 'confirmed'): ?><a class="button secondary compact-button" href="index.php?page=payment_receipt&amp;id=<?= (int) $payment['id'] ?>">Lihat struk</a><?php else: ?>—<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$payments): ?><tr><td colspan="7" class="no-data">Belum ada histori pembayaran.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
