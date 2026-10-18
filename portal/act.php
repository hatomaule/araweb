<?php
declare(strict_types=1);

function handle_portal_action(PDO $pdo, array $user, string $action): void
{
        if ($action === 'toggle_saved_property') {
            $buildingId = (int) post_value('building_id');
            if ($user['role'] !== 'seeker' || $buildingId < 1
                || !one($pdo, "SELECT id FROM kos_buildings WHERE id=:id AND status='active'", ['id' => $buildingId])) {
                set_flash('error', 'Kos ini tidak dapat disimpan dengan akun saat ini.');
                redirect('home');
            }
            $saved = one(
                $pdo,
                "SELECT id FROM property_user_actions
                 WHERE building_id=:building_id AND user_id=:user_id AND action_type='saved' LIMIT 1",
                ['building_id' => $buildingId, 'user_id' => $user['id']]
            );
            if ($saved) {
                $pdo->prepare("DELETE FROM property_user_actions WHERE id=:id AND user_id=:user_id AND action_type='saved'")
                    ->execute(['id' => $saved['id'], 'user_id' => $user['id']]);
                set_flash('success', 'Kos dihapus dari daftar tersimpan.');
            } else {
                $pdo->prepare(
                    "INSERT INTO property_user_actions (building_id,user_id,action_type)
                     VALUES (:building_id,:user_id,'saved')"
                )->execute(['building_id' => $buildingId, 'user_id' => $user['id']]);
                set_flash('success', 'Kos berhasil disimpan.');
            }
            redirect('property&id=' . $buildingId);
        }

        if ($action === 'report_property') {
            $buildingId = (int) post_value('building_id');
            $reason = post_value('report_reason');
            $details = post_value('report_details');
            if ($user['role'] !== 'seeker' || $buildingId < 1
                || !in_array($reason, ['misleading', 'fake', 'inappropriate', 'other'], true)
                || strlen($details) > 2000
                || !one($pdo, "SELECT id FROM kos_buildings WHERE id=:id AND status='active'", ['id' => $buildingId])) {
                set_flash('error', 'Laporan kos tidak valid.');
                redirect('home');
            }
            $pdo->prepare(
                "INSERT INTO property_user_actions
                    (building_id,user_id,action_type,report_reason,report_details,status)
                 VALUES (:building_id,:user_id,'reported',:reason,:details,'open')
                 ON DUPLICATE KEY UPDATE report_reason=VALUES(report_reason),
                    report_details=VALUES(report_details),status='open',updated_at=CURRENT_TIMESTAMP"
            )->execute([
                'building_id' => $buildingId, 'user_id' => $user['id'],
                'reason' => $reason, 'details' => $details !== '' ? $details : null,
            ]);
            set_flash('success', 'Laporan kos berhasil dikirim kepada admin.');
            redirect('property&id=' . $buildingId);
        }

if ($action === 'submit_review') {
            if ($user['role'] !== 'seeker') {
                set_flash('error', 'Ulasan hanya dapat dikirim oleh User.');
                redirect('home');
            }
            $buildingId = (int) post_value('building_id');
            $rating = (int) post_value('rating');
            $reviewText = post_value('review_text');
            $tenant = one($pdo, 'SELECT id FROM tenants WHERE user_id=:id', ['id' => $user['id']]);
            if ($buildingId < 1 || $rating < 1 || $rating > 5
                || $reviewText === '' || strlen($reviewText) > 3000
                || !one($pdo, "SELECT id FROM kos_buildings WHERE id=:id AND status='active'", ['id' => $buildingId])) {
                set_flash('error', 'Pilih nilai 1–5 dan tulis ulasan maksimal 3.000 karakter.');
                redirect('property&id=' . $buildingId . '#review');
            }
            $existingReview = one(
                $pdo,
                'SELECT pr.id FROM property_reviews pr
                 LEFT JOIN tenants t ON t.id=pr.tenant_id
                 WHERE pr.building_id=:building_id
                   AND (pr.reviewer_user_id=:reviewer_id OR t.user_id=:tenant_user_id)
                 LIMIT 1',
                ['building_id' => $buildingId, 'reviewer_id' => $user['id'], 'tenant_user_id' => $user['id']]
            );
            if ($existingReview) {
                $review = $pdo->prepare(
                    "UPDATE property_reviews
                     SET reviewer_user_id=:reviewer_id,rating=:rating,review_text=:review_text,status='published'
                     WHERE id=:id"
                );
                $review->execute([
                    'reviewer_id' => $user['id'], 'rating' => $rating,
                    'review_text' => $reviewText, 'id' => $existingReview['id'],
                ]);
            } else {
                $review = $pdo->prepare(
                    'INSERT INTO property_reviews
                        (building_id,reviewer_user_id,tenant_id,rental_id,rating,review_text)
                     VALUES (:building_id,:reviewer_id,:tenant_id,NULL,:rating,:review_text)'
                );
                $review->execute([
                    'building_id' => $buildingId,
                    'reviewer_id' => $user['id'],
                    'tenant_id' => $tenant['id'] ?? null,
                    'rating' => $rating,
                    'review_text' => $reviewText,
                ]);
            }
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
