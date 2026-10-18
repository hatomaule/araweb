<?php
declare(strict_types=1);

function handle_chat_action(PDO $pdo, array $user, string $action): void
{
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
        redirect('chat&id=' . $conversationId);
    }
}
