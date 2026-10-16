<?php
declare(strict_types=1);

$rooms = rows(
    $pdo,
    "SELECT rm.id,rm.room_number,rm.monthly_rate,b.name AS building_name,
            current_tenant.user_id AS current_user_id,current_tenant.full_name AS current_tenant_name
     FROM rooms rm JOIN kos_buildings b ON b.id=rm.building_id
     LEFT JOIN rentals current_rental ON current_rental.id=(
        SELECT r.id FROM rentals r
        WHERE r.room_id=rm.id AND r.status='active'
          AND r.start_date<=CURDATE() AND (r.end_date IS NULL OR r.end_date>=CURDATE())
        ORDER BY r.start_date DESC,r.id DESC LIMIT 1
     )
     LEFT JOIN tenants current_tenant ON current_tenant.id=current_rental.tenant_id
     WHERE b.owner_user_id=:owner_id AND b.status='active'
       AND b.approval_status='approved'
       AND (
           current_rental.id IS NOT NULL
           OR (rm.status='available' AND NOT EXISTS (
               SELECT 1 FROM rentals r WHERE r.room_id=rm.id AND r.status IN ('active','upcoming')
                 AND (r.status='active' OR r.booking_expires_at IS NULL OR r.booking_expires_at>NOW())
           ))
       )
     ORDER BY b.name,rm.room_number",
    ['owner_id' => $user['id']]
);
?>
<div class="page-actions">
    <p>Catat pembayaran yang diterima, pilih akun penghuni, kamar, nominal, dan periode pembayaran.</p>
