<?php
$owners = rows($pdo, "SELECT u.id,u.name,u.email,u.phone,u.status,u.created_at,
    (SELECT COUNT(*) FROM kos_buildings b WHERE b.owner_user_id=u.id) AS property_count
    FROM users u WHERE u.role='owner' ORDER BY u.created_at DESC");
?>
<div class="panel"><div class="panel-heading"><div><h2>Akun Owner</h2><p>Kelola akses akun Owner kos yang terdaftar.</p></div></div><div class="table-scroll"><table><thead><tr><th>Nama</th><th>Email</th><th>WhatsApp</th><th>Jumlah kos</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
<?php foreach ($owners as $owner): ?><tr><td><?= e($owner['name']) ?></td><td><?= e($owner['email']) ?></td><td><?= e($owner['phone'] ?: '—') ?></td><td><?= (int) $owner['property_count'] ?></td><td><span class="badge <?= e($owner['status']) ?>"><?= e(status_label($owner['status'])) ?></span></td><td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="set_operator_status"><input type="hidden" name="user_id" value="<?= (int) $owner['id'] ?>"><input type="hidden" name="status" value="<?= $owner['status'] === 'active' ? 'inactive' : 'active' ?>"><button class="text-button" type="submit"><?= $owner['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?></button></form></td></tr><?php endforeach; ?>
<?php if (!$owners): ?><tr><td colspan="6" class="no-data">Belum ada akun Owner.</td></tr><?php endif; ?></tbody></table></div></div>
