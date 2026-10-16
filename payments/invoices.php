<?php
                $invoiceScope = $user['role'] === 'operator' ? 'AND b.owner_user_id=:owner_id' : '';
                $invoiceParams = $user['role'] === 'operator' ? ['owner_id' => $user['id']] : [];
                $list = rows(
                    $pdo,
                    "SELECT i.*,t.full_name,rm.room_number,b.name AS building_name,COALESCE(p.paid,0) AS paid
                     FROM invoices i JOIN rentals r ON r.id=i.rental_id
                     JOIN tenants t ON t.id=r.tenant_id JOIN rooms rm ON rm.id=r.room_id
                     JOIN kos_buildings b ON b.id=rm.building_id
                     LEFT JOIN (SELECT invoice_id,SUM(amount) AS paid FROM payments
                                WHERE status='confirmed' GROUP BY invoice_id) p ON p.invoice_id=i.id
                     WHERE i.status<>'void' {$invoiceScope} ORDER BY i.due_date DESC",
                    $invoiceParams
                );
            ?>
                <div class="page-actions"><p>Terbitkan tagihan dan pantau sisa pembayaran.</p><a class="button primary" href="index.php?page=invoice_form">＋ Buat tagihan</a></div><div class="panel"><div class="table-scroll"><table><thead><tr><th>No. tagihan</th><th>Pelanggan / kamar</th><th>Periode</th><th>Jatuh tempo</th><th>Tagihan</th><th>Sisa</th><th>Status</th></tr></thead><tbody>
                    <?php foreach ($list as $row): ?><tr><td><strong><?= e($row['invoice_number']) ?></strong></td><td><?= e($row['full_name']) ?><small class="subline"><?= e($row['building_name']) ?> · Kamar <?= e($row['room_number']) ?></small></td><td><?= e(date_id($row['period_start'])) ?> – <?= e(date_id($row['period_end'])) ?></td><td><?= e(date_id($row['due_date'])) ?></td><td><?= e(money($row['amount_due'])) ?></td><td><?= e(money(max(0, (float) $row['amount_due'] - (float) $row['paid']))) ?></td><td><span class="badge <?= e($row['status']) ?>"><?= e(status_label($row['status'])) ?></span></td></tr><?php endforeach; ?>
                    <?php if (!$list): ?><tr><td colspan="7" class="no-data">Belum ada tagihan.</td></tr><?php endif; ?></tbody></table></div></div>
            