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
?>
<section class="page-heading"><div><span class="eyebrow">DASHBOARD AKUN</span><h2>Dashboard</h2><p>Ringkasan pembayaran, tagihan tersisa, dan cicilan pada akun Anda.</p></div></section>
<div class="stats-grid">
    <article class="stat-card"><a class="stat-icon green stat-icon-link" href="index.php?page=my_payments" aria-label="Buka riwayat pembayaran">Rp</a><div><span>Total dibayar</span><strong><?= e(money($accountSummary['paid_total'])) ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon orange stat-icon-link" href="index.php?page=my_payments" aria-label="Buka riwayat pembayaran dan tagihan">◷</a><div><span>Total sisa tagihan</span><strong><?= e(money($accountSummary['outstanding'])) ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon red stat-icon-link" href="index.php?page=my_payments" aria-label="Buka tagihan yang lewat jatuh tempo">!</a><div><span>Tunggakan lewat jatuh tempo</span><strong><?= e(money($accountSummary['overdue'])) ?></strong></div></article>
    <article class="stat-card"><a class="stat-icon blue stat-icon-link" href="index.php?page=my_payments" aria-label="Buka pembayaran">◌</a><div><span>Pembayaran menunggu verifikasi</span><strong><?= e(money($accountSummary['pending_total'])) ?></strong></div></article>
</div>
<section class="panel dashboard-payment-link"><div class="panel-heading"><div><h2>Tagihan dan pembayaran</h2><p>Tagihan dan rincian transaksi akun Anda tersedia pada satu menu.</p></div><a class="button primary" href="index.php?page=my_payments">Buka tagihan dan pembayaran →</a></div></section>
