<?php
declare(strict_types=1);

$pendingProofs = rows(
    $pdo,
    "SELECT p.id,p.amount,p.paid_at,p.transaction_reference,p.payment_proof_path,
            i.invoice_number,i.amount_due,t.full_name,rm.room_number,b.name AS building_name
     FROM payments p
     JOIN invoices i ON i.id=p.invoice_id
     JOIN rentals r ON r.id=i.rental_id
     JOIN tenants t ON t.id=r.tenant_id
     JOIN rooms rm ON rm.id=r.room_id
     JOIN kos_buildings b ON b.id=rm.building_id
     WHERE p.status='pending' AND p.payment_proof_path IS NOT NULL
       AND b.owner_user_id=:owner_id
     ORDER BY p.paid_at,p.id",
    ['owner_id' => $user['id']]
);
?>
<div class="panel">
    <div class="panel-heading">
        <div><h2>Konfirmasi pembayaran</h2><p>Tinjau bukti yang dikirim pengguna. Pembayaran baru dihitung setelah disetujui.</p></div>
    </div>
    <?php if ($pendingProofs): ?>
        <div class="payment-review-list">
            <?php foreach ($pendingProofs as $proof): ?>
                <article class="payment-review-card">
                    <div>
                        <span class="section-eyebrow"><?= e($proof['invoice_number']) ?> · <?= e($proof['building_name']) ?> · KAMAR <?= e($proof['room_number']) ?></span>
                        <h3><?= e($proof['full_name']) ?></h3>
                        <p><?= e(date_id($proof['paid_at'])) ?><?php if ($proof['transaction_reference']): ?> · Ref: <?= e($proof['transaction_reference']) ?><?php endif; ?></p>
                        <strong><?= e(money($proof['amount'])) ?></strong>
                        <small>dari tagihan <?= e(money($proof['amount_due'])) ?></small>
                        <p><a class="text-link" href="index.php?page=payment_proof&amp;id=<?= (int) $proof['id'] ?>">Lihat bukti transfer</a></p>
                    </div>
                    <div class="payment-review-actions">
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve_payment_proof">
                            <input type="hidden" name="payment_id" value="<?= (int) $proof['id'] ?>">
                            <button class="button primary" type="submit">Setujui pembayaran</button>
                        </form>
                        <form method="post" onsubmit="return confirm('Tolak bukti transfer ini? Pengguna dapat mengirim bukti baru.')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reject_payment_proof">
                            <input type="hidden" name="payment_id" value="<?= (int) $proof['id'] ?>">
                            <button class="button secondary" type="submit">Tolak</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state"><strong>Tidak ada pembayaran yang menunggu konfirmasi</strong><p>Bukti pembayaran yang dikirim pengguna akan muncul di sini.</p></div>
    <?php endif; ?>
</div>
