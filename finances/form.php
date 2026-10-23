<?php
declare(strict_types=1);

$currentYear = (int) date('Y');
$year = filter_var($_GET['year'] ?? $currentYear, FILTER_VALIDATE_INT);
$year = is_int($year) && $year >= 2000 && $year <= $currentYear + 1 ? $year : $currentYear;
$month = filter_var($_GET['month'] ?? (int) date('n'), FILTER_VALIDATE_INT);
$month = is_int($month) && $month >= 1 && $month <= 12 ? $month : (int) date('n');
$range = isset($_GET['range']) && is_string($_GET['range'])
    && in_array($_GET['range'], ['month', 'year', 'week', '24h'], true) ? $_GET['range'] : 'month';
$rangeStart = match ($range) {
    'year' => sprintf('%04d-01-01 00:00:00', $year),
    'week' => date('Y-m-d H:i:s', strtotime('-7 days')),
    '24h' => date('Y-m-d H:i:s', strtotime('-24 hours')),
    default => sprintf('%04d-%02d-01 00:00:00', $year, $month),
};
$rangeEnd = in_array($range, ['month', 'year'], true)
    ? date('Y-m-d H:i:s', strtotime($range === 'year' ? ($year + 1) . '-01-01' : sprintf('%04d-%02d-01', $year, $month) . ' +1 month'))
    : date('Y-m-d H:i:s');
$monthLabels = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$payments = rows(
    $pdo,
    "SELECT p.id,p.amount,p.paid_at,p.payment_method,t.full_name,b.name AS building_name,
            rm.room_number,i.invoice_number
     FROM payments p JOIN invoices i ON i.id=p.invoice_id
     JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id
     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
     WHERE b.owner_user_id=:owner_id AND p.status='confirmed'
       AND p.paid_at>=:range_start AND p.paid_at<:range_end
     ORDER BY p.paid_at DESC,p.id DESC LIMIT 200",
    ['owner_id' => $user['id'], 'range_start' => $rangeStart, 'range_end' => $rangeEnd]
);
$income = (float) one(
    $pdo,
    "SELECT COALESCE(SUM(p.amount),0) AS total
     FROM payments p JOIN invoices i ON i.id=p.invoice_id
     JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
     JOIN kos_buildings b ON b.id=rm.building_id
     WHERE b.owner_user_id=:owner_id AND p.status='confirmed'
       AND p.paid_at>=:range_start AND p.paid_at<:range_end",
    ['owner_id' => $user['id'], 'range_start' => $rangeStart, 'range_end' => $rangeEnd]
)['total'];
$expenseDateColumn = $range === '24h' ? 'created_at' : 'expense_date';
$expenseStart = $range === '24h' ? $rangeStart : substr($rangeStart, 0, 10);
$expenseEnd = $range === '24h'
    ? date('Y-m-d H:i:s')
    : (in_array($range, ['month', 'year'], true)
        ? substr($rangeEnd, 0, 10)
        : date('Y-m-d', strtotime('tomorrow')));
$expenses = rows(
    $pdo,
    "SELECT id,expense_type,category_name,amount,expense_date,notes
     FROM owner_expenses
     WHERE owner_user_id=:owner_id AND {$expenseDateColumn}>=:range_start AND {$expenseDateColumn}<:range_end
     ORDER BY expense_date DESC,id DESC LIMIT 200",
    ['owner_id' => $user['id'], 'range_start' => $expenseStart, 'range_end' => $expenseEnd]
);
$expenseTotal = (float) one(
    $pdo,
    "SELECT COALESCE(SUM(amount),0) AS total FROM owner_expenses
     WHERE owner_user_id=:owner_id AND {$expenseDateColumn}>=:range_start AND {$expenseDateColumn}<:range_end",
    ['owner_id' => $user['id'], 'range_start' => $expenseStart, 'range_end' => $expenseEnd]
)['total'];
$categories = rows(
    $pdo,
    'SELECT id,name FROM owner_expense_categories WHERE owner_user_id=:owner_id ORDER BY name',
    ['owner_id' => $user['id']]
);
$editExpenseId = filter_var($_GET['edit_expense'] ?? 0, FILTER_VALIDATE_INT);
$editExpense = is_int($editExpenseId) && $editExpenseId > 0
    ? one(
        $pdo,
        'SELECT id,category_id,expense_type,category_name,amount,expense_date,notes
         FROM owner_expenses WHERE id=:expense_id AND owner_user_id=:owner_id',
        ['expense_id' => $editExpenseId, 'owner_id' => $user['id']]
    )
    : null;
if (is_int($editExpenseId) && $editExpenseId > 0 && !$editExpense) {
    set_flash('error', 'Pengeluaran tidak ditemukan atau bukan milik akun Anda.');
    redirect('finance');
}
$financeFilterQuery = http_build_query([
    'page' => 'finance',
    'range' => $range,
    'year' => $year,
    'month' => $month,
]);
$installments = rows(
    $pdo,
    "SELECT i.id,i.invoice_number,i.due_date,i.amount_due,t.full_name,b.name AS building_name,
            rm.room_number,COALESCE(p.paid,0) AS paid,
            GREATEST(i.amount_due-COALESCE(p.paid,0),0) AS remaining
     FROM invoices i JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id
     JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
     LEFT JOIN (SELECT invoice_id,SUM(amount) AS paid FROM payments
                WHERE status='confirmed' GROUP BY invoice_id) p ON p.invoice_id=i.id
     WHERE b.owner_user_id=:owner_id AND i.status='open'
       AND GREATEST(i.amount_due-COALESCE(p.paid,0),0)>0
     ORDER BY i.due_date,i.id LIMIT 200",
    ['owner_id' => $user['id']]
);
?>
<section class="page-heading">
    <div><span class="eyebrow">KEUANGAN</span><h2>Pemasukan dan pengeluaran</h2>
        <p>Pantau pemasukan, pengeluaran, dan sisa cicilan kos Anda.</p></div>
</section>
<section class="panel finance-panel">
    <form method="get" class="activity-log-filters" data-auto-submit>
        <input type="hidden" name="page" value="finance">
        <label>Periode
            <select name="range">
                <option value="month" <?= $range === 'month' ? 'selected' : '' ?>>Bulan</option>
                <option value="year" <?= $range === 'year' ? 'selected' : '' ?>>Tahun</option>
                <option value="week" <?= $range === 'week' ? 'selected' : '' ?>>7 hari terakhir</option>
                <option value="24h" <?= $range === '24h' ? 'selected' : '' ?>>24 jam terakhir</option>
            </select>
        </label>
        <label>Tahun
            <select name="year"><?php for ($optionYear = $currentYear; $optionYear >= $currentYear - 5; $optionYear--): ?>
                <option value="<?= $optionYear ?>" <?= $year === $optionYear ? 'selected' : '' ?>><?= $optionYear ?></option>
            <?php endfor; ?></select>
        </label>
        <label>Bulan
            <select name="month"><?php for ($optionMonth = 1; $optionMonth <= 12; $optionMonth++): ?>
                <option value="<?= $optionMonth ?>" <?= $month === $optionMonth ? 'selected' : '' ?>><?= e($monthLabels[$optionMonth - 1]) ?></option>
            <?php endfor; ?></select>
        </label>
    </form>
    <div class="stats-grid finance-stats">
        <article class="stat-card"><div class="stat-icon green">↗</div><div><span>Total Pemasukan</span><strong><?= e(money($income)) ?></strong></div></article>
        <article class="stat-card"><div class="stat-icon orange">↘</div><div><span>Total Pengeluaran</span><strong><?= e(money($expenseTotal)) ?></strong></div></article>
        <article class="stat-card"><div class="stat-icon blue">＝</div><div><span>Pendapatan Bersih</span><strong><?= e(money($income - $expenseTotal)) ?></strong></div></article>
    </div>