</div>
<div class="form-panel">
        <form method="post" enctype="multipart/form-data" id="operator-payment-form" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_operator_payment">
            <input type="hidden" name="existing_user_id" id="existing-user-id" value="">
            <input type="hidden" name="confirm_operator_payment" id="payment-confirmation" value="">

            <label class="field">
                <span>Jenis akun *</span>
                <select name="account_type" id="account-type" required>
                    <option value="existing">Sudah punya akun koskosan.my.id</option>
                    <option value="new">User baru</option>
                </select>
            </label>
            <div class="field" id="existing-search-field">
                <label for="account-search">Cari akun dengan nama, email, atau telepon *</label>
                <input type="search" id="account-search" autocomplete="off" placeholder="Ketik minimal 2 karakter">
                <small class="subline" id="account-search-status">Pilih akun dari hasil pencarian.</small>
                <div class="account-search-results" id="account-search-results" role="listbox"></div>
            </div>
            <div class="form-grid full-span hidden" id="new-account-fields">
                <label class="field"><span>Nama lengkap *</span><input type="text" name="new_name" maxlength="100" autocomplete="name"></label>
                <label class="field"><span>Email *</span><input type="email" name="new_email" maxlength="255" autocomplete="email"></label>
                <label class="field"><span>Nomor telepon *</span><input type="tel" name="new_phone" maxlength="30" autocomplete="tel"></label>
                <label class="field"><span>Password awal *</span><input type="password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password"></label>
                <label class="field">
                    <span>Jenis kelamin *</span>
                    <select name="new_gender">
                        <option value="">Pilih jenis kelamin</option>
                        <option value="male">Laki-laki</option>
                        <option value="female">Perempuan</option>
                    </select>
                </label>
                <label class="field"><span>Foto profil (opsional)</span><input type="file" name="new_profile_photo" accept="image/jpeg,image/png,image/webp"></label>
            </div>
            <div class="form-grid full-span hidden" id="selected-account-fields">
                <label class="field"><span>Nama akun</span><input id="selected-name" type="text" readonly></label>
                <label class="field"><span>Email</span><input id="selected-email" type="text" readonly></label>
                <label class="field"><span>Nomor telepon</span><input id="selected-phone" type="text" readonly></label>
                <label class="field"><span>Jenis kelamin</span><input id="selected-gender" type="text" readonly></label>
            </div>
            <label class="field">
                <span>Kamar atau hunian aktif *</span>
                <select name="room_id" id="payment-room" required>
                    <option value="">Pilih kamar</option>
                    <?php foreach ($rooms as $room): ?>
                        <option value="<?= (int) $room['id'] ?>" data-rate="<?= e($room['monthly_rate']) ?>"
                            data-user-id="<?= (int) ($room['current_user_id'] ?? 0) ?>">
                            <?= e($room['building_name']) ?> · Kamar <?= e($room['room_number']) ?>
                            <?= $room['current_tenant_name'] ? ' — Dihuni ' . e($room['current_tenant_name']) : ' — Tersedia' ?>
                            — <?= e(money($room['monthly_rate'])) ?>/bulan
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$rooms): ?><small class="subline">Belum ada kamar aktif yang dapat dipilih. Tambahkan kamar atau aktifkan kembali gedung.</small><?php endif; ?>
            </label>
            <label class="field">
                <span>Periode pembayaran *</span>
                <select name="period" required>
                    <option value="<?= e(date('Y-m')) ?>">Bulan berjalan (<?= e(date_id(date('Y-m-01'))) ?>)</option>
                    <option value="<?= e(date('Y-m', strtotime('first day of next month'))) ?>">Bulan depan (<?= e(date_id(date('Y-m-01', strtotime('first day of next month')))) ?>)</option>
                </select>
                <small class="subline">Jika tagihan pada periode terpilih sudah lunas, sistem akan memberi peringatan. Pilih bulan berikutnya untuk mencatat pembayaran selanjutnya.</small>
            </label>
            <label class="field">
                <span>Nominal *</span>
                <select name="amount_type" id="amount-type" required>
                    <option value="room_rate">Gunakan harga kamar</option>
                    <option value="custom">Masukkan harga khusus transaksi</option>
                </select>
            </label>
            <label class="field hidden" id="custom-amount-field">
                <span>Nominal khusus (Rp) *</span>
                <input type="number" name="custom_amount" id="custom-amount" min="1" step="1000" inputmode="numeric">
                <small class="subline">Harga khusus hanya berlaku untuk pembayaran ini dan tidak mengubah harga kamar.</small>
            </label>
            <label class="field">
                <span>Metode pembayaran *</span>
                <select name="payment_method" required>
                    <option value="cash">Tunai</option>
                    <option value="bank_transfer">Transfer bank</option>
                    <option value="e_wallet">E-wallet</option>
                    <option value="other">Lainnya</option>
                </select>
            </label>
            <div class="form-actions full-span">
                <button class="button primary" type="button" id="open-payment-confirmation">Simpan pembayaran</button>
            </div>
        </form>
        <dialog class="action-dialog" id="operator-payment-confirmation">
            <div class="action-dialog-form" id="payment-confirmation-dialog">
                <button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button>
                <span class="dialog-eyebrow">KONFIRMASI PEMBAYARAN</span>
                <h2>Catat pembayaran?</h2>
                <p>Hunian dan tagihan akan dibuat, lalu pembayaran dicatat sebagai sudah diterima.</p>
                <p class="payment-confirmation-summary" id="payment-confirmation-summary"></p>
                <label class="confirmation-check">
                    <input type="checkbox" id="confirm-payment-checkbox" required>
                    <span>Saya memahami pembayaran ini akan dicatat.</span>
                </label>
                <div class="dialog-actions">
                    <button class="button secondary" type="button" data-dialog-close>Batal</button>
                    <button class="button primary" type="button" id="confirm-payment-submit">Ya, simpan pembayaran</button>
                </div>
            </div>
        </dialog>
