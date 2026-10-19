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
    if ($action === 'archive_chat_conversation' || $action === 'restore_chat_conversation') {
        if (($user['role'] ?? null) !== 'admin') {
            throw new ChatAccessDeniedException('Only admins may archive or restore conversations.');
        }
        $conversationId = (int) post_value('conversation_id');
        chat_archive_conversation($pdo, $user, $conversationId, $action === 'archive_chat_conversation');
        set_flash('success', $action === 'archive_chat_conversation'
            ? 'Percakapan diarsipkan dan dapat dipulihkan oleh admin.'
            : 'Percakapan berhasil dipulihkan.');
        redirect('admin_chat&id=' . $conversationId . '&status='
            . ($action === 'archive_chat_conversation' ? 'archived' : 'active'));
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
        redirect('chat&id=' . $conversationId);
    }
}
