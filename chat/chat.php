<?php
declare(strict_types=1);

final class ChatAccessDeniedException extends RuntimeException
{
}

final class ChatNotFoundException extends RuntimeException
{
}

final class ChatValidationException extends InvalidArgumentException
{
}

function chat_actor_id(array $user): int
{
    $userId = (int) ($user['id'] ?? 0);
    if ($userId < 1 || !in_array($user['role'] ?? null, ['seeker', 'operator'], true)) {
        throw new ChatAccessDeniedException('Chat access is not permitted.');
    }

    return $userId;
}

function chat_validate_message(string $message): string
{
    $message = trim($message);
    if ($message === '' || preg_match('//u', $message) !== 1) {
        throw new ChatValidationException('Message must contain valid, non-empty UTF-8 text.');
    }

    $characterCount = preg_match_all('/./us', $message);
    if ($characterCount === false || $characterCount > 4000) {
        throw new ChatValidationException('Message must not exceed 4,000 characters.');
    }

    return $message;
}

function chat_transaction(PDO $pdo, callable $operation): mixed
{
    static $savepointNumber = 0;
    $ownsTransaction = !$pdo->inTransaction();
    $savepoint = 'chat_sp_' . (++$savepointNumber);

    if ($ownsTransaction) {
        $pdo->beginTransaction();
    } else {
        $pdo->exec('SAVEPOINT ' . $savepoint);
    }

    try {
        $result = $operation();
        if ($ownsTransaction) {
            $pdo->commit();
        } else {
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        }

        return $result;
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        } elseif ($pdo->inTransaction()) {
            $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        }

        throw $exception;
    }
}

function chat_participant_conversation(PDO $pdo, array $user, int $conversationId, bool $lock = false): array
{
    $userId = chat_actor_id($user);
    if ($conversationId < 1) {
        throw new ChatNotFoundException('Conversation not found.');
    }

    $statement = $pdo->prepare(
        'SELECT c.id, c.seeker_user_id, c.building_id, c.room_id, b.owner_user_id
         FROM chat_conversations c
         JOIN kos_buildings b ON b.id = c.building_id
         WHERE c.id = :conversation_id' . ($lock ? ' FOR UPDATE' : '')
    );
    $statement->execute(['conversation_id' => $conversationId]);
    $conversation = $statement->fetch();

    if (!$conversation) {
        throw new ChatNotFoundException('Conversation not found.');
    }

    $authorized = $user['role'] === 'seeker'
        ? (int) $conversation['seeker_user_id'] === $userId
        : $conversation['owner_user_id'] !== null
            && (int) $conversation['owner_user_id'] === $userId;

    if (!$authorized) {
        throw new ChatNotFoundException('Conversation not found.');
    }

    return $conversation;
}

function chat_insert_message(PDO $pdo, int $conversationId, int $senderUserId, string $body): int
{
    $statement = $pdo->prepare(
        'INSERT INTO chat_messages (conversation_id, sender_user_id, body)
         VALUES (:conversation_id, :sender_user_id, :body)'
    );
    $statement->execute([
        'conversation_id' => $conversationId,
        'sender_user_id' => $senderUserId,
        'body' => $body,
    ]);

    $update = $pdo->prepare(
        'UPDATE chat_conversations SET last_message_at = CURRENT_TIMESTAMP WHERE id = :id'
    );
    $update->execute(['id' => $conversationId]);

    return (int) $pdo->lastInsertId();
}

function chat_start_conversation(
    PDO $pdo,
    array $user,
    int $buildingId,
    ?int $roomId,
    string $firstMessage
): int {
    $userId = chat_actor_id($user);
    if ($user['role'] !== 'seeker') {
        throw new ChatAccessDeniedException('Only seekers may start a conversation.');
    }
    if ($buildingId < 1 || ($roomId !== null && $roomId < 1)) {
        throw new ChatValidationException('A valid building and optional room are required.');
    }
    $body = chat_validate_message($firstMessage);

    return chat_transaction($pdo, static function () use ($pdo, $userId, $buildingId, $roomId, $body): int {
        $buildingQuery = $pdo->prepare(
            'SELECT id, owner_user_id FROM kos_buildings
             WHERE id = :building_id AND status = \'active\' FOR UPDATE'
        );
        $buildingQuery->execute(['building_id' => $buildingId]);
        $building = $buildingQuery->fetch();
        if (!$building || $building['owner_user_id'] === null) {
            throw new ChatNotFoundException('Building not found or unavailable for chat.');
        }

        if ($roomId !== null) {
            $roomQuery = $pdo->prepare(
                'SELECT id FROM rooms WHERE id = :room_id AND building_id = :building_id'
            );
            $roomQuery->execute(['room_id' => $roomId, 'building_id' => $buildingId]);
            if (!$roomQuery->fetchColumn()) {
                throw new ChatValidationException('The selected room does not belong to this building.');
            }
        }

        $findConversation = $pdo->prepare(
            'SELECT id FROM chat_conversations
             WHERE seeker_user_id = :seeker_user_id AND building_id = :building_id
             FOR UPDATE'
        );
        $findConversation->execute(['seeker_user_id' => $userId, 'building_id' => $buildingId]);
        $conversationId = $findConversation->fetchColumn();

        if ($conversationId === false) {
            $insert = $pdo->prepare(
                'INSERT INTO chat_conversations (seeker_user_id, building_id, room_id)
                 VALUES (:seeker_user_id, :building_id, :room_id)'
            );
            $insert->execute([
                'seeker_user_id' => $userId,
                'building_id' => $buildingId,
                'room_id' => $roomId,
            ]);
            $conversationId = (int) $pdo->lastInsertId();
        } else {
            $conversationId = (int) $conversationId;
        }

        chat_insert_message($pdo, $conversationId, $userId, $body);
        return $conversationId;
    });
}

function chat_send_message(PDO $pdo, array $user, int $conversationId, string $message): int
{
    $userId = chat_actor_id($user);
    $body = chat_validate_message($message);

    return chat_transaction($pdo, static function () use ($pdo, $user, $userId, $conversationId, $body): int {
        $conversation = chat_participant_conversation($pdo, $user, $conversationId, true);
        if ($user['role'] === 'operator'
            && ($conversation['owner_user_id'] === null || (int) $conversation['owner_user_id'] !== $userId)) {
            throw new ChatAccessDeniedException('Only the building operator may reply.');
        }

        return chat_insert_message($pdo, $conversationId, $userId, $body);
    });
}

function chat_list_conversations(PDO $pdo, array $user): array
{
    $userId = chat_actor_id($user);
    $participantFilter = $user['role'] === 'seeker'
        ? 'c.seeker_user_id = :user_id'
        : 'b.owner_user_id = :user_id';

    $statement = $pdo->prepare(
        'SELECT c.id, c.seeker_user_id, c.building_id, c.room_id, c.created_at,
                c.updated_at, c.last_message_at, b.name AS building_name,
                rm.room_number, s.name AS seeker_name,
                (SELECT COUNT(*) FROM chat_messages unread
                 WHERE unread.conversation_id = c.id
                   AND unread.sender_user_id <> :unread_user_id
                   AND unread.read_at IS NULL) AS unread_count
         FROM chat_conversations c
         JOIN kos_buildings b ON b.id = c.building_id
         JOIN users s ON s.id = c.seeker_user_id
         LEFT JOIN rooms rm ON rm.id = c.room_id
         WHERE ' . $participantFilter . '
         ORDER BY COALESCE(c.last_message_at, c.created_at) DESC, c.id DESC'
    );
    $statement->execute(['user_id' => $userId, 'unread_user_id' => $userId]);

    return $statement->fetchAll();
}

function chat_get_conversation(PDO $pdo, array $user, int $conversationId): array
{
    chat_participant_conversation($pdo, $user, $conversationId);
    $userId = (int) $user['id'];
    $statement = $pdo->prepare(
        'SELECT c.id, c.seeker_user_id, c.building_id, c.room_id, c.created_at,
                c.updated_at, c.last_message_at, b.name AS building_name,
                rm.room_number, s.name AS seeker_name, o.name AS operator_name,
                (SELECT COUNT(*) FROM chat_messages unread
                 WHERE unread.conversation_id = c.id
                   AND unread.sender_user_id <> :user_id
                   AND unread.read_at IS NULL) AS unread_count
         FROM chat_conversations c
         JOIN kos_buildings b ON b.id = c.building_id
         JOIN users s ON s.id = c.seeker_user_id
         LEFT JOIN users o ON o.id = b.owner_user_id
         LEFT JOIN rooms rm ON rm.id = c.room_id
         WHERE c.id = :conversation_id'
    );
    $statement->execute(['conversation_id' => $conversationId, 'user_id' => $userId]);
    $conversation = $statement->fetch();
    if (!$conversation) {
        throw new ChatNotFoundException('Conversation not found.');
    }

    return $conversation;
}

function chat_get_messages(
    PDO $pdo,
    array $user,
    int $conversationId,
    int $beforeMessageId = 0,
    int $limit = 50
): array {
    chat_participant_conversation($pdo, $user, $conversationId);
    if ($beforeMessageId < 0) {
        throw new ChatValidationException('Invalid message cursor.');
    }
    $limit = max(1, min($limit, 100));
    $sql = 'SELECT id, conversation_id, sender_user_id, body, created_at, read_at
            FROM chat_messages
            WHERE conversation_id = :conversation_id';
    $parameters = ['conversation_id' => $conversationId];
    if ($beforeMessageId > 0) {
        $sql .= ' AND id < :before_message_id';
        $parameters['before_message_id'] = $beforeMessageId;
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . $limit;

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    return array_reverse($statement->fetchAll());
}

function chat_mark_read(PDO $pdo, array $user, int $conversationId): int
{
    $userId = chat_actor_id($user);

    return chat_transaction($pdo, static function () use ($pdo, $user, $userId, $conversationId): int {
        chat_participant_conversation($pdo, $user, $conversationId, true);
        $statement = $pdo->prepare(
            'UPDATE chat_messages SET read_at = CURRENT_TIMESTAMP
             WHERE conversation_id = :conversation_id
               AND sender_user_id <> :user_id AND read_at IS NULL'
        );
        $statement->execute(['conversation_id' => $conversationId, 'user_id' => $userId]);

        return $statement->rowCount();
    });
}
