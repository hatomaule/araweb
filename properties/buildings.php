<?php
                $buildings = rows($pdo, "SELECT b.*,
                    (SELECT COUNT(*) FROM rooms rm WHERE rm.building_id=b.id) AS room_count
                    FROM kos_buildings b
                    WHERE (:is_admin=1 OR b.owner_user_id=:owner_id)
                    ORDER BY b.status='active' DESC, b.name", [
                        'is_admin' => $user['role'] === 'admin' ? 1 : 0,
                        'owner_id' => $user['id'],
                    ]);
            ?>
                <div class="page-actions"><p>Kelola gedung, alamat, serta koordinat peta tiap lokasi kos.</p><a class="button primary" href="index.php?page=building_form">＋ Tambah gedung</a></div>
                <div class="panel"><div class="table-scroll"><table><thead><tr><th>Gedung kos</th><th>Alamat</th><th>Koordinat</th><th>Kamar</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                    <?php foreach ($buildings as $building): ?><tr><td><a class="building-name-link" href="index.php?page=property&amp;id=<?= (int) $building['id'] ?>"><span class="building-profile-circle" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($building['name'], 0, 1, 'UTF-8'), 'UTF-8')) ?></span><strong><?= e($building['name']) ?></strong></a></td><td><?= e($building['address']) ?></td><td><?php if ($building['latitude'] !== null && $building['longitude'] !== null): ?><a class="text-link" href="https://www.openstreetmap.org/?mlat=<?= e($building['latitude']) ?>&amp;mlon=<?= e($building['longitude']) ?>#map=17/<?= e($building['latitude']) ?>/<?= e($building['longitude']) ?>" target="_blank" rel="noopener noreferrer"><?= e($building['latitude']) ?>, <?= e($building['longitude']) ?> ↗</a><?php else: ?><span class="badge rejected">Lokasi belum diatur</span><?php endif; ?></td><td><?= (int) $building['room_count'] ?></td><td><span class="badge <?= e($building['status']) ?>"><?= e(status_label($building['status'])) ?></span></td><td class="actions"><a class="button secondary compact-button property-row-action" href="index.php?page=building_form&amp;id=<?= (int) $building['id'] ?>">Edit</a><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="building_status"><input type="hidden" name="id" value="<?= (int) $building['id'] ?>"><input type="hidden" name="status" value="<?= $building['status'] === 'active' ? 'inactive' : 'active' ?>"><button class="button <?= $building['status'] === 'active' ? 'danger' : 'secondary' ?> compact-button property-row-action" type="submit"><?= $building['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?></button></form></td></tr><?php endforeach; ?>
                    <?php if (!$buildings): ?><tr><td colspan="6" class="no-data">Belum ada gedung kos. Tambahkan gedung sebelum mencatat kamar.</td></tr><?php endif; ?>
                </tbody></table></div></div>
            