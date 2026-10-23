<?php
declare(strict_types=1);

function handle_finance_action(PDO $pdo, ?array $user, string $action): void
{
    if (!in_array($action, ['save_owner_expense', 'save_expense_category'], true)) {
        return;
    }
    if (!$user || $user['role'] !== 'owner') {
        set_flash('error', 'Hanya Owner yang dapat mengelola keuangan.');
        redirect('dashboard');
    }

    if ($action === 'save_expense_category') {
        $name = trim(post_value('category_name'));
        if ($name === '' || mb_strlen($name) > 100) {
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
        redirect('finance');
    }

    $type = post_value('expense_type');
    $amount = post_value('amount');
    $date = post_value('expense_date');
    $notes = post_value('notes');
    if (!in_array($type, ['routine', 'other'], true)
        || !is_numeric($amount) || (float) $amount <= 0 || (float) $amount > 9999999999.99
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
        || $notes !== '' && mb_strlen($notes) > 500) {
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
        if ($categoryName === '' || mb_strlen($categoryName) > 100) {
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
        'amount' => $amount,
        'expense_date' => $date,
        'notes' => $notes !== '' ? $notes : null,
    ]);
    set_flash('success', 'Pengeluaran berhasil dicatat.');
    redirect('finance');
}
