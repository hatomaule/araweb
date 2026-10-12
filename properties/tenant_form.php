<?php
                $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
                if ($user['role'] === 'operator' && $id > 0) {
                    set_flash('error', 'Operator dapat menambahkan penghuni dan mengganti password, tetapi tidak mengedit atau menonaktifkan akun.');
                    redirect('tenants');
                }
                $record = $id ? one($pdo, 'SELECT * FROM tenants WHERE id=:id', ['id' => $id]) : null;
                if ($id && !$record) { set_flash('error', 'Pelanggan tidak ditemukan.'); redirect('tenants'); }
                $availableRooms = $user['role'] === 'operator'
                    ? rows(
                        $pdo,
                        "SELECT rm.id,rm.room_number,rm.monthly_rate,b.name AS building_name
                         FROM rooms rm JOIN kos_buildings b ON b.id=rm.building_id
                         WHERE b.owner_user_id=:owner_id AND b.status='active' AND rm.status='available'
                         ORDER BY b.name,rm.room_number",
                        ['owner_id' => $user['id']]
                    )
                    : [];
            ?>
                <div class="form-panel"><div class="panel-heading"><div><h2><?= $user['role'] === 'operator' ? 'Tambah akun penghuni' : ($record ? 'Edit pelanggan' : 'Tambah pelanggan') ?></h2><p><?= $user['role'] === 'operator' ? 'Buat akun pengguna dan tautkan langsung ke kamar kos Anda yang tersedia.' : 'Lengkapi detail pelanggan kos.' ?></p></div><a class="text-link" href="index.php?page=tenants">← Kembali</a></div>
                <?php if ($user['role'] === 'operator'): ?>
                    <?php if (!$availableRooms): ?><div class="alert info">Belum ada kamar tersedia di kos Anda. Tambahkan atau kosongkan kamar sebelum mendaftarkan penghuni.</div><?php else: ?>
                    <form method="post" enctype="multipart/form-data" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="operator_add_tenant_account">
                        <?php field('Nama lengkap *', 'full_name', '', 'text', ['required' => 'required', 'maxlength' => '100']); field('Email untuk login *', 'email', '', 'email', ['required' => 'required', 'maxlength' => '255', 'autocomplete' => 'email']); field('Nomor telepon *', 'phone', '', 'tel', ['required' => 'required', 'maxlength' => '30']); field('Password awal *', 'password', '', 'password', ['required' => 'required', 'minlength' => '8', 'maxlength' => '72', 'autocomplete' => 'new-password']); ?>
                        <label class="field"><span>Jenis kelamin *</span><select name="gender" required><option value="">Pilih jenis kelamin</option><option value="male">Pria</option><option value="female">Wanita</option></select></label>
                        <label class="field"><span>Foto profil (opsional)</span><input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"><small class="field-help">Jika tidak diunggah, avatar mengikuti jenis kelamin.</small></label>
                        <label class="field full-span"><span>Kamar tersedia *</span><select name="room_id" required><option value="">Pilih kamar untuk penghuni</option><?php foreach ($availableRooms as $room): ?><option value="<?= (int) $room['id'] ?>"><?= e($room['building_name']) ?> · Kamar <?= e($room['room_number']) ?> · <?= e(money($room['monthly_rate'])) ?>/bulan</option><?php endforeach; ?></select><small class="field-help">Penyimpanan akan membuat masa sewa aktif mulai hari ini dan menandai kamar terisi.</small></label>
                        <div class="form-actions full-span"><a class="button secondary" href="index.php?page=tenants">Batal</a><button class="button primary" type="submit">Buat akun penghuni</button></div>
                    </form><?php endif; ?>
                <?php else: ?>
                <form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="save_tenant"><input type="hidden" name="id" value="<?= $id ?>">
                    <?php field('Nama lengkap *', 'full_name', $record['full_name'] ?? '', 'text', ['required' => 'required', 'maxlength' => '150']); field('Nomor telepon *', 'phone', $record['phone'] ?? '', 'tel', ['required' => 'required', 'maxlength' => '30']); field('Email', 'email', $record['email'] ?? '', 'email'); field('Nomor identitas', 'identity_number', $record['identity_number'] ?? '', 'text', ['maxlength' => '50']); field('Nama kontak darurat', 'emergency_contact_name', $record['emergency_contact_name'] ?? ''); field('Telepon kontak darurat', 'emergency_contact_phone', $record['emergency_contact_phone'] ?? '', 'tel'); ?>
                    <label class="field full-span"><span>Alamat</span><textarea name="address" rows="3"><?= e($record['address'] ?? '') ?></textarea></label><label class="field full-span"><span>Catatan</span><textarea name="notes" rows="3"><?= e($record['notes'] ?? '') ?></textarea></label><div class="form-actions full-span"><a class="button secondary" href="index.php?page=tenants">Batal</a><button class="button primary" type="submit">Simpan pelanggan</button></div>
                </form><?php endif; ?></div>
            