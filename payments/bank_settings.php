<?php
                $managedBuildings = $user['role'] === 'admin'
                    ? rows($pdo, 'SELECT id,name FROM kos_buildings ORDER BY name')
                    : rows($pdo, 'SELECT id,name FROM kos_buildings WHERE owner_user_id=:owner_id ORDER BY name', ['owner_id' => $user['id']]);
                $bankBuildingId = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($managedBuildings[0]['id'] ?? 0);
                $selectedBankBuilding = null;
                foreach ($managedBuildings as $managedBuilding) {
                    if ((int) $managedBuilding['id'] === $bankBuildingId) {
                        $selectedBankBuilding = $managedBuilding;
                        break;
                    }
                }
                $bank = $selectedBankBuilding
                    ? one($pdo, 'SELECT bank_name, account_name, account_number, instructions, updated_at FROM bank_transfer_settings WHERE building_id=:building_id', ['building_id' => $bankBuildingId])
                    : null;
            ?>
                <div class="form-panel"><div class="panel-heading"><div><h2>Rekening pembayaran kos</h2><p>Setiap kos memiliki rekening pembayaran sendiri untuk booking.</p></div></div>
                    <?php if (!$managedBuildings): ?><div class="empty-state"><strong>Tambahkan kos terlebih dahulu</strong><p>Rekening baru dapat diatur setelah Anda memiliki listing kos.</p><a class="button primary" href="index.php?page=building_form">Tambah kos</a></div>
                    <?php else: ?>
                    <form method="get" class="building-bank-picker"><input type="hidden" name="page" value="bank_settings"><label class="field"><span>Pilih kos</span><select name="id" onchange="this.form.submit()"><?php foreach ($managedBuildings as $managedBuilding): ?><option value="<?= (int) $managedBuilding['id'] ?>" <?= (int) $managedBuilding['id'] === $bankBuildingId ? 'selected' : '' ?>><?= e($managedBuilding['name']) ?></option><?php endforeach; ?></select></label></form>
                    <?php if ($selectedBankBuilding): ?><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="save_bank_settings"><input type="hidden" name="building_id" value="<?= $bankBuildingId ?>">
                        <?php field('Nama bank *', 'bank_name', $bank['bank_name'] ?? '', 'text', ['required' => 'required', 'maxlength' => '100', 'placeholder' => 'Contoh: BCA']); field('Nama pemilik rekening *', 'account_name', $bank['account_name'] ?? '', 'text', ['required' => 'required', 'maxlength' => '150']); field('Nomor rekening *', 'account_number', $bank['account_number'] ?? '', 'text', ['required' => 'required', 'maxlength' => '60']); ?>
                        <label class="field full-span"><span>Petunjuk transfer untuk pelanggan</span><textarea name="instructions" rows="4"><?= e($bank['instructions'] ?? '') ?></textarea></label>
                        <div class="form-actions full-span"><button class="button primary" type="submit">Simpan rekening</button><?php if ($bank): ?><small>Terakhir diperbarui <?= e(date_id($bank['updated_at'])) ?>.</small><?php endif; ?></div>
                    </form><?php endif; ?><?php endif; ?>
                </div>
            