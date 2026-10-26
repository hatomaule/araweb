<?php
$buildings = rows($pdo, "SELECT b.*,
    (SELECT COUNT(*) FROM rooms rm WHERE rm.building_id=b.id) AS room_count,
    (SELECT gr.admin_note FROM owner_gold_verification_requests gr
     WHERE gr.building_id=b.id ORDER BY gr.id DESC LIMIT 1) AS gold_review_note,
    COALESCE(b.profile_photo_path,
        (SELECT pp.photo_path FROM property_photos pp
         WHERE pp.building_id=b.id ORDER BY pp.display_order,pp.id LIMIT 1)
    ) AS cover_photo
    FROM kos_buildings b
    WHERE (:is_admin=1 OR b.owner_user_id=:owner_id)
    ORDER BY b.status='active' DESC, b.name", [
        'is_admin' => $user['role'] === 'admin' ? 1 : 0,
        'owner_id' => $user['id'],
    ]);
$buildingProgressById = building_completions(
    $pdo,
    array_map(static fn(array $building): int => (int) $building['id'], $buildings)
);
$ownerProfileProgress = $user['role'] === 'owner'
    ? owner_profile_completion($pdo, (int) $user['id']) : null;
$ownerIsGold = $user['role'] === 'owner'
    ? owner_has_gold_verification($pdo, (int) $user['id']) : false;
$publicationLabels = [
    'draft' => 'Draft',
    'pending' => 'Menunggu Admin',
    'published' => 'Tayang',
    'revision' => 'Perlu perbaikan',
    'rejected' => 'Ditolak',
    'suspended' => 'Ditangguhkan',
];
$goldLabels = [
    'not_requested' => 'Belum diajukan Gold',
    'pending' => 'Menunggu verifikasi Gold',
    'verified' => 'Gold terverifikasi',
    'rejected' => 'Pengajuan Gold ditolak',
];
?>
<div class="page-actions building-list-heading">
    <div><h2><?= $user['role'] === 'admin' ? 'Gedung kos' : 'Gedung kos saya' ?></h2><p>Kelola informasi, galeri foto, lokasi, kamar, dan status setiap gedung.</p></div>
    <a class="button primary" href="index.php?page=building_form">＋ Tambah gedung</a>
</div>
<?php if ($user['role'] === 'owner' && $ownerProfileProgress): ?>
    <section class="panel owner-onboarding-summary">
        <div class="panel-heading">
            <div>
                <h2><?= $ownerIsGold ? 'Gold' : 'Basic' ?></h2>
            </div>
            <a class="button secondary" href="index.php?page=profile">Lengkapi profil Owner</a>
        </div>
        <label class="field-help" for="owner-profile-progress">Kelengkapan profil Owner: <?= $ownerProfileProgress['percent'] ?>%</label>
        <progress id="owner-profile-progress" max="100" value="<?= $ownerProfileProgress['percent'] ?>"><?= $ownerProfileProgress['percent'] ?>%</progress>
        <small class="subline"><?= $ownerProfileProgress['completed'] ?> dari <?= $ownerProfileProgress['total'] ?> data profil terisi. Progres profil ini terpisah dari progres setiap gedung.</small>
    </section>
<?php endif; ?>
<section class="building-list" data-building-card-list data-building-view-storage-key="buildingView-<?= e($user['role']) ?>-<?= (int) $user['id'] ?>">
    <div class="building-list-tools">
        <label class="building-list-search"><span>Cari gedung</span><input type="search" data-building-card-search placeholder="Nama atau alamat gedung..." aria-label="Cari gedung berdasarkan nama atau alamat"></label>
        <label class="building-list-page-size"><span>Tampilkan</span><select data-building-card-page-size aria-label="Jumlah gedung yang ditampilkan"><option value="5" selected>5</option><option value="10">10</option><option value="all">Semua</option></select></label>
        <div class="building-list-navigation">
            <span data-building-card-summary aria-live="polite"></span>
            <button class="button secondary compact-button" type="button" data-building-card-previous>Sebelumnya</button>
            <button class="button secondary compact-button" type="button" data-building-card-next>Berikutnya</button>
        </div>
        <div class="data-table-view-switch data-view-picker building-view-switch" data-building-view-switch role="group" aria-label="Pilih tampilan gedung"></div>
    </div>
    <div class="building-card-grid" data-building-card-grid>
        <?php foreach ($buildings as $building):
            $completion = $buildingProgressById[(int) $building['id']];
            $buildingInitial = iconv_substr((string) $building['name'], 0, 1, 'UTF-8');
            if ($buildingInitial === false) {
                $buildingInitial = substr((string) $building['name'], 0, 1);
            }
            $isActive = $building['status'] === 'active';
            $isPublic = $building['status'] === 'active'
                && $building['publication_status'] === 'published';
            $goldReady = $completion['percent'] === 100 && $completion['core_complete']
                && $ownerProfileProgress && $ownerProfileProgress['percent'] === 100;
            $canSubmitBasicListing = $completion['percent'] >= 50
                && $completion['core_complete'] && $isActive;
        ?>
            <article class="building-card" data-building-card data-building-id="<?= (int) $building['id'] ?>">
                <a class="building-card-cover" href="index.php?page=<?= $isPublic ? 'property' : 'building_form' ?>&amp;id=<?= (int) $building['id'] ?>" aria-label="<?= $isPublic ? 'Lihat' : 'Kelola' ?> <?= e($building['name']) ?>">
                    <?php if (!empty($building['cover_photo'])): ?>
                        <img src="<?= e($building['cover_photo']) ?>" alt="Foto <?= e($building['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="building-card-placeholder" aria-hidden="true">⌂</span>
                    <?php endif; ?>
                    <span class="building-card-location-tag"><?= e($building['gender_policy'] === 'male' ? 'Kos Putra' : ($building['gender_policy'] === 'female' ? 'Kos Putri' : 'Kos Campur')) ?></span>
                    <span class="badge <?= e($building['status']) ?>"><?= e(status_label($building['status'])) ?></span>
                </a>
                <div class="building-card-body">
                    <div class="building-card-heading">
                        <span class="building-card-initial" aria-hidden="true"><?= e($buildingInitial) ?></span>
                        <div>
                            <h3><a href="index.php?page=property&amp;id=<?= (int) $building['id'] ?>"><?= e($building['name']) ?></a></h3>
                        </div>
                    </div>
                    <p class="building-card-address"><?= e($building['address']) ?></p>
                    <div class="building-card-facts">
                        <span><strong><?= (int) $building['room_count'] ?></strong> kamar</span>
                        <?php if ($building['latitude'] !== null && $building['longitude'] !== null): ?>
                            <a class="building-card-location" href="https://www.openstreetmap.org/?mlat=<?= e($building['latitude']) ?>&amp;mlon=<?= e($building['longitude']) ?>#map=17/<?= e($building['latitude']) ?>/<?= e($building['longitude']) ?>" target="_blank" rel="noopener noreferrer">Lihat lokasi peta ↗</a>
                        <?php else: ?>
                            <span class="building-card-no-location">Lokasi belum diatur</span>
                        <?php endif; ?>
                    </div>
                    <div class="owner-listing-progress">
                        <div><strong>Kelengkapan data</strong><span><?= $completion['percent'] ?>%</span></div>
                        <progress max="100" value="<?= $completion['percent'] ?>"><?= $completion['percent'] ?>%</progress>
                        <small class="subline"><?= e($publicationLabels[$building['publication_status']] ?? 'Belum diketahui') ?>
                            · <?= e($goldLabels[$building['gold_verification_status']] ?? 'Status Gold belum diketahui') ?></small>
                    </div>
                    <?php if ($user['role'] === 'owner'): ?>
                        <div class="owner-listing-guidance">
                            <strong>
                                <?= $completion['missing_details']
                                    ? 'Data yang masih perlu dilengkapi'
                                    : 'Semua kelompok data gedung sudah lengkap' ?>
                            </strong>
                            <?php if ($completion['missing_details']): ?>
                                <ul>
                                    <?php foreach ($completion['missing_details'] as $missingDetail): ?>
                                        <li><?= e($missingDetail) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'owner' && $building['listing_review_note']): ?>
                        <p class="alert warning"><?= nl2br(e($building['listing_review_note'])) ?></p>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'owner' && $building['gold_review_note']
                        && $building['gold_verification_status'] === 'rejected'): ?>
                        <p class="alert warning">Catatan Gold: <?= nl2br(e($building['gold_review_note'])) ?></p>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'owner' && in_array(
                        $building['publication_status'],
                        ['draft', 'revision', 'rejected'],
                        true
                    )): ?>
                        <form method="post" class="owner-listing-submit-form">
                            <?= csrf_field() ?><input type="hidden" name="action" value="submit_basic_listing">
                            <input type="hidden" name="building_id" value="<?= (int) $building['id'] ?>">
                            <button class="button primary" type="submit" <?= !$canSubmitBasicListing ? 'disabled' : '' ?>>
                                Ajukan tayang ke Admin
                            </button>
                            <?php if (!$canSubmitBasicListing): ?>
                                <small class="subline">Tombol aktif setelah kelengkapan minimal 50%, data inti lengkap, dan gedung berstatus Aktif.</small>
                                <?php if ($completion['percent'] < 50): ?>
                                    <small class="subline">Kelengkapan saat ini <?= $completion['percent'] ?>%; minimal 50%.</small>
                                <?php endif; ?>
                                <?php foreach ($completion['core_missing_details'] as $coreMissingDetail): ?>
                                    <small class="subline">Data inti: <?= e($coreMissingDetail) ?></small>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!$isActive): ?>
                                <small class="subline">Aktifkan gedung sebelum mengajukan tayang.</small>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'owner' && $isPublic
                        && $building['gold_verification_status'] !== 'verified'
                        && $building['gold_verification_status'] !== 'pending'): ?>
                        <details class="owner-gold-request">
                            <summary>Ajukan verifikasi Gold</summary>
                            <form method="post" enctype="multipart/form-data" class="form-stack">
                                <?= csrf_field() ?><input type="hidden" name="action" value="submit_gold_verification">
                                <input type="hidden" name="building_id" value="<?= (int) $building['id'] ?>">
                                <label class="field"><span>Dokumen kepemilikan atau kewenangan *</span><input type="file" name="evidence_file" accept="application/pdf,image/jpeg,image/png,image/webp" required><small class="field-help">PDF, JPG, PNG, atau WebP, maksimal 5 MB. Dokumen privat dan hanya dapat diakses Owner terkait serta Admin.</small></label>
                                <label class="field"><span>Catatan untuk Admin</span><textarea name="owner_note" rows="2" maxlength="2000"></textarea></label>
                                <?php if (!$goldReady): ?><small class="subline">Verifikasi Gold memerlukan profil Owner dan data gedung 100% lengkap, termasuk alamat, peta, foto gedung, fasilitas, aturan, serta harga dan foto seluruh kamar.</small><?php endif; ?>
                                <button class="button secondary" type="submit" <?= !$goldReady ? 'disabled' : '' ?>>Kirim pengajuan Gold</button>
                            </form>
                        </details>
                    <?php endif; ?>
                    <div class="building-card-actions">
                        <a class="button secondary building-card-edit" href="index.php?page=building_form&amp;id=<?= (int) $building['id'] ?>">Edit gedung</a>
                        <form method="post"<?= $isActive ? ' data-confirm="' . e('Nonaktifkan gedung "' . $building['name'] . '"? Gedung dan kamar di dalamnya tidak lagi ditampilkan sebagai aktif.') . '"' : '' ?>><?= csrf_field() ?><input type="hidden" name="action" value="building_status"><input type="hidden" name="id" value="<?= (int) $building['id'] ?>"><input type="hidden" name="status" value="<?= $isActive ? 'inactive' : 'active' ?>"><button class="button <?= $isActive ? 'danger' : 'primary' ?> building-card-toggle" type="submit"><?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
                    </div>
                    <?php if ($user['role'] === 'admin' && $building['publication_status'] === 'published'): ?>
                        <details class="owner-gold-request admin-listing-suspension">
                            <summary>Tangguhkan publikasi gedung</summary>
                            <form method="post" class="form-stack">
                                <?= csrf_field() ?><input type="hidden" name="action" value="admin_suspend_listing">
                                <input type="hidden" name="building_id" value="<?= (int) $building['id'] ?>">
                                <label class="field"><span>Alasan penangguhan *</span><textarea name="admin_note" rows="2" maxlength="2000" required></textarea></label>
                                <button class="button danger" type="submit">Tangguhkan listing ini</button>
                            </form>
                        </details>
                    <?php elseif ($user['role'] === 'admin' && $building['publication_status'] === 'suspended'): ?>
                        <form method="post" class="owner-listing-submit-form">
                            <?= csrf_field() ?><input type="hidden" name="action" value="admin_restore_listing">
                            <input type="hidden" name="building_id" value="<?= (int) $building['id'] ?>">
                            <button class="button primary" type="submit">Pulihkan publikasi</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="table-scroll building-table-view" data-building-table-view hidden>
        <table data-custom-table-controls>
            <thead><tr><th>Gedung</th><th>Alamat</th><th>Kamar</th><th>Status gedung</th><th>Kelengkapan</th><th>Publikasi / Gold</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($buildings as $building): ?>
                    <?php
                    $isActive = $building['status'] === 'active';
                    $completion = $buildingProgressById[(int) $building['id']];
                    $isPublic = $isActive && $building['publication_status'] === 'published';
                    ?>
                    <tr data-building-table-row data-building-id="<?= (int) $building['id'] ?>">
                        <td><a class="building-name-link" href="index.php?page=<?= $isPublic ? 'property' : 'building_form' ?>&amp;id=<?= (int) $building['id'] ?>"><strong><?= e($building['name']) ?></strong></a></td>
                        <td><?= e($building['address']) ?></td>
                        <td><?= (int) $building['room_count'] ?></td>
                        <td><span class="badge <?= e($building['status']) ?>"><?= e(status_label($building['status'])) ?></span></td>
                        <td><progress max="100" value="<?= $completion['percent'] ?>"><?= $completion['percent'] ?>%</progress><small><?= $completion['percent'] ?>%</small></td>
                        <td><?= e($publicationLabels[$building['publication_status']] ?? 'Belum diketahui') ?><small class="subline"><?= e($goldLabels[$building['gold_verification_status']] ?? 'Status Gold belum diketahui') ?></small></td>
                        <td class="actions">
                            <a class="button secondary compact-button" href="index.php?page=building_form&amp;id=<?= (int) $building['id'] ?>">Edit</a>
                            <form method="post"<?= $isActive ? ' data-confirm="' . e('Nonaktifkan gedung "' . $building['name'] . '"? Gedung dan kamar di dalamnya tidak lagi ditampilkan sebagai aktif.') . '"' : '' ?>><?= csrf_field() ?><input type="hidden" name="action" value="building_status"><input type="hidden" name="id" value="<?= (int) $building['id'] ?>"><input type="hidden" name="status" value="<?= $isActive ? 'inactive' : 'active' ?>"><button class="button <?= $isActive ? 'danger' : 'primary' ?> compact-button" type="submit"><?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
                            <?php if ($user['role'] === 'owner' && in_array($building['publication_status'], ['draft', 'revision', 'rejected'], true)): ?>
                                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="submit_basic_listing"><input type="hidden" name="building_id" value="<?= (int) $building['id'] ?>"><button class="button primary compact-button" type="submit" <?= $completion['percent'] < 50 || !$completion['core_complete'] || !$isActive ? 'disabled' : '' ?>>Ajukan tayang</button></form>
                            <?php endif; ?>
                            <?php if ($user['role'] === 'admin' && $building['publication_status'] === 'published'): ?>
                                <details class="admin-listing-suspension"><summary>Tangguhkan</summary><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="admin_suspend_listing"><input type="hidden" name="building_id" value="<?= (int) $building['id'] ?>"><label class="field"><span>Alasan *</span><textarea name="admin_note" rows="2" maxlength="2000" required></textarea></label><button class="button danger compact-button" type="submit">Simpan</button></form></details>
                            <?php elseif ($user['role'] === 'admin' && $building['publication_status'] === 'suspended'): ?>
                                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="admin_restore_listing"><input type="hidden" name="building_id" value="<?= (int) $building['id'] ?>"><button class="button primary compact-button" type="submit">Pulihkan</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$buildings): ?><tr><td colspan="7" class="no-data">Belum ada gedung kos.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="building-list-empty" data-building-card-empty <?= $buildings ? 'hidden' : '' ?>><?= $buildings ? 'Tidak ada gedung yang cocok dengan pencarian.' : 'Belum ada gedung kos. Tambahkan gedung sebelum mencatat kamar.' ?></p>
</section>
