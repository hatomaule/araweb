<?php
declare(strict_types=1);

function chat_notify_message_recipients(PDO $pdo, array $user, array $conversation, int $conversationId): void
{
    if ($conversation['conversation_type'] === 'support') {
        $recipientIds = $user['role'] === 'admin'
            ? [(int) $conversation['requester_user_id']]
            : array_map('intval', array_column(
                rows($pdo, "SELECT id FROM users WHERE role='admin' AND status='active'"),
                'id'
            ));
        foreach ($recipientIds as $recipientId) {
            notification_create(
                $pdo,
                $recipientId,
                'chat.message',
                'Pesan bantuan baru',
                $user['name'] . ' mengirim pesan ke dukungan KosKosan.',
                'index.php?page=' . ($user['role'] === 'admin' ? 'chat' : 'admin_chat')
                    . '&id=' . $conversationId
            );
        }
        return;
    }

    $recipientId = $user['role'] === 'seeker'
        ? (int) one($pdo, 'SELECT owner_user_id FROM kos_buildings WHERE id=:id', ['id' => $conversation['building_id']])['owner_user_id']
        : (int) $conversation['seeker_user_id'];
    notification_create(
        $pdo,
        $recipientId,
        'chat.message',
        'Pesan chat baru',
        $user['name'] . ' mengirim pesan di ' . $conversation['building_name'] . '.',
        'index.php?page=chat&id=' . $conversationId
    );
}

function handle_chat_action(PDO $pdo, array $user, string $action): void
{
    if (in_array($action, [
        'archive_chat_conversation',
        'restore_chat_conversation',
        'trash_chat_conversation',
        'restore_trashed_chat_conversation',
    ], true)) {
        $conversationId = (int) post_value('conversation_id');
        if ($action === 'archive_chat_conversation') {
            chat_archive_conversation($pdo, $user, $conversationId, true);
            $folder = 'archive';
            $message = 'Percakapan dipindahkan ke Arsip.';
        } elseif ($action === 'restore_chat_conversation') {
            chat_archive_conversation($pdo, $user, $conversationId, false);
            $folder = 'inbox';
            $message = 'Percakapan dipulihkan ke kotak masuk.';
        } elseif ($action === 'trash_chat_conversation') {
            chat_trash_conversation($pdo, $user, $conversationId);
            $folder = 'trash';
            $message = 'Percakapan dipindahkan ke Sampah dan akan dihapus permanen setelah 30 hari.';
        } else {
            chat_restore_trashed_conversation($pdo, $user, $conversationId);
            $folder = 'inbox';
            $message = 'Percakapan dipulihkan dari Sampah.';
        }
        set_flash('success', $message);
        redirect($user['role'] === 'admin'
            ? 'admin_chat&id=' . $conversationId . '&status=' . ($folder === 'inbox' ? 'active' : $folder)
            : 'chat&id=' . $conversationId . '&folder=' . $folder);
    }

    if ($action === 'empty_chat_trash') {
        chat_empty_trash($pdo, $user);
        set_flash('success', 'Sampah Anda dikosongkan secara permanen; salinan peserta lain tetap terjaga.');
        redirect($user['role'] === 'admin' ? 'admin_chat&status=trash' : 'chat&folder=trash');
    }

    if ($action === 'start_support_conversation') {
        $conversationId = chat_start_support_conversation($pdo, $user);
        redirect('chat&id=' . $conversationId);
    }

    if ($action === 'start_chat_conversation') {
        $buildingId = (int) post_value('building_id');
        $roomId = (int) post_value('room_id');
        $conversationId = chat_start_conversation(
            $pdo,
            $user,
            $buildingId,
            $roomId > 0 ? $roomId : null,
            post_value('message')
        );
        $conversation = chat_get_conversation($pdo, $user, $conversationId);
        $operatorId = (int) one(
            $pdo,
            'SELECT owner_user_id FROM kos_buildings WHERE id=:id',
            ['id' => $conversation['building_id']]
        )['owner_user_id'];
        notification_create(
            $pdo,
            $operatorId,
            'chat.message',
            'Pesan baru',
            $user['name'] . ' memulai percakapan tentang ' . $conversation['building_name'] . '.',
            'index.php?page=chat&id=' . $conversationId
        );
        redirect('chat&id=' . $conversationId);
    }

    if ($action === 'send_chat_message') {
        $conversationId = (int) post_value('conversation_id');
        chat_send_message($pdo, $user, $conversationId, post_value('message'));
        $conversation = chat_get_conversation($pdo, $user, $conversationId);
        chat_notify_message_recipients($pdo, $user, $conversation, $conversationId);
        redirect($user['role'] === 'admin'
            ? 'admin_chat&id=' . $conversationId
            : 'chat&id=' . $conversationId);
    }
}
