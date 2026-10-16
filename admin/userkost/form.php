<?php
$operators = rows($pdo, "SELECT u.id,u.name,u.email,u.phone,u.status,u.created_at,
    (SELECT COUNT(*) FROM kos_buildings b WHERE b.owner_user_id=u.id) AS property_count
    FROM users u WHERE u.role='operator' ORDER BY u.created_at DESC");
?>
<div class="panel"><div class="panel-heading"><div><h2>Akun pemilik/operator</h2><p>Kelola akses akun pemilik kos yang terdaftar.</p></div></div><div class="table-scroll"><table><thead><tr><th>Nama</th><th>Email</th><th>WhatsApp</th><th>Jumlah kos</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
<?php foreach ($operators as $operator): ?><tr><td><?= e($operator['name']) ?></td><td><?= e($operator['email']) ?></td><td><?= e($operator['phone'] ?: '—') ?></td><td><?= (int) $operator['property_count'] ?></td><td><span class="badge <?= e($operator['status']) ?>"><?= e(status_label($operator['status'])) ?></span></td><td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="set_operator_status"><input type="hidden" name="user_id" value="<?= (int) $operator['id'] ?>"><input type="hidden" name="status" value="<?= $operator['status'] === 'active' ? 'inactive' : 'active' ?>"><button class="text-button" type="submit"><?= $operator['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?></button></form></td></tr><?php endforeach; ?>
<?php if (!$operators): ?><tr><td colspan="6" class="no-data">Belum ada akun operator.</td></tr><?php endif; ?></tbody></table></div></div>