</div>
<script>
(() => {
    const typeSelect = document.getElementById('account-type');
    if (!typeSelect) return;
    const searchField = document.getElementById('existing-search-field');
    const newFields = document.getElementById('new-account-fields');
    const selectedFields = document.getElementById('selected-account-fields');
    const search = document.getElementById('account-search');
    const results = document.getElementById('account-search-results');
    const status = document.getElementById('account-search-status');
    const selectedId = document.getElementById('existing-user-id');
    const roomSelect = document.getElementById('payment-room');
    const filterRooms = () => {
        const isNew = typeSelect.value === 'new';
        const selectedUserId = isNew ? '' : selectedId.value;
        Array.from(roomSelect.options).forEach((option) => {
            if (!option.value) return;
            const roomUserId = option.dataset.userId || '0';
            option.hidden = roomUserId !== '0' && roomUserId !== selectedUserId;
            option.disabled = option.hidden;
        });
        if (roomSelect.selectedOptions[0]?.disabled) roomSelect.value = '';
    };
    let searchTimer;
    let requestNumber = 0;
    const toggleRequired = (container, required) => {
        container.querySelectorAll('input,select').forEach((input) => {
            input.required = required && input.type !== 'file';
        });
    };
    const setAccountType = () => {
        const isNew = typeSelect.value === 'new';
        searchField.classList.toggle('hidden', isNew);
        newFields.classList.toggle('hidden', !isNew);
        selectedFields.classList.toggle('hidden', isNew || !selectedId.value);
        toggleRequired(newFields, isNew);
        if (isNew) selectedId.value = '';
        filterRooms();
    };
    typeSelect.addEventListener('change', setAccountType);
    setAccountType();

    search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        selectedId.value = '';
        selectedFields.classList.add('hidden');
        results.replaceChildren();
        filterRooms();
        const term = search.value.trim();
        if (term.length < 2) {
            status.textContent = 'Ketik minimal 2 karakter untuk mencari akun.';
            return;
        }
        status.textContent = 'Mencari akun…';
        const currentRequest = ++requestNumber;
        searchTimer = setTimeout(async () => {
            try {
                const response = await fetch(`index.php?page=operator_payment&account_lookup=1&term=${encodeURIComponent(term)}`, {
                    headers: {'Accept': 'application/json'}
                });
                if (!response.ok) throw new Error('Pencarian akun gagal.');
                const payload = await response.json();
                if (currentRequest !== requestNumber) return;
                results.replaceChildren();
                payload.accounts.forEach((account) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'account-search-result';
                    button.setAttribute('role', 'option');
                    button.textContent = [account.name, account.email, account.phone].filter(Boolean).join(' · ');
                    button.addEventListener('click', () => {
                        selectedId.value = account.id;
                        document.getElementById('selected-name').value = account.name || '';
                        document.getElementById('selected-email').value = account.email || '';
                        document.getElementById('selected-phone').value = account.phone || '';
                        document.getElementById('selected-gender').value = account.gender === 'male' ? 'Laki-laki' : (account.gender === 'female' ? 'Perempuan' : '—');
                        selectedFields.classList.remove('hidden');
                        results.replaceChildren();
                        status.textContent = 'Akun terpilih. Data akun terisi otomatis.';
                        filterRooms();
                    });
                    results.append(button);
                });
                status.textContent = payload.accounts.length ? 'Pilih salah satu akun berikut.' : 'Tidak ada akun kosong yang cocok.';
            } catch (error) {
                status.textContent = error.message || 'Pencarian akun gagal.';
            }
        }, 250);
    });

    const amountType = document.getElementById('amount-type');
    const customField = document.getElementById('custom-amount-field');
    const customAmount = document.getElementById('custom-amount');
    const updateAmount = () => {
        const isCustom = amountType.value === 'custom';
        customField.classList.toggle('hidden', !isCustom);
        customAmount.required = isCustom;
        customAmount.disabled = !isCustom;
        if (!isCustom) customAmount.value = '';
    };
    amountType.addEventListener('change', updateAmount);
    roomSelect.addEventListener('change', () => {
        const rate = roomSelect.selectedOptions[0]?.dataset.rate;
        if (rate && amountType.value === 'custom') customAmount.placeholder = `Harga kamar saat ini Rp ${Number(rate).toLocaleString('id-ID')}`;
    });
    updateAmount();

    const dialog = document.getElementById('operator-payment-confirmation');
    document.getElementById('open-payment-confirmation').addEventListener('click', () => {
        const form = document.getElementById('operator-payment-form');
        if (!form.reportValidity()) return;
        if (typeSelect.value === 'existing' && !selectedId.value) {
            status.textContent = 'Pilih akun koskosan.my.id dari hasil pencarian terlebih dahulu.';
            search.focus();
            return;
        }
        const accountName = typeSelect.value === 'new'
            ? form.elements.new_name.value.trim()
            : document.getElementById('selected-name').value;
        const roomLabel = roomSelect.selectedOptions[0]?.textContent.trim() || '';
        const periodLabel = form.elements.period.selectedOptions[0]?.textContent.trim() || '';
        const amount = amountType.value === 'custom'
            ? Number(customAmount.value)
            : Number(roomSelect.selectedOptions[0]?.dataset.rate || 0);
        document.getElementById('payment-confirmation-summary').textContent =
            `${accountName} · ${roomLabel} · ${periodLabel} · Rp ${amount.toLocaleString('id-ID')}`;
        dialog.showModal();
    });
    document.getElementById('confirm-payment-submit').addEventListener('click', () => {
        const confirmationCheckbox = document.getElementById('confirm-payment-checkbox');
        if (!confirmationCheckbox.checked) {
            confirmationCheckbox.focus();
            confirmationCheckbox.reportValidity();
            return;
        }
        document.getElementById('payment-confirmation').value = 'yes';
        dialog.close();
        document.getElementById('operator-payment-form').requestSubmit();
    });
    dialog.querySelectorAll('[data-dialog-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
})();
</script>
