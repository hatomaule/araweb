<?php
declare(strict_types=1);

function handle_notification_action(PDO $pdo, array $user, string $action): void
{
    if ($action === 'mark_notification_read') {
        notification_mark_read($pdo, (int) post_value('notification_id'));
        redirect('notifications');
    }
    if ($action === 'mark_all_notifications_read') {
        notification_mark_all_read($pdo);
        redirect('notifications');
    }
}
