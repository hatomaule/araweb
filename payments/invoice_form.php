<?php
                $rentalScope = $user['role'] === 'operator' ? 'AND b.owner_user_id=:owner_id' : '';
                $rentalParams = $user['role'] === 'operator' ? ['owner_id' => $user['id']] : [];
                $rentals = rows(
                    $pdo,
                    "SELECT r.id,r.monthly_rate,r.start_date,t.full_name,rm.room_number,b.name AS building_name
                     FROM rentals r JOIN tenants t ON t.id=r.tenant_id
                     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
                     WHERE r.status IN ('active','upcoming') {$rentalScope} ORDER BY t.full_name",
                    $rentalParams
                );
            ?>
                <div class="form-panel"><div class="panel-heading"><div><h2>Buat tagihan sewa</h2><p>Tagihan ditautkan ke satu masa sewa.</p></div><a class="text-link" href="index.php?page=invoices">← Kembali</a></div><?php if (!$rentals): ?><div class="alert error">Belum ada masa sewa aktif atau akan datang untuk ditagih.</div><?php else: ?><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="create_invoice"><label class="field full-span"><span>Masa sewa *</span><select name="rental_id" id="invoice-rental" required><option value="">Pilih pelanggan dan kamar</option><?php foreach ($rentals as $rental): ?><option value="<?= (int) $rental['id'] ?>" data-rate="<?= e($rental['monthly_rate']) ?>">#<?= (int) $rental['id'] ?> — <?= e($rental['full_name']) ?> / <?= e($rental['building_name']) ?> · Kamar <?= e($rental['room_number']) ?></option><?php endforeach; ?></select></label>
                    <?php field('Awal periode *', 'period_start', date('Y-m-01'), 'date', ['required' => 'required']); field('Akhir periode *', 'period_end', date('Y-m-t'), 'date', ['required' => 'required']); field('Jatuh tempo *', 'due_date', date('Y-m-d'), 'date', ['required' => 'required']); field('Nominal tagihan (Rp) *', 'amount_due', '', 'number', ['required' => 'required', 'min' => '1', 'step' => '1000', 'id' => 'invoice-amount']); ?>
                    <label class="field full-span"><span>Catatan</span><textarea name="notes" rows="3"></textarea></label><div class="form-actions full-span"><a class="button secondary" href="index.php?page=invoices">Batal</a><button class="button primary" type="submit">Simpan tagihan</button></div></form><?php endif; ?></div>
            