<?php
$userId = (int) $user['id'];
$accountSummary = one(
    $pdo,
    "SELECT
       (SELECT COALESCE(SUM(GREATEST(i.amount_due-COALESCE(p.paid,0),0)),0)
        FROM invoices i JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id
        LEFT JOIN (SELECT invoice_id,SUM(amount) AS paid FROM payments WHERE status='confirmed' GROUP BY invoice_id) p
          ON p.invoice_id=i.id
        WHERE t.user_id=:open_user AND i.status='open') AS outstanding,
       (SELECT COALESCE(SUM(GREATEST(i.amount_due-COALESCE(p.paid,0),0)),0)
        FROM invoices i JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id
        LEFT JOIN (SELECT invoice_id,SUM(amount) AS paid FROM payments WHERE status='confirmed' GROUP BY invoice_id) p
          ON p.invoice_id=i.id
        WHERE t.user_id=:overdue_user AND i.status='open' AND i.due_date<CURDATE()) AS overdue,
       (SELECT COALESCE(SUM(p.amount),0)
        FROM payments p JOIN invoices i ON i.id=p.invoice_id
        JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id
        WHERE t.user_id=:paid_user AND p.status='confirmed') AS paid_total,
       (SELECT COALESCE(SUM(p.amount),0)
        FROM payments p JOIN invoices i ON i.id=p.invoice_id
        JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id
        WHERE t.user_id=:pending_user AND p.status='pending') AS pending_total",
    [
        'open_user' => $userId, 'overdue_user' => $userId,
        'paid_user' => $userId, 'pending_user' => $userId,
    ]
);
$payments = rows(
    $pdo,
    "SELECT p.id,p.amount,p.payment_method,p.status,p.transaction_reference,p.paid_at,
            i.invoice_number,i.amount_due,i.due_date,b.name AS building_name,rm.room_number,
            COALESCE(paid.confirmed_total,0) AS confirmed_total,
            GREATEST(i.amount_due-COALESCE(paid.confirmed_total,0),0) AS remaining_amount
     FROM payments p JOIN invoices i ON i.id=p.invoice_id
     JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id
     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
     LEFT JOIN (SELECT invoice_id,SUM(amount) AS confirmed_total FROM payments
                WHERE status='confirmed' GROUP BY invoice_id) paid ON paid.invoice_id=i.id
     WHERE t.user_id=:id ORDER BY p.paid_at DESC,p.id DESC LIMIT 100",
    ['id' => $userId]
);
$invoices = rows(
    $pdo,
    "SELECT i.invoice_number,i.period_start,i.period_end,i.due_date,i.amount_due,i.status,
            b.name AS building_name,rm.room_number,COALESCE(p.paid,0) AS paid,
            GREATEST(i.amount_due-COALESCE(p.paid,0),0) AS remaining
     FROM invoices i JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id
     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
     LEFT JOIN (SELECT invoice_id,SUM(amount) AS paid FROM payments
                WHERE status='confirmed' GROUP BY invoice_id) p ON p.invoice_id=i.id
     WHERE t.user_id=:id AND i.status<>'void'
     ORDER BY i.due_date DESC,i.id DESC LIMIT 100",
    ['id' => $userId]
);
?>
<section class="page-heading"><div><span class="eyebrow">KEUANGAN PRIBADI</span><h2>Laporan pembayaran</h2><p>Ringkasan pembayaran, tagihan tersisa, dan cicilan pada akun Anda.</p></div></section>
<div class="stats-grid">
    <article class="stat-card"><a class="stat-icon green stat-icon-link" href="index.php?page=my_payments" aria-label="Buka riwayat pembayaran">Rp</a><div><span>Total dibayar</span><strong><?= e(money($accountSummary['paid_total'])) ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon orange stat-icon-link" href="index.php?page=my_payments" aria-label="Buka riwayat pembayaran dan tagihan">◷</a><div><span>Total sisa tagihan</span><strong><?= e(money($accountSummary['outstanding'])) ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon red stat-icon-link" href="index.php?page=my_payments" aria-label="Buka tagihan yang lewat jatuh tempo">!</a><div><span>Tunggakan lewat jatuh tempo</span><strong><?= e(money($accountSummary['overdue'])) ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon blue stat-icon-link" href="index.php?page=my_payments" aria-label="Buka pembayaran">◌</a><div><span>Pembayaran menunggu verifikasi</span><strong><?= e(money($accountSummary['pending_total'])) ?></strong></div></article>
</div>
<section class="panel"><div class="panel-heading"><div><h2>Tagihan dan sisa cicilan</h2><p>Sisa tagihan dihitung dari total tagihan dikurangi pembayaran yang sudah dikonfirmasi.</p></div></div><div class="table-scroll"><table><thead><tr><th>Tagihan</th><th>Kos / kamar</th><th>Periode</th><th>Jatuh tempo</th><th>Tagihan</th><th>Dibayar</th><th>Sisa</th></tr></thead><tbody>
<?php foreach ($invoices as $invoice): ?><tr><td><?= e($invoice['invoice_number']) ?><small class="subline"><?= e(status_label($invoice['status'])) ?></small></td><td><?= e($invoice['building_name'] . ' / ' . $invoice['room_number']) ?></td><td><?= e(date_id($invoice['period_start'])) ?> – <?= e(date_id($invoice['period_end'])) ?></td><td><?= e(date_id($invoice['due_date'])) ?></td><td><?= e(money($invoice['amount_due'])) ?></td><td><?= e(money($invoice['paid'])) ?></td><td><strong><?= e(money($invoice['remaining'])) ?></strong></td></tr><?php endforeach; ?>
<?php if (!$invoices): ?><tr><td colspan="7" class="no-data">Belum ada tagihan yang tercatat.</td></tr><?php endif; ?>
</tbody></table></div></section>
<section class="panel"><div class="panel-heading"><div><h2>Riwayat pembayaran</h2><p>Maksimal 100 catatan pembayaran pada akun ini.</p></div></div><div class="table-scroll"><table><thead><tr><th>Tanggal</th><th>Kos / kamar</th><th>Tagihan</th><th>Jumlah bayar</th><th>Sisa setelah konfirmasi</th><th>Metode</th><th>Status</th></tr></thead><tbody>
<?php foreach ($payments as $payment): ?><tr><td><?= e(date_id($payment['paid_at'])) ?></td><td><?= e($payment['building_name'] . ' / ' . $payment['room_number']) ?></td><td><?= e($payment['invoice_number']) ?></td><td><?= e(money($payment['amount'])) ?></td><td><?= e(money($payment['remaining_amount'])) ?></td><td><?= e(['cash'=>'Tunai','bank_transfer'=>'Transfer bank'][$payment['payment_method']] ?? $payment['payment_method']) ?></td><td><?= e(status_label($payment['status'])) ?></td></tr><?php endforeach; ?>
<?php if (!$payments): ?><tr><td colspan="7" class="no-data">Belum ada pembayaran.</td></tr><?php endif; ?>
</tbody></table></div></section>
