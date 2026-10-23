<?php
declare(strict_types=1);

function seed_default_owner_expense_categories(PDO $pdo, int $ownerId): void
{
    if ($ownerId < 1) {
        throw new InvalidArgumentException('A valid owner account is required.');
    }
    $statement = $pdo->prepare(
        'INSERT IGNORE INTO owner_expense_categories (owner_user_id,name)
         VALUES (:owner_id,:name)'
    );
    foreach (['Listrik', 'Air', 'Wifi'] as $name) {
        $statement->execute(['owner_id' => $ownerId, 'name' => $name]);
    }
}

function finance_character_count(string $value): int
{
    $count = preg_match_all('/./us', $value);
    if ($count === false) {
        throw new RuntimeException('Teks pengeluaran harus berupa UTF-8 yang valid.');
    }

    return $count;
}

function handle_finance_action(PDO $pdo, ?array $user, string $action): void
{
    if (!in_array($action, [
        'save_owner_expense',
        'save_expense_category',
        'update_expense_category',
        'delete_expense_category',
    ], true)) {
        return;
    }
    if (!$user || $user['role'] !== 'owner') {
        set_flash('error', 'Hanya Owner yang dapat mengelola keuangan.');
        redirect('dashboard');
    }

    if ($action === 'save_expense_category') {
        $name = trim(post_value('category_name'));
        if ($name === '' || finance_character_count($name) > 100) {
            throw new RuntimeException('Nama pengeluaran rutin wajib diisi dan maksimal 100 karakter.');
        }
        if (one(
            $pdo,
            'SELECT id FROM owner_expense_categories WHERE owner_user_id=:owner_id AND name=:name',
            ['owner_id' => $user['id'], 'name' => $name]
        )) {
            throw new RuntimeException('Jenis pengeluaran rutin tersebut sudah terdaftar.');
        }
        $statement = $pdo->prepare(
            'INSERT INTO owner_expense_categories (owner_user_id,name)
             VALUES (:owner_id,:name)'
        );
        $statement->execute(['owner_id' => $user['id'], 'name' => $name]);
        set_flash('success', 'Jenis pengeluaran rutin berhasil ditambahkan.');
        redirect('finance_settings');
    }

    if ($action === 'update_expense_category') {
        $categoryId = (int) post_value('category_id');
        $name = trim(post_value('category_name'));
        if ($categoryId < 1 || $name === '' || finance_character_count($name) > 100) {
            throw new RuntimeException('Pilih jenis pengeluaran rutin dan isi nama maksimal 100 karakter.');
        }
        if (one(
            $pdo,
            'SELECT id FROM owner_expense_categories
             WHERE owner_user_id=:owner_id AND name=:name AND id<>:category_id',
            ['owner_id' => $user['id'], 'name' => $name, 'category_id' => $categoryId]
        )) {
            throw new RuntimeException('Jenis pengeluaran rutin tersebut sudah terdaftar.');
        }
        $statement = $pdo->prepare(
            'UPDATE owner_expense_categories SET name=:name
             WHERE id=:category_id AND owner_user_id=:owner_id'
        );
        $statement->execute([
            'name' => $name,
            'category_id' => $categoryId,
            'owner_id' => $user['id'],
        ]);
        if ($statement->rowCount() === 0 && !one(
            $pdo,
            'SELECT id FROM owner_expense_categories WHERE id=:category_id AND owner_user_id=:owner_id',
            ['category_id' => $categoryId, 'owner_id' => $user['id']]
        )) {
            throw new RuntimeException('Jenis pengeluaran rutin tidak ditemukan.');
        }
        set_flash('success', 'Jenis pengeluaran rutin berhasil diperbarui.');
        redirect('finance_settings');
    }

    if ($action === 'delete_expense_category') {
        $categoryId = (int) post_value('category_id');
        if ($categoryId < 1) {
            throw new RuntimeException('Pilih jenis pengeluaran rutin yang akan dihapus.');
        }
        $statement = $pdo->prepare(
            'DELETE FROM owner_expense_categories WHERE id=:category_id AND owner_user_id=:owner_id'
        );
        $statement->execute(['category_id' => $categoryId, 'owner_id' => $user['id']]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Jenis pengeluaran rutin tidak ditemukan.');
        }
        set_flash('success', 'Jenis pengeluaran rutin berhasil dihapus. Riwayat pengeluaran tetap tersimpan.');
        redirect('finance_settings');
    }

    $type = post_value('expense_type');
    $amount = post_money_value('amount');
    $date = post_value('expense_date');
    $notes = post_value('notes');
    if (!in_array($type, ['routine', 'other'], true)
        || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $amount)
        || (float) $amount <= 0 || (float) $amount > 9999999999.99
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
        || $notes !== '' && finance_character_count($notes) > 500) {
        throw new RuntimeException('Jenis, nominal, tanggal, atau keterangan pengeluaran tidak valid.');
    }
    $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dateValue || $dateValue->format('Y-m-d') !== $date) {
        throw new RuntimeException('Tanggal pengeluaran tidak valid.');
    }

    $categoryId = null;
    if ($type === 'routine') {
        $categoryId = (int) post_value('category_id');
        $category = one(
            $pdo,
            'SELECT id,name FROM owner_expense_categories WHERE id=:id AND owner_user_id=:owner_id',
            ['id' => $categoryId, 'owner_id' => $user['id']]
        );
        if (!$category) {
            throw new RuntimeException('Pilih jenis pengeluaran rutin milik akun Anda.');
        }
        $categoryName = $category['name'];
    } else {
        $categoryName = trim(post_value('other_category_name'));
        if ($categoryName === '' || finance_character_count($categoryName) > 100) {
            throw new RuntimeException('Jenis pengeluaran lainnya wajib diisi dan maksimal 100 karakter.');
        }
    }

    $statement = $pdo->prepare(
        'INSERT INTO owner_expenses
            (owner_user_id,category_id,expense_type,category_name,amount,expense_date,notes)
         VALUES (:owner_id,:category_id,:expense_type,:category_name,:amount,:expense_date,:notes)'
    );
    $statement->execute([
        'owner_id' => $user['id'],
        'category_id' => $categoryId,
        'expense_type' => $type,
        'category_name' => $categoryName,
        'amount' => number_format((float) $amount, 2, '.', ''),
        'expense_date' => $date,
        'notes' => $notes !== '' ? $notes : null,
    ]);
    set_flash('success', 'Pengeluaran berhasil dicatat.');
    redirect('finance');
}
