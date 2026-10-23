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
                $sharedBankSchema = (int) $pdo->query(
                    "SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='owner_bank_accounts'"
                )->fetchColumn() === 1
                    && (int) $pdo->query(
                        "SELECT COUNT(*) FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bank_transfer_settings'
                           AND COLUMN_NAME='bank_account_id'"
                    )->fetchColumn() === 1;
                $bank = $selectedBankBuilding
                    ? one($pdo, $sharedBankSchema
                        ? 'SELECT bank_name, account_name, account_number, instructions, updated_at, bank_account_id FROM bank_transfer_settings WHERE building_id=:building_id'
                        : 'SELECT bank_name, account_name, account_number, instructions, updated_at FROM bank_transfer_settings WHERE building_id=:building_id',
                        ['building_id' => $bankBuildingId])
                    : null;
                $ownerBankAccounts = $sharedBankSchema && $user['role'] === 'owner'
                    ? rows($pdo, 'SELECT id, bank_name, account_name, account_number FROM owner_bank_accounts WHERE owner_user_id=:owner_id ORDER BY bank_name, account_name, id', ['owner_id' => $user['id']])
                    : [];
                $selectedOwnerBankAccount = null;
                foreach ($ownerBankAccounts as $ownerBankAccount) {
                    if ((int) $ownerBankAccount['id'] === (int) ($bank['bank_account_id'] ?? 0)) {
                        $selectedOwnerBankAccount = $ownerBankAccount;
                        break;
                    }
                }
            ?>
                <div class="form-panel"><div class="panel-heading"><div><h2>Rekening pembayaran kos</h2><p>Gunakan rekening bersama untuk beberapa gedung atau buat rekening baru.</p></div></div>
                    <?php if (!$managedBuildings): ?><div class="empty-state"><strong>Tambahkan kos terlebih dahulu</strong><p>Rekening baru dapat diatur setelah Anda memiliki listing kos.</p><a class="button primary" href="index.php?page=building_form">Tambah kos</a></div>
                    <?php else: ?>
                    <form method="get" class="building-bank-picker"><input type="hidden" name="page" value="bank_settings"><label class="field"><span>Pilih kos</span><select name="id" onchange="this.form.submit()"><?php foreach ($managedBuildings as $managedBuilding): ?><option value="<?= (int) $managedBuilding['id'] ?>" <?= (int) $managedBuilding['id'] === $bankBuildingId ? 'selected' : '' ?>><?= e($managedBuilding['name']) ?></option><?php endforeach; ?></select></label></form>
                    <?php if ($selectedBankBuilding): ?><form method="post" class="form-grid" <?= $sharedBankSchema && $user['role'] === 'owner' ? 'data-bank-settings' : '' ?>><?= csrf_field() ?><input type="hidden" name="action" value="save_bank_settings"><input type="hidden" name="building_id" value="<?= $bankBuildingId ?>">
                        <?php if ($sharedBankSchema && $user['role'] === 'owner'): ?>
                            <fieldset class="bank-account-mode full-span">
                                <legend>Rekening untuk gedung ini</legend>
                                <label><input type="radio" name="bank_mode" value="existing" <?= $selectedOwnerBankAccount || $ownerBankAccounts ? 'checked' : '' ?> <?= !$ownerBankAccounts ? 'disabled' : '' ?>><span>Gunakan rekening yang sudah ada</span></label>
                                <label><input type="radio" name="bank_mode" value="new" <?= $ownerBankAccounts ? '' : 'checked' ?>><span>Buat rekening baru</span></label>
                            </fieldset>
                            <label class="field full-span" data-bank-existing-field <?= $selectedOwnerBankAccount || $ownerBankAccounts ? '' : 'hidden' ?>>
                                <span>Pilih rekening tersimpan</span>
                                <select name="bank_account_id" data-bank-account-picker <?= $selectedOwnerBankAccount ? 'required' : 'disabled' ?>>
                                    <option value="">Pilih rekening</option>
                                    <?php foreach ($ownerBankAccounts as $ownerBankAccount): ?>
                                        <option value="<?= (int) $ownerBankAccount['id'] ?>"
                                            data-bank-name="<?= e($ownerBankAccount['bank_name']) ?>"
                                            data-account-name="<?= e($ownerBankAccount['account_name']) ?>"
                                            data-account-number="<?= e($ownerBankAccount['account_number']) ?>"
                                            <?= $selectedOwnerBankAccount && (int) $ownerBankAccount['id'] === (int) $selectedOwnerBankAccount['id'] ? 'selected' : '' ?>>
                                            <?= e($ownerBankAccount['bank_name'] . ' · ' . $ownerBankAccount['account_name'] . ' · ' . $ownerBankAccount['account_number']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!$ownerBankAccounts): ?><small class="field-help">Belum ada rekening tersimpan. Isi detail rekening di bawah untuk membuat rekening pertama.</small><?php endif; ?>
                            </label>
                            <p class="field-help full-span" data-bank-shared-note <?= $selectedOwnerBankAccount ? '' : 'hidden' ?>>Mengubah detail dan menyimpan akan memperbarui rekening ini di semua gedung yang menggunakannya. Petunjuk transfer tetap dapat berbeda untuk setiap kos.</p>
                        <?php endif; ?>
                        <?php
                        $accountFields = $selectedOwnerBankAccount ?? $bank ?? [];
                        field('Nama bank *', 'bank_name', $accountFields['bank_name'] ?? '', 'text', ['required' => 'required', 'maxlength' => '100', 'placeholder' => 'Contoh: BCA']);
                        field('Nama pemilik rekening *', 'account_name', $accountFields['account_name'] ?? '', 'text', ['required' => 'required', 'maxlength' => '150']);
                        field('Nomor rekening *', 'account_number', $accountFields['account_number'] ?? '', 'text', ['required' => 'required', 'maxlength' => '60']);
                        ?>
                        <label class="field full-span"><span>Petunjuk transfer untuk pelanggan</span><textarea name="instructions" rows="4"><?= e($bank['instructions'] ?? '') ?></textarea></label>
                        <div class="form-actions full-span"><button class="button primary" type="submit">Simpan rekening</button><?php if ($bank): ?><small>Terakhir diperbarui <?= e(date_id($bank['updated_at'])) ?>.</small><?php endif; ?></div>
                    </form><?php endif; ?><?php endif; ?>
                </div>
            