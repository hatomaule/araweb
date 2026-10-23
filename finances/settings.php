<?php
declare(strict_types=1);

$categories = rows(
    $pdo,
    'SELECT id,name FROM owner_expense_categories WHERE owner_user_id=:owner_id ORDER BY name',
    ['owner_id' => $user['id']]
);
?>
<section class="page-heading finance-settings-heading">
    <div><span class="eyebrow">KEUANGAN</span><h2>Pengaturan pengeluaran rutin</h2>
        <p>Kelola pilihan yang muncul saat mencatat pengeluaran rutin. Riwayat pengeluaran tetap tersimpan meski nama kategori diubah atau dihapus.</p></div>
    <a class="button secondary" href="index.php?page=finance">← Kembali ke Keuangan</a>
</section>
<section class="panel finance-panel">
    <div class="panel-heading"><div><h2>Tambah jenis pengeluaran rutin</h2><p>Contoh: Internet, kebersihan, atau iuran lingkungan.</p></div></div>
    <form method="post" class="finance-category-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_expense_category">
        <label class="field"><span>Nama jenis pengeluaran</span><input name="category_name" maxlength="100" required placeholder="Contoh: Internet"></label>
        <button class="button primary" type="submit">Tambah jenis</button>
    </form>
</section>
<section class="panel finance-panel">
    <div class="panel-heading"><div><h2>Jenis pengeluaran rutin</h2><p><?= count($categories) ?> jenis tersedia di dropdown Catat Pengeluaran.</p></div></div>
    <div class="finance-category-list">
        <?php foreach ($categories as $category): ?>
            <div class="finance-category-item">
                <form method="post" class="finance-category-edit">
                    <?= csrf_field() ?><input type="hidden" name="action" value="update_expense_category">
                    <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                    <label class="field"><span>Nama jenis</span><input name="category_name" value="<?= e($category['name']) ?>" maxlength="100" required></label>
                    <button class="button secondary" type="submit">Simpan</button>
                </form>
                <form method="post" data-confirm="Hapus jenis rutin ini dari pilihan? Riwayat pengeluaran yang sudah dicatat akan tetap tersimpan.">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete_expense_category">
                    <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                    <button class="button danger" type="submit">Hapus</button>
                </form>
            </div>
        <?php endforeach; ?>
        <?php if (!$categories): ?><p class="no-data">Belum ada jenis pengeluaran rutin.</p><?php endif; ?>
    </div>
</section>
