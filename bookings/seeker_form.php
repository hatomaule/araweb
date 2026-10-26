<?php
$roomId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$roomsToBook = rows($pdo, "SELECT rm.id, rm.room_number, rm.room_type, rm.monthly_rate,
        b.name AS building_name
    FROM rooms rm JOIN kos_buildings b ON b.id=rm.building_id
    WHERE rm.status='available' AND b.status='active'
      AND (b.owner_user_id IS NULL OR b.publication_status='published')
    AND NOT EXISTS (SELECT 1 FROM rentals r WHERE r.room_id=rm.id
        AND ((r.status='active' AND r.start_date<=CURDATE()
              AND (r.end_date IS NULL OR r.end_date>=CURDATE()))
          OR (r.status='upcoming' AND (r.end_date IS NULL OR r.end_date>=CURDATE())
              AND (r.booking_expires_at IS NULL OR r.booking_expires_at>NOW()
                   OR EXISTS (SELECT 1 FROM invoices i JOIN payments p ON p.invoice_id=i.id
                              WHERE i.rental_id=r.id AND p.status='pending')))))
    ORDER BY rm.room_number");
?>
<div class="form-panel"><div class="panel-heading"><div><h2>Pesan kamar kos</h2><p>Pilih masa sewa. Pemilik kos akan meninjau permintaan sebelum pembayaran.</p></div><a class="text-link" href="index.php?page=my_bookings">← Pesanan saya</a></div>
    <?php if (!$roomsToBook): ?><div class="empty-state"><span>▦</span><strong>Belum ada kamar yang dapat dipesan</strong><p>Silakan periksa kembali nanti.</p></div>
    <?php else: ?><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="create_customer_booking">
        <label class="field full-span"><span>Kamar *</span><select name="room_id" required><option value="">Pilih gedung, kamar dan tipe</option><?php foreach ($roomsToBook as $room): ?><option value="<?= (int) $room['id'] ?>" data-rate="<?= e($room['monthly_rate']) ?>" <?= (int) $room['id'] === $roomId ? 'selected' : '' ?>><?= e($room['building_name']) ?> · Kamar <?= e($room['room_number']) ?> · <?= e($room['room_type']) ?> · <?= e(money($room['monthly_rate'])) ?>/bulan</option><?php endforeach; ?></select></label>
        <?php field('Mulai sewa *', 'start_date', date('Y-m-d'), 'date', ['required' => 'required', 'min' => date('Y-m-d')]); ?>
        <label class="field"><span>Lama sewa *</span><select name="months" required><?php for ($month = 1; $month <= 12; $month++): ?><option value="<?= $month ?>"><?= $month ?> bulan</option><?php endfor; ?></select></label>
        <div class="booking-terms full-span"><strong>Alur pemesanan</strong><p>Pemilik kos akan menyetujui atau menolak permintaan. Setelah disetujui, Anda dapat mentransfer tagihan ke rekening kos dan mengunggah bukti pembayaran.</p></div>
        <div class="form-actions full-span"><button class="button primary" type="submit">Buat booking dan tagihan</button></div>
    </form><?php endif; ?>
</div>
