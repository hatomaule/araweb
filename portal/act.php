<?php
declare(strict_types=1);

function handle_portal_action(PDO $pdo, array $user, string $action): void
{
if ($action === 'submit_review') {
            if ($user['role'] !== 'seeker') {
                set_flash('error', 'Ulasan hanya dapat dikirim oleh User.');
                redirect('home');
            }
            $buildingId = (int) post_value('building_id');
            $rating = (int) post_value('rating');
            $reviewText = post_value('review_text');
            $tenant = one($pdo, 'SELECT id FROM tenants WHERE user_id=:id', ['id' => $user['id']]);
            if (!$tenant || $rating < 1 || $rating > 5 || $reviewText === '' || strlen($reviewText) > 3000) {
                set_flash('error', 'Pilih nilai 1–5 dan tulis ulasan maksimal 3.000 karakter.');
                redirect('property&id=' . $buildingId . '#review');
            }
            $eligible = one(
                $pdo,
                "SELECT r.id FROM rentals r JOIN rooms rm ON rm.id=r.room_id
                 JOIN kos_buildings b ON b.id=rm.building_id
                 WHERE r.tenant_id=:tenant_id AND b.id=:building_id
                   AND r.status='ended' AND (r.end_date IS NULL OR r.end_date<CURDATE())
                 LIMIT 1",
                ['tenant_id' => $tenant['id'], 'building_id' => $buildingId]
            );
            if (!$eligible) {
                set_flash('error', 'Ulasan hanya bisa diberikan setelah masa sewa berakhir.');
                redirect('property&id=' . $buildingId . '#review');
            }
            $review = $pdo->prepare(
                'INSERT INTO property_reviews (building_id,tenant_id,rental_id,rating,review_text)
                 VALUES (:building_id,:tenant_id,:rental_id,:rating,:review_text)
                 ON DUPLICATE KEY UPDATE rating=VALUES(rating),review_text=VALUES(review_text),status=\'published\''
            );
            $review->execute([
                'building_id' => $buildingId, 'tenant_id' => $tenant['id'],
                'rental_id' => $eligible['id'], 'rating' => $rating, 'review_text' => $reviewText,
            ]);
            set_flash('success', 'Ulasan berhasil diterbitkan.');
            redirect('property&id=' . $buildingId . '#review');
        }

        if (in_array($action, ['operator_review_reply', 'operator_review_visibility'], true)) {
            $reviewId = (int) post_value('review_id');
            if ($user['role'] !== 'operator' || $reviewId < 1) {
                set_flash('error', 'Hanya pemilik kos yang dapat mengelola ulasan.');
                redirect('dashboard');
            }
            $reviewScope = $pdo->prepare(
                'SELECT pr.id,b.id AS building_id
                 FROM property_reviews pr JOIN kos_buildings b ON b.id=pr.building_id
                 WHERE pr.id=:id AND b.owner_user_id=:owner_id'
            );
            $reviewScope->execute(['id' => $reviewId, 'owner_id' => $user['id']]);
            $review = $reviewScope->fetch();
            if (!$review) {
                set_flash('error', 'Ulasan tidak ditemukan pada properti Anda.');
                redirect('dashboard');
            }

            if ($action === 'operator_review_reply') {
                $reply = post_value('operator_reply');
                if ($reply === '' || strlen($reply) > 3000) {
                    set_flash('error', 'Balasan ulasan wajib diisi dan maksimal 3.000 karakter.');
                    redirect('dashboard');
                }
                $updateReply = $pdo->prepare(
                    'UPDATE property_reviews pr
                     JOIN kos_buildings b ON b.id=pr.building_id
                     SET pr.operator_reply=:reply,pr.operator_replied_at=NOW()
                     WHERE pr.id=:id AND b.owner_user_id=:owner_id'
                );
                $updateReply->execute([
                    'reply' => $reply, 'id' => $reviewId, 'owner_id' => $user['id'],
                ]);
                set_flash('success', 'Balasan ulasan berhasil diterbitkan.');
            } else {
                $visibility = post_value('review_status');
                if (post_value('confirm_review_action') !== 'yes'
                    || !in_array($visibility, ['published', 'hidden'], true)) {
                    set_flash('error', 'Konfirmasi dan status moderasi ulasan wajib dipilih.');
                    redirect('dashboard');
                }
                $updateVisibility = $pdo->prepare(
                    'UPDATE property_reviews pr
                     JOIN kos_buildings b ON b.id=pr.building_id
                     SET pr.status=:status
                     WHERE pr.id=:id AND b.owner_user_id=:owner_id'
                );
                $updateVisibility->execute([
                    'status' => $visibility, 'id' => $reviewId, 'owner_id' => $user['id'],
                ]);
                set_flash('success', $visibility === 'hidden' ? 'Ulasan disembunyikan.' : 'Ulasan diterbitkan kembali.');
            }
            redirect('dashboard');
        }

        
}
