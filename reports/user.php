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
$invoices = rows(
    $pdo,
    "SELECT i.invoice_number,i.period_start,i.period_end,i.due_date,i.amount_due,i.status,
            b.id AS building_id,b.name AS building_name,rm.room_number,
            COALESCE(p.confirmed_total,0) AS paid,COALESCE(p.pending_total,0) AS pending,
            COALESCE(p.payment_count,0) AS payment_count,p.latest_paid_at,
            GREATEST(i.amount_due-COALESCE(p.confirmed_total,0),0) AS remaining
     FROM invoices i JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id
     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
     LEFT JOIN (SELECT invoice_id,
                       SUM(CASE WHEN status='confirmed' THEN amount ELSE 0 END) AS confirmed_total,
                       SUM(CASE WHEN status='pending' THEN amount ELSE 0 END) AS pending_total,
                       COUNT(*) AS payment_count,MAX(paid_at) AS latest_paid_at
                FROM payments GROUP BY invoice_id) p ON p.invoice_id=i.id
     WHERE t.user_id=:id AND (i.status<>'void' OR COALESCE(p.payment_count,0)>0)
     ORDER BY i.due_date DESC,i.id DESC LIMIT 100",
    ['id' => $userId]
);
?>
<section class="page-heading"><div><span class="eyebrow">DASHBOARD AKUN</span><h2>Dashboard</h2><p>Ringkasan pembayaran, tagihan tersisa, dan cicilan pada akun Anda.</p></div></section>
<div class="stats-grid">
    <article class="stat-card"><a class="stat-icon green stat-icon-link" href="index.php?page=my_payments" aria-label="Buka riwayat pembayaran">Rp</a><div><span>Total dibayar</span><strong><?= e(money($accountSummary['paid_total'])) ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon orange stat-icon-link" href="index.php?page=my_payments" aria-label="Buka riwayat pembayaran dan tagihan">◷</a><div><span>Total sisa tagihan</span><strong><?= e(money($accountSummary['outstanding'])) ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon red stat-icon-link" href="index.php?page=my_payments" aria-label="Buka tagihan yang lewat jatuh tempo">!</a><div><span>Tunggakan lewat jatuh tempo</span><strong><?= e(money($accountSummary['overdue'])) ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon blue stat-icon-link" href="index.php?page=my_payments" aria-label="Buka pembayaran">◌</a><div><span>Pembayaran menunggu verifikasi</span><strong><?= e(money($accountSummary['pending_total'])) ?></strong></div></article>
</div>
<section class="panel"><div class="panel-heading"><div><h2>Tagihan dan pembayaran</h2><p>Satu baris per tagihan untuk seluruh kos; pembayaran terkonfirmasi dan menunggu diringkas terpisah.</p></div><a class="text-link" href="index.php?page=my_payments">Buka rincian transaksi →</a></div>
<div class="table-scroll"><table class="user-dashboard-table" data-building-filter><thead><tr><th>Tagihan / periode</th><th>Kos / kamar</th><th>Jatuh tempo</th><th>Total tagihan</th><th>Terkonfirmasi</th><th>Menunggu</th><th>Sisa</th><th>Status</th></tr></thead><tbody>
<?php foreach ($invoices as $invoice): ?><tr data-building-filter="<?= (int) $invoice['building_id'] ?>" data-building-label="<?= e($invoice['building_name']) ?>">
    <td><strong><?= e($invoice['invoice_number']) ?></strong><small class="subline"><?= e(date_id($invoice['period_start'])) ?> – <?= e(date_id($invoice['period_end'])) ?></small><?php if ($invoice['latest_paid_at']): ?><small class="subline">Pembayaran terakhir <?= e(date_id($invoice['latest_paid_at'])) ?></small><?php endif; ?></td>
    <td><?= e($invoice['building_name']) ?><small class="subline">Kamar <?= e($invoice['room_number']) ?></small></td>
    <td><?= e(date_id($invoice['due_date'])) ?></td>
    <td><?= e(money($invoice['amount_due'])) ?></td>
    <td><?= e(money($invoice['paid'])) ?></td>
    <td><?= e(money($invoice['pending'])) ?></td>
    <td><strong><?= e(money($invoice['remaining'])) ?></strong></td>
    <td><span class="badge <?= e($invoice['status']) ?>"><?= e(status_label($invoice['status'])) ?></span><small class="subline"><?= (int) $invoice['payment_count'] ?> pembayaran</small></td>
</tr><?php endforeach; ?>
<?php if (!$invoices): ?><tr><td colspan="8" class="no-data">Belum ada tagihan yang tercatat.</td></tr><?php endif; ?>
</tbody></table></div></section>
