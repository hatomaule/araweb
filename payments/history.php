<?php
                $paymentScope = $user['role'] === 'operator' ? 'AND b.owner_user_id=:owner_id' : '';
                $scopeParams = $user['role'] === 'operator' ? ['owner_id' => $user['id']] : [];
                $list = rows(
                    $pdo,
                    "SELECT p.*,i.invoice_number,t.id AS tenant_id,t.full_name,rm.room_number,b.name AS building_name
                     FROM payments p JOIN invoices i ON i.id=p.invoice_id
                     JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id
                     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
                     WHERE 1=1 {$paymentScope} ORDER BY p.paid_at DESC,p.id DESC LIMIT 500",
                    $scopeParams
                );
                $pendingProofs = rows(
                    $pdo,
                    "SELECT p.id,p.amount,p.paid_at,p.transaction_reference,p.payment_proof_path,
                            i.invoice_number,i.amount_due,t.full_name,rm.room_number,b.name AS building_name
                     FROM payments p JOIN invoices i ON i.id=p.invoice_id
                     JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id
                     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
                     WHERE p.status='pending' AND p.payment_proof_path IS NOT NULL {$paymentScope}
                     ORDER BY p.paid_at,p.id",
                    $scopeParams
                );
                $openInvoices = rows(
                    $pdo,
                    "SELECT i.id,i.invoice_number,i.amount_due,t.full_name,rm.room_number,b.name AS building_name,
                            COALESCE(p.paid,0) AS paid
                     FROM invoices i JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id
                     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
                     LEFT JOIN (SELECT invoice_id,SUM(amount) AS paid FROM payments
                                WHERE status='confirmed' GROUP BY invoice_id) p ON p.invoice_id=i.id
                     WHERE i.status='open' AND i.amount_due>COALESCE(p.paid,0)
                       AND NOT EXISTS (SELECT 1 FROM payments pp
                                       WHERE pp.invoice_id=i.id AND pp.status='pending')
                       {$paymentScope} ORDER BY i.due_date",
                    $scopeParams
                );
                $summary = one(
                    $pdo,
                    "SELECT COALESCE(SUM(CASE WHEN p.status='confirmed' THEN p.amount ELSE 0 END),0) AS collected,
                            COALESCE(SUM(CASE WHEN p.status='pending' THEN p.amount ELSE 0 END),0) AS pending,
                            COUNT(CASE WHEN p.status='confirmed' THEN 1 END) AS confirmed_count
                     FROM payments p JOIN invoices i ON i.id=p.invoice_id
                     JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
                     JOIN kos_buildings b ON b.id=rm.building_id WHERE 1=1 {$paymentScope}",
                    $scopeParams
                );
                $arrears = one(
                    $pdo,
                    "SELECT COALESCE(SUM(GREATEST(i.amount_due-COALESCE(p.paid,0),0)),0) AS total
                     FROM invoices i JOIN rentals r ON r.id=i.rental_id
                     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
                     LEFT JOIN (SELECT invoice_id,SUM(amount) AS paid FROM payments
                                WHERE status='confirmed' GROUP BY invoice_id) p ON p.invoice_id=i.id
                     WHERE i.status='open' AND i.due_date<CURDATE() {$paymentScope}",
                    $scopeParams
                );
                $propertyIncome = rows(
                    $pdo,
                    "SELECT b.id,b.name,
                       (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN invoices i ON i.id=p.invoice_id
                        JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
                        WHERE rm.building_id=b.id AND p.status='confirmed') AS collected,
                       (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN invoices i ON i.id=p.invoice_id
                        JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
                        WHERE rm.building_id=b.id AND p.status='pending') AS pending
                     FROM kos_buildings b
                     WHERE " . ($user['role'] === 'operator' ? 'b.owner_user_id=:owner_id' : 'b.owner_user_id IS NOT NULL') . "
                     ORDER BY b.name",
                    $scopeParams
                );
            ?>
                <div class="welcome-row"><div><h2><?= $user['role'] === 'operator' ? 'Keuangan kos saya' : 'Histori pembayaran platform' ?></h2><p><?= $user['role'] === 'operator' ? 'Data hanya mencakup kos yang terhubung ke akun Anda.' : 'Pantau transaksi dan pemasukan listing operator.' ?></p></div></div>
                <div class="stats-grid payment-history-stats">
                    <article class="stat-card"><div class="stat-icon green">✓</div><div><span>Total pemasukan terkonfirmasi</span><strong><?= e(money($summary['collected'])) ?></strong></div></article>
                    <article class="stat-card"><div class="stat-icon orange">◷</div><div><span>Menunggu verifikasi</span><strong><?= e(money($summary['pending'])) ?></strong></div></article>
                    <article class="stat-card"><div class="stat-icon purple">▤</div><div><span>Pembayaran terkonfirmasi</span><strong><?= (int) $summary['confirmed_count'] ?></strong></div></article>
                    <article class="stat-card"><div class="stat-icon orange">!</div><div><span>Tunggakan jatuh tempo</span><strong><?= e(money($arrears['total'])) ?></strong></div></article>
                </div>
                <div class="panel"><div class="panel-heading"><div><h2>Akumulasi pendapatan per kos</h2><p>Total dibayar hanya menghitung transaksi terkonfirmasi.</p></div></div><div class="table-scroll"><table><thead><tr><th>Nama kos</th><th>Pemasukan terkonfirmasi</th><th>Menunggu verifikasi</th></tr></thead><tbody>
                    <?php foreach ($propertyIncome as $income): ?><tr><td><strong><?= e($income['name']) ?></strong></td><td><?= e(money($income['collected'])) ?></td><td><?= e(money($income['pending'])) ?></td></tr><?php endforeach; ?>
                    <?php if (!$propertyIncome): ?><tr><td colspan="3" class="no-data">Belum ada data kos yang dapat ditampilkan.</td></tr><?php endif; ?>
                </tbody></table></div></div>
                <?php if ($pendingProofs): ?><div class="panel"><div class="panel-heading"><div><h2>Verifikasi bukti transfer</h2><p><?= count($pendingProofs) ?> pembayaran menunggu pemeriksaan</p></div></div><div class="payment-review-list">
                    <?php foreach ($pendingProofs as $proof): ?><article class="payment-review-card"><div><span class="section-eyebrow"><?= e($proof['invoice_number']) ?> · <?= e($proof['building_name']) ?> · KAMAR <?= e($proof['room_number']) ?></span><h3><?= e($proof['full_name']) ?></h3><p><?= e(date_id($proof['paid_at'])) ?><?php if ($proof['transaction_reference']): ?> · Ref: <?= e($proof['transaction_reference']) ?><?php endif; ?></p><strong><?= e(money($proof['amount'])) ?></strong> <small>dari tagihan <?= e(money($proof['amount_due'])) ?></small><p><a class="text-link" href="index.php?page=payment_proof&amp;id=<?= (int) $proof['id'] ?>" target="_blank" rel="noopener noreferrer">Lihat bukti transfer ↗</a></p></div><div class="payment-review-actions"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="approve_payment_proof"><input type="hidden" name="payment_id" value="<?= (int) $proof['id'] ?>"><button class="button primary" type="submit">Setujui</button></form><form method="post" onsubmit="return confirm('Tolak bukti transfer ini? Pelanggan dapat mengirim bukti baru.')"><?= csrf_field() ?><input type="hidden" name="action" value="reject_payment_proof"><input type="hidden" name="payment_id" value="<?= (int) $proof['id'] ?>"><button class="button secondary" type="submit">Tolak</button></form></div></article><?php endforeach; ?>
                </div></div><?php endif; ?>
                <?php if ($openInvoices): ?><div class="form-panel compact"><div class="panel-heading"><div><h2>Catat pembayaran</h2><p>Pilih tagihan yang akan dibayar.</p></div></div><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="create_payment"><label class="field full-span"><span>Tagihan *</span><select name="invoice_id" id="payment-invoice" required><option value="">Pilih tagihan</option><?php foreach ($openInvoices as $invoice): ?><option value="<?= (int) $invoice['id'] ?>" data-remaining="<?= e((float) $invoice['amount_due'] - (float) $invoice['paid']) ?>"><?= e($invoice['invoice_number']) ?> — <?= e($invoice['full_name']) ?> / Kamar <?= e($invoice['room_number']) ?> (sisa <?= e(money((float) $invoice['amount_due'] - (float) $invoice['paid'])) ?>)</option><?php endforeach; ?></select></label>
                    <?php field('Nominal diterima (Rp) *', 'amount', '', 'number', ['required' => 'required', 'min' => '1', 'step' => '1000', 'id' => 'payment-amount']); ?>
                    <label class="field"><span>Metode pembayaran *</span><select name="payment_method"><option value="cash">Tunai</option><option value="bank_transfer">Transfer bank</option><option value="e_wallet">E-wallet</option><option value="other">Lainnya</option></select></label>
                    <?php field('Referensi transaksi', 'transaction_reference', ''); ?><label class="field full-span"><span>Catatan</span><textarea name="notes" rows="2"></textarea></label><div class="form-actions full-span"><button class="button primary" type="submit">Catat pembayaran</button></div></form></div><?php else: ?><div class="alert success">Tidak ada tagihan yang menunggu pembayaran.</div><?php endif; ?>
                <div class="panel"><div class="panel-heading"><div><h2>Riwayat pembayaran</h2><p><?= count($list) ?> transaksi terbaru yang dapat diakses akun ini</p></div></div><div class="table-scroll"><table><thead><tr><th>Tanggal</th><th>No. tagihan</th><th>Pelanggan / kamar</th><th>Metode</th><th>Nominal</th><th>Status</th></tr></thead><tbody>
                    <?php foreach ($list as $row): ?><tr><td><?= e($row['paid_at'] ? date_id($row['paid_at']) : '—') ?></td><td><strong><?= e($row['invoice_number']) ?></strong><?php if ($row['payment_proof_path']): ?><small class="subline"><a class="text-link" href="index.php?page=payment_proof&amp;id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener noreferrer">Lihat bukti</a></small><?php endif; ?></td><td><a class="text-link" href="index.php?page=tenant_payments&amp;id=<?= (int) $row['tenant_id'] ?>"><?= e($row['full_name']) ?></a><small class="subline"><?= e($row['building_name']) ?> · Kamar <?= e($row['room_number']) ?></small></td><td><?= e(['cash' => 'Tunai', 'bank_transfer' => 'Transfer bank', 'e_wallet' => 'E-wallet', 'other' => 'Lainnya'][$row['payment_method']] ?? $row['payment_method']) ?></td><td><?= e(money($row['amount'])) ?></td><td><span class="badge <?= e($row['status']) ?>"><?= e(status_label($row['status'])) ?></span></td></tr><?php endforeach; ?>
                    <?php if (!$list): ?><tr><td colspan="6" class="no-data">Belum ada pembayaran.</td></tr><?php endif; ?></tbody></table></div></div>