</section>
<section class="panel finance-panel">
    <div class="panel-heading"><div><h2>Pemasukan</h2><p>Pembayaran sewa pada periode terpilih.</p></div></div>
    <div class="table-scroll"><table><thead><tr><th>Tanggal</th><th>Penghuni</th><th>Gedung / kamar</th><th>Tagihan</th><th>Nominal</th></tr></thead><tbody>
    <?php foreach ($payments as $payment): ?><tr>
        <td><?= e(date_id($payment['paid_at'])) ?></td><td><?= e($payment['full_name']) ?></td>
        <td><?= e($payment['building_name']) ?> · <?= e($payment['room_number']) ?></td>
        <td><?= e($payment['invoice_number']) ?></td><td><?= e(money($payment['amount'])) ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$payments): ?><tr><td colspan="5" class="no-data">Belum ada pemasukan pada periode ini.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<section class="panel finance-panel">
    <div class="panel-heading"><div><h2>Cicilan dan sisa tagihan</h2><p>Tagihan sewa yang belum lunas, termasuk jumlah yang sudah dibayar dan sisanya.</p></div></div>
    <div class="table-scroll"><table><thead><tr><th>Jatuh tempo</th><th>Penghuni</th><th>Gedung / kamar</th><th>Tagihan</th><th>Total</th><th>Terbayar</th><th>Sisa</th></tr></thead><tbody>
    <?php foreach ($installments as $installment): ?><tr>
        <td><?= e(date_id($installment['due_date'])) ?></td><td><?= e($installment['full_name']) ?></td>
        <td><?= e($installment['building_name']) ?> · <?= e($installment['room_number']) ?></td>
        <td><?= e($installment['invoice_number']) ?></td><td><?= e(money($installment['amount_due'])) ?></td>
        <td><?= e(money($installment['paid'])) ?></td><td><strong><?= e(money($installment['remaining'])) ?></strong></td>
    </tr><?php endforeach; ?>
    <?php if (!$installments): ?><tr><td colspan="7" class="no-data">Tidak ada sisa tagihan sewa.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<section class="panel finance-panel">
    <div class="panel-heading finance-expense-heading">
        <div><h2><?= $editExpense ? 'Edit pengeluaran' : 'Catat pengeluaran' ?></h2><p>Pilih pengeluaran rutin atau masukkan jenis pengeluaran lainnya.</p></div>
        <a class="button secondary finance-settings-link" href="index.php?page=finance_settings" aria-label="Pengaturan jenis pengeluaran rutin" title="Pengaturan pengeluaran">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z"/><path d="m19.4 15 .1.1a1.8 1.8 0 1 1-2.5 2.5l-.1-.1a1.8 1.8 0 0 0-3 .9v.2a1.8 1.8 0 1 1-3.6 0v-.2a1.8 1.8 0 0 0-3-.9l-.1.1a1.8 1.8 0 1 1-2.5-2.5l.1-.1a1.8 1.8 0 0 0-.9-3h-.2a1.8 1.8 0 1 1 0-3.6h.2a1.8 1.8 0 0 0 .9-3l-.1-.1a1.8 1.8 0 1 1 2.5-2.5l.1.1a1.8 1.8 0 0 0 3-.9v-.2a1.8 1.8 0 1 1 3.6 0v.2a1.8 1.8 0 0 0 3 .9l.1-.1a1.8 1.8 0 1 1 2.5 2.5l-.1.1a1.8 1.8 0 0 0 .9 3h.2a1.8 1.8 0 1 1 0 3.6h-.2a1.8 1.8 0 0 0-.9 3Z"/></svg>
            <span>Pengaturan</span>
        </a>
    </div>
    <form method="post" class="form-grid finance-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="<?= $editExpense ? 'update_owner_expense' : 'save_owner_expense' ?>">
        <input type="hidden" name="return_range" value="<?= e($range) ?>">
        <input type="hidden" name="return_year" value="<?= $year ?>">
        <input type="hidden" name="return_month" value="<?= $month ?>">
        <?php if ($editExpense): ?><input type="hidden" name="expense_id" value="<?= (int) $editExpense['id'] ?>"><?php endif; ?>
        <label class="field"><span>Jenis *</span><select name="expense_type" data-expense-type>
            <option value="routine" <?= ($editExpense['expense_type'] ?? 'routine') === 'routine' ? 'selected' : '' ?>>Pengeluaran rutin</option>
            <option value="other" <?= ($editExpense['expense_type'] ?? '') === 'other' ? 'selected' : '' ?>>Pengeluaran lainnya</option>
        </select></label>
        <label class="field" data-expense-routine><span>Pengeluaran rutin *</span><select name="category_id" <?= $categories ? 'required' : 'disabled' ?>>
            <option value="">Pilih jenis</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($editExpense['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?>
        </select></label>
        <label class="field" data-expense-other hidden><span>Jenis pengeluaran *</span><input name="other_category_name" value="<?= e($editExpense && $editExpense['expense_type'] === 'other' ? $editExpense['category_name'] : '') ?>" maxlength="100" disabled placeholder="Contoh: perbaikan atap"></label>
        <label class="field"><span>Nominal (Rp) *</span><input type="text" name="amount" value="<?= e($editExpense ? number_format((float) $editExpense['amount'], 2, ',', '.') : '') ?>" inputmode="decimal" data-money-input data-money-decimals="2" placeholder="Contoh: 500.000" required></label>
        <label class="field"><span>Tanggal *</span><input type="date" name="expense_date" value="<?= e($editExpense['expense_date'] ?? date('Y-m-d')) ?>" required></label>
        <label class="field full-span"><span>Keterangan</span><input name="notes" value="<?= e($editExpense['notes'] ?? '') ?>" maxlength="500" placeholder="Opsional"></label>
        <div class="form-actions full-span">
            <button class="button primary" type="submit"><?= $editExpense ? 'Simpan perubahan' : 'Simpan pengeluaran' ?></button>
            <?php if ($editExpense): ?><a class="button secondary" href="index.php?<?= e($financeFilterQuery) ?>">Batal</a><?php endif; ?>
        </div>
    </form>
    <div class="table-scroll"><table><thead><tr><th>Tanggal</th><th>Jenis</th><th>Nominal</th><th>Keterangan</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach ($expenses as $expense): ?><tr>
        <td><?= e(date_id($expense['expense_date'])) ?></td><td><?= e($expense['category_name']) ?><?= $expense['expense_type'] === 'other' ? ' · lainnya' : '' ?></td>
        <td><?= e(money($expense['amount'])) ?></td><td><?= e($expense['notes'] ?: '—') ?></td>
        <td class="finance-expense-actions">
            <a class="button secondary" href="index.php?<?= e($financeFilterQuery) ?>&amp;edit_expense=<?= (int) $expense['id'] ?>">Edit</a>
            <button class="button danger" type="button" data-dialog-open="delete-owner-expense" data-expense-delete data-expense-id="<?= (int) $expense['id'] ?>">Hapus</button>
        </td>
    </tr><?php endforeach; ?>
    <?php if (!$expenses): ?><tr><td colspan="5" class="no-data">Belum ada pengeluaran pada periode ini.</td></tr><?php endif; ?>
    </tbody></table></div>
    <dialog class="action-dialog" id="delete-owner-expense" aria-labelledby="delete-owner-expense-title">
        <form method="post" class="action-dialog-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete_owner_expense">
            <input type="hidden" name="return_range" value="<?= e($range) ?>">
            <input type="hidden" name="return_year" value="<?= $year ?>">
            <input type="hidden" name="return_month" value="<?= $month ?>">
            <input type="hidden" name="expense_id" value="">
            <button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button>
            <span class="dialog-eyebrow">KEAMANAN KEUANGAN</span><h2 id="delete-owner-expense-title">Hapus pengeluaran?</h2>
            <p>Catatan pengeluaran ini akan dihapus permanen dan tidak dapat dipulihkan.</p>
            <label class="confirmation-check"><input type="checkbox" name="confirm_delete_expense" value="yes" required><span>Saya memahami catatan pengeluaran ini akan dihapus permanen.</span></label>
            <div class="dialog-actions"><button class="button secondary" type="button" data-dialog-close>Batal</button><button class="button danger" type="submit">Hapus pengeluaran</button></div>
        </form>
    </dialog>
</section>
