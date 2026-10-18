<?php
declare(strict_types=1);

$notifications = notification_list($pdo, 100);
$unreadNotifications = notification_unread_count($pdo);
?>
<div class="welcome-row"><div><h2>Notifikasi</h2><p><?= $unreadNotifications ?> notifikasi belum dibaca.</p></div><?php if ($unreadNotifications): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mark_all_notifications_read"><button class="button secondary" type="submit">Tandai semua dibaca</button></form><?php endif; ?></div>
<section class="panel notification-list" aria-label="Daftar notifikasi">
    <?php foreach ($notifications as $notification): ?>
        <article class="notification-item <?= $notification['read_at'] ? 'read' : 'unread' ?>">
            <span class="notification-dot" aria-hidden="true"></span>
            <div><strong><?= e($notification['title']) ?></strong><p><?= e($notification['body']) ?></p><small><?= e(date_id($notification['created_at'])) ?></small></div>
            <div class="notification-actions">
                <?php if ($notification['link']): ?><a class="button secondary compact-button" href="<?= e($notification['link']) ?>">Buka</a><?php endif; ?>
                <?php if (!$notification['read_at']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mark_notification_read"><input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>"><button class="text-button" type="submit">Tandai dibaca</button></form><?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$notifications): ?><p class="chat-empty">Belum ada notifikasi.</p><?php endif; ?>
</section>
