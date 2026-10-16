<?php
                $stats = [
                    'tenants' => (int) $pdo->query("SELECT COUNT(*) FROM tenants WHERE status='active'")->fetchColumn(),
                    'rooms' => (int) $pdo->query('SELECT COUNT(*) FROM rooms')->fetchColumn(),
                    'occupied' => (int) $pdo->query("SELECT COUNT(DISTINCT room_id) FROM rentals WHERE status='active' AND start_date<=CURDATE() AND (end_date IS NULL OR end_date>=CURDATE())")->fetchColumn(),
                    'outstanding' => (float) $pdo->query("SELECT COALESCE(SUM(i.amount_due - COALESCE(p.paid,0)),0) FROM invoices i LEFT JOIN (SELECT invoice_id,SUM(amount) AS paid FROM payments WHERE status='confirmed' GROUP BY invoice_id) p ON p.invoice_id=i.id WHERE i.status='open'")->fetchColumn(),
                ];
                $recent = rows($pdo, "SELECT i.invoice_number, t.full_name, rm.room_number, b.name AS building_name, i.due_date, i.amount_due, i.status FROM invoices i JOIN rentals r ON r.id=i.rental_id JOIN tenants t ON t.id=r.tenant_id JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id WHERE i.status <> 'void' ORDER BY i.due_date DESC LIMIT 6");
                $occupancy = $stats['rooms'] ? round(($stats['occupied'] / $stats['rooms']) * 100) : 0;
            ?>
                <div class="welcome-row"><div><h2>Halo, <?= e(explode(' ', $user['name'])[0]) ?> 👋</h2><p>Pantau operasional kos Anda hari ini.</p></div><a class="button primary" href="index.php?page=rental_form">＋ Sewa baru</a></div>
                <div class="stats-grid">
                    <article class="stat-card"><div class="stat-icon purple">♙</div><div><span>Pelanggan aktif</span><strong><?= $stats['tenants'] ?></strong><small>terdaftar saat ini</small></div></article>
                    <article class="stat-card"><div class="stat-icon blue">▦</div><div><span>Total kamar</span><strong><?= $stats['rooms'] ?></strong><small><?= $stats['occupied'] ?> kamar terisi</small></div></article>
                    <article class="stat-card"><div class="stat-icon green">◔</div><div><span>Okupansi</span><strong><?= $occupancy ?>%</strong><small>dari seluruh kamar</small></div></article>
                    <article class="stat-card"><div class="stat-icon orange">Rp</div><div><span>Tagihan berjalan</span><strong><?= e(money($stats['outstanding'])) ?></strong><small>belum dibayar</small></div></article>
                </div>
                <div class="panel"><div class="panel-heading"><div><h2>Tagihan terbaru</h2><p>Ringkasan tagihan pelanggan</p></div><a class="text-link" href="index.php?page=invoices">Lihat semua →</a></div>
                    <?php if (!$recent): ?><div class="empty-state"><span>▤</span><strong>Belum ada tagihan</strong><p>Tagihan yang dibuat akan muncul di sini.</p><a class="button secondary" href="index.php?page=invoice_form">Buat tagihan</a></div>
                    <?php else: ?><div class="table-scroll"><table><thead><tr><th>No. tagihan</th><th>Pelanggan / gedung</th><th>Jatuh tempo</th><th>Nominal</th><th>Status</th></tr></thead><tbody><?php foreach ($recent as $row): ?><tr><td class="strong"><?= e($row['invoice_number']) ?></td><td><?= e($row['full_name']) ?><small class="subline"><?= e($row['building_name']) ?> · Kamar <?= e($row['room_number']) ?></small></td><td><?= e(date_id($row['due_date'])) ?></td><td><?= e(money($row['amount_due'])) ?></td><td><span class="badge <?= e($row['status']) ?>"><?= e(status_label($row['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                </div>
            