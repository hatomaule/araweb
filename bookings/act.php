<?php
declare(strict_types=1);

function handle_operator_action(PDO $pdo, array $user, string $action): bool
{
    if ($action !== 'decide_booking') {
        return false;
    }
    if ($user['role'] !== 'operator' && $user['role'] !== 'admin') {
        set_flash('error', 'Hanya pemilik kos yang dapat memutuskan booking.');
        redirect('home');
    }

    $rentalId = (int) post_value('rental_id');
    $decision = post_value('decision');
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        set_flash('error', 'Keputusan booking tidak valid.');
        redirect('operator_bookings');
    }
    $booking = one(
        $pdo,
        "SELECT r.id, r.status, r.booking_status, i.id AS invoice_id
         FROM rentals r JOIN rooms rm ON rm.id=r.room_id
         JOIN kos_buildings b ON b.id=rm.building_id
         LEFT JOIN invoices i ON i.rental_id=r.id
         WHERE r.id=:rental_id AND r.booking_status='pending'
           AND r.status='upcoming'
           AND (:is_admin=1 OR b.owner_user_id=:owner_id)",
        ['rental_id' => $rentalId, 'is_admin' => $user['role'] === 'admin' ? 1 : 0, 'owner_id' => $user['id']]
    );
    if (!$booking) {
        set_flash('error', 'Booking tidak ditemukan atau bukan milik kos yang Anda kelola.');
        redirect('operator_bookings');
    }
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare(
            'UPDATE rentals SET booking_status=:decision, booking_decided_by=:decider,
             booking_decided_at=NOW(), booking_decision_note=:note,
             booking_expires_at=' . ($decision === 'approved' ? 'DATE_ADD(NOW(), INTERVAL 24 HOUR)' : 'NULL') . ',
             status=:rental_status WHERE id=:id AND booking_status=\'pending\''
        );
        $update->execute([
            'decision' => $decision, 'decider' => $user['id'],
            'note' => post_value('decision_note') ?: null,
            'rental_status' => $decision === 'rejected' ? 'cancelled' : 'upcoming',
            'id' => $rentalId,
        ]);
        if ($decision === 'rejected' && $booking['invoice_id']) {
            $voidInvoice = $pdo->prepare("UPDATE invoices SET status='void' WHERE id=:id AND status='open'");
            $voidInvoice->execute(['id' => $booking['invoice_id']]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    set_flash('success', $decision === 'approved'
        ? 'Booking disetujui. User dapat menyelesaikan pembayaran dalam 24 jam.'
        : 'Booking ditolak dan tagihan dibatalkan.');
    redirect('operator_bookings');
}

function handle_customer_booking_action(PDO $pdo, array $user, string $action): void
{
if ($action === 'create_customer_booking') {
            if ($user['role'] !== 'seeker') {
                set_flash('error', 'Hanya akun pelanggan yang dapat melakukan booking kamar.');
                redirect('dashboard');
            }
            $tenant = one($pdo, "SELECT id FROM tenants WHERE user_id=:user_id AND status='active'", ['user_id' => $user['id']]);
            $roomId = (int) post_value('room_id');
            $start = post_value('start_date');
            $months = (int) post_value('months');
            $parsedStart = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
            if (!$tenant || !$roomId || !$parsedStart || $parsedStart->format('Y-m-d') !== $start
                || $start < date('Y-m-d') || $months < 1 || $months > 12) {
                set_flash('error', 'Pilih kamar, tanggal mulai yang valid, dan lama sewa 1–12 bulan.');
                redirect('booking_form');
            }
            $pdo->beginTransaction();
            try {
                $roomCheck = $pdo->prepare(
                    "SELECT rm.* FROM rooms rm JOIN kos_buildings b ON b.id=rm.building_id
                     WHERE rm.id=:id AND rm.status='available' AND b.status='active' FOR UPDATE"
                );
                $roomCheck->execute(['id' => $roomId]);
                $room = $roomCheck->fetch();
                if (!$room) {
                    $pdo->rollBack();
                    set_flash('error', 'Maaf, kamar tersebut sudah tidak tersedia.');
                    redirect('home');
                }
                if ((float) $room['monthly_rate'] <= 0) {
                    $pdo->rollBack();
                    set_flash('error', 'Kamar ini belum memiliki tarif sewa yang dapat dibayar online.');
                    redirect('home');
                }

                $expireOld = $pdo->prepare(
                    "UPDATE rentals SET status='cancelled'
                     WHERE room_id=:room_id AND status='upcoming'
                       AND booking_expires_at IS NOT NULL AND booking_expires_at<=NOW()
                       AND NOT EXISTS (
                           SELECT 1 FROM invoices i JOIN payments p ON p.invoice_id=i.id
                           WHERE i.rental_id=rentals.id AND p.status='pending'
                       )"
                );
                $expireOld->execute(['room_id' => $roomId]);
                $overlap = $pdo->prepare(
                    "SELECT COUNT(*) FROM rentals
                     WHERE room_id=:room_id AND status IN ('active','upcoming')
                       AND (end_date IS NULL OR end_date>=:start_date)
                       AND start_date<=:end_date"
                );
                $targetMonth = $parsedStart->modify('first day of this month')->modify('+' . $months . ' months');
                $rentalEnd = $targetMonth->setDate(
                    (int) $targetMonth->format('Y'),
                    (int) $targetMonth->format('m'),
                    min((int) $parsedStart->format('d'), (int) $targetMonth->format('t'))
                )->modify('-1 day')->format('Y-m-d');
                $overlap->execute([
                    'room_id' => $roomId, 'start_date' => $start,
                    'end_date' => $rentalEnd,
                ]);
                if ((int) $overlap->fetchColumn() > 0) {
                    $pdo->rollBack();
                    set_flash('error', 'Kamar baru saja dibooking orang lain atau tanggal sewanya bertabrakan.');
                    redirect('home');
                }

                $nextMonth = $parsedStart->modify('first day of this month')->modify('+1 month');
                $firstPeriodEnd = $nextMonth->setDate(
                    (int) $nextMonth->format('Y'),
                    (int) $nextMonth->format('m'),
                    min((int) $parsedStart->format('d'), (int) $nextMonth->format('t'))
                )->modify('-1 day')->format('Y-m-d');
                $rental = $pdo->prepare(
                    "INSERT INTO rentals (tenant_id, room_id, start_date, end_date, monthly_rate,
                     deposit_amount, billing_day, status, notes, created_by, booking_expires_at, booking_status)
                     VALUES (:tenant_id, :room_id, :start_date, :end_date, :monthly_rate, 0,
                     :billing_day, 'upcoming', 'Menunggu persetujuan pemilik kos.', :created_by, NULL, 'pending')"
                );
                $rental->execute([
                    'tenant_id' => $tenant['id'], 'room_id' => $roomId,
                    'start_date' => $start, 'end_date' => $rentalEnd,
                    'billing_day' => min((int) $parsedStart->format('d'), 28),
                    'monthly_rate' => $room['monthly_rate'],
                    'created_by' => $user['id'],
                ]);
                $rentalId = (int) $pdo->lastInsertId();
                $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $invoice = $pdo->prepare(
                    "INSERT INTO invoices (rental_id, invoice_number, period_start, period_end, due_date, amount_due, status, notes)
                     VALUES (:rental_id, :invoice_number, :period_start, :period_end, :due_date, :amount_due, 'open', 'Tagihan bulan pertama untuk booking online.')"
                );
                $invoice->execute([
                    'rental_id' => $rentalId, 'invoice_number' => $invoiceNumber,
                    'period_start' => $start, 'period_end' => $firstPeriodEnd,
                    'due_date' => date('Y-m-d', strtotime('+1 day')),
                    'amount_due' => $room['monthly_rate'],
                ]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            set_flash('success', 'Permintaan booking dikirim. Pemilik kos akan meninjau permintaan sebelum pembayaran.');
            redirect('my_bookings');
        }

        
}
