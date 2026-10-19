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
    if ($userId < 1 || !in_array($user['role'] ?? null, ['seeker', 'owner', 'admin'], true)) {
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
        'SELECT c.id, c.conversation_type, c.seeker_user_id, c.requester_user_id,
                c.building_id, c.room_id, c.archived_at, b.owner_user_id
         FROM chat_conversations c
         LEFT JOIN kos_buildings b ON b.id = c.building_id
         WHERE c.id = :conversation_id' . ($lock ? ' FOR UPDATE' : '')
    );
    $statement->execute(['conversation_id' => $conversationId]);
    $conversation = $statement->fetch();

    if (!$conversation) {
        throw new ChatNotFoundException('Conversation not found.');
    }

    $authorized = $user['role'] === 'admin'
        || ($conversation['conversation_type'] === 'support'
            ? (int) $conversation['requester_user_id'] === $userId
            && $user['role'] !== 'admin'
            : ($user['role'] === 'seeker'
                ? (int) $conversation['seeker_user_id'] === $userId
                : $conversation['owner_user_id'] !== null
                    && (int) $conversation['owner_user_id'] === $userId));

    if (!$authorized || ($conversation['archived_at'] !== null && $user['role'] !== 'admin')) {
        throw new ChatNotFoundException('Conversation not found.');
    }

    return $conversation;
}

function chat_start_support_conversation(PDO $pdo, array $user): int
{
    $userId = chat_actor_id($user);
    if ($user['role'] === 'admin') {
        throw new ChatAccessDeniedException('Admins cannot open a support request.');
    }

    $statement = $pdo->prepare(
        "INSERT INTO chat_conversations
            (conversation_type, requester_user_id)
         VALUES ('support', :requester_user_id)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
    );
    $statement->execute(['requester_user_id' => $userId]);
    $conversationId = (int) $pdo->lastInsertId();
    if ($conversationId < 1) {
        $find = $pdo->prepare(
            "SELECT id FROM chat_conversations
             WHERE conversation_type='support' AND requester_user_id=:requester_user_id"
        );
        $find->execute(['requester_user_id' => $userId]);
        $conversationId = (int) $find->fetchColumn();
    }
    if ($conversationId < 1) {
        throw new RuntimeException('Unable to open support conversation.');
    }
    $archived = $pdo->prepare('SELECT archived_at FROM chat_conversations WHERE id=:id');
    $archived->execute(['id' => $conversationId]);
    if ($archived->fetchColumn() !== null) {
        throw new ChatAccessDeniedException('Your support conversation has been archived by an admin.');
    }

    return $conversationId;
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
            'SELECT id, archived_at FROM chat_conversations
             WHERE conversation_type = \'property\'
               AND seeker_user_id = :seeker_user_id AND building_id = :building_id
             FOR UPDATE'
        );
        $findConversation->execute(['seeker_user_id' => $userId, 'building_id' => $buildingId]);
        $existingConversation = $findConversation->fetch();

        if (!$existingConversation) {
            $insert = $pdo->prepare(
                "INSERT INTO chat_conversations
                    (conversation_type, seeker_user_id, building_id, room_id)
                 VALUES ('property', :seeker_user_id, :building_id, :room_id)"
            );
            $insert->execute([
                'seeker_user_id' => $userId,
                'building_id' => $buildingId,
                'room_id' => $roomId,
            ]);
            $conversationId = (int) $pdo->lastInsertId();
        } else {
            if ($existingConversation['archived_at'] !== null) {
                throw new ChatAccessDeniedException('This conversation has been archived by an admin.');
            }
            $conversationId = (int) $existingConversation['id'];
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
        if ($conversation['archived_at'] !== null) {
            throw new ChatAccessDeniedException('Archived conversations cannot receive messages.');
        }
        if ($conversation['conversation_type'] === 'support') {
            if ($user['role'] === 'admin') {
                return chat_insert_message($pdo, $conversationId, $userId, $body);
            }
            if ((int) $conversation['requester_user_id'] !== $userId) {
                throw new ChatAccessDeniedException('Only the support requester or an admin may reply.');
            }
        } else {
            if ($user['role'] === 'admin') {
                throw new ChatAccessDeniedException('Admins may only reply to support conversations.');
            }
            if ($user['role'] === 'owner'
                && ($conversation['owner_user_id'] === null || (int) $conversation['owner_user_id'] !== $userId)) {
                throw new ChatAccessDeniedException('Only the building owner may reply.');
            }
        }

        return chat_insert_message($pdo, $conversationId, $userId, $body);
    });
}

function chat_list_conversations(PDO $pdo, array $user): array
{
    $userId = chat_actor_id($user);
    if ($user['role'] === 'admin') {
        $participantFilter = '1=1';
        $unreadQuery = "SELECT COUNT(*) FROM chat_messages unread
            JOIN users unread_sender ON unread_sender.id=unread.sender_user_id
            WHERE c.conversation_type='support' AND c.archived_at IS NULL
              AND unread.conversation_id=c.id AND unread.read_at IS NULL
              AND unread_sender.role <> 'admin'";
        $parameters = [];
    } else {
        $participantFilter = $user['role'] === 'seeker'
            ? '(c.conversation_type = \'support\' AND c.requester_user_id = :requester_id)
                OR (c.conversation_type = \'property\' AND c.seeker_user_id = :participant_id)'
            : '(c.conversation_type = \'support\' AND c.requester_user_id = :requester_id)
                OR (c.conversation_type = \'property\' AND b.owner_user_id = :participant_id)';
        $unreadQuery = 'SELECT COUNT(*) FROM chat_messages unread
            WHERE unread.conversation_id=c.id AND unread.sender_user_id <> :unread_user_id
              AND unread.read_at IS NULL';
        $parameters = [
            'requester_id' => $userId,
            'participant_id' => $userId,
            'unread_user_id' => $userId,
        ];
    }

    $sql =
        'SELECT c.id, c.conversation_type, c.seeker_user_id, c.requester_user_id,
                c.building_id, c.room_id, c.created_at, c.updated_at, c.last_message_at,
                c.archived_at, b.name AS building_name, rm.room_number,
                s.name AS seeker_name, requester.name AS requester_name,
                requester.role AS requester_role, o.name AS operator_name,
                (SELECT m.body FROM chat_messages m WHERE m.conversation_id = c.id
                 ORDER BY m.id DESC LIMIT 1) AS last_message,
                (' . $unreadQuery . ') AS unread_count
         FROM chat_conversations c
         LEFT JOIN kos_buildings b ON b.id = c.building_id
         LEFT JOIN users s ON s.id = c.seeker_user_id
         LEFT JOIN users requester ON requester.id = c.requester_user_id
         LEFT JOIN users o ON o.id = b.owner_user_id
         LEFT JOIN rooms rm ON rm.id = c.room_id
         WHERE c.archived_at IS NULL AND (' . $participantFilter . ')
         ORDER BY COALESCE(c.last_message_at, c.created_at) DESC, c.id DESC';
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchAll();
}

function chat_unread_count(PDO $pdo, array $user): int
{
    $userId = chat_actor_id($user);
    if ($user['role'] === 'admin') {
        $statement = $pdo->prepare(
            "SELECT COUNT(*) FROM chat_messages m
             JOIN chat_conversations c ON c.id=m.conversation_id
             JOIN users sender ON sender.id=m.sender_user_id
             WHERE c.conversation_type='support' AND c.archived_at IS NULL
               AND m.read_at IS NULL AND sender.role <> 'admin'"
        );
        $statement->execute();
    } else {
        $propertyParticipant = $user['role'] === 'seeker'
            ? 'c.seeker_user_id = :participant_id'
            : 'b.owner_user_id = :participant_id';
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM chat_messages m
             JOIN chat_conversations c ON c.id=m.conversation_id
             LEFT JOIN kos_buildings b ON b.id=c.building_id
             WHERE c.archived_at IS NULL AND m.read_at IS NULL
               AND m.sender_user_id <> :sender_id
               AND ((c.conversation_type=\'support\' AND c.requester_user_id=:requester_id)
                    OR (c.conversation_type=\'property\' AND ' . $propertyParticipant . '))'
        );
        $statement->execute([
            'sender_id' => $userId,
            'requester_id' => $userId,
            'participant_id' => $userId,
        ]);
    }

    return (int) $statement->fetchColumn();
}

function chat_admin_list_conversations(PDO $pdo, string $type, string $status, string $search): array
{
    $where = [];
    $parameters = [];
    if (in_array($type, ['property', 'support'], true)) {
        $where[] = 'c.conversation_type = :conversation_type';
        $parameters['conversation_type'] = $type;
    }
    if ($status === 'active') {
        $where[] = 'c.archived_at IS NULL';
    } elseif ($status === 'archived') {
        $where[] = 'c.archived_at IS NOT NULL';
    }
    $search = trim($search);
    if ($search !== '') {
        $where[] = '(requester.name LIKE :requester_name ESCAPE \'!\'
            OR requester.email LIKE :requester_email ESCAPE \'!\'
            OR s.name LIKE :seeker_name ESCAPE \'!\'
            OR s.email LIKE :seeker_email ESCAPE \'!\'
            OR b.name LIKE :building_name ESCAPE \'!\')';
        $term = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], substr($search, 0, 100)) . '%';
        $parameters += [
            'requester_name' => $term,
            'requester_email' => $term,
            'seeker_name' => $term,
            'seeker_email' => $term,
            'building_name' => $term,
        ];
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $statement = $pdo->prepare(
        'SELECT c.id, c.conversation_type, c.seeker_user_id, c.requester_user_id,
                c.building_id, c.room_id, c.created_at, c.last_message_at,
                c.archived_at, b.name AS building_name, rm.room_number,
                s.name AS seeker_name, requester.name AS requester_name,
                requester.email AS requester_email, requester.role AS requester_role,
                o.name AS operator_name,
                (SELECT m.body FROM chat_messages m WHERE m.conversation_id = c.id
                 ORDER BY m.id DESC LIMIT 1) AS last_message,
                (SELECT COUNT(*) FROM chat_messages unread
                 JOIN users unread_sender ON unread_sender.id = unread.sender_user_id
                 WHERE unread.conversation_id = c.id AND unread.read_at IS NULL
                   AND unread_sender.role <> \'admin\') AS unread_count
         FROM chat_conversations c
         LEFT JOIN kos_buildings b ON b.id = c.building_id
         LEFT JOIN users s ON s.id = c.seeker_user_id
         LEFT JOIN users requester ON requester.id = c.requester_user_id
         LEFT JOIN users o ON o.id = b.owner_user_id
         LEFT JOIN rooms rm ON rm.id = c.room_id
         ' . $whereSql . '
         ORDER BY COALESCE(c.last_message_at, c.created_at) DESC, c.id DESC
         LIMIT 200'
    );
    $statement->execute($parameters);

    return $statement->fetchAll();
}

function chat_get_conversation(PDO $pdo, array $user, int $conversationId): array
{
    chat_participant_conversation($pdo, $user, $conversationId);
    $userId = (int) $user['id'];
    $statement = $pdo->prepare(
        'SELECT c.id, c.conversation_type, c.seeker_user_id, c.requester_user_id,
                c.building_id, c.room_id, c.created_at, c.updated_at, c.last_message_at,
                c.archived_at, c.archived_by, b.name AS building_name,
                rm.room_number, s.name AS seeker_name, o.name AS operator_name,
                requester.name AS requester_name, requester.role AS requester_role,
                (SELECT COUNT(*) FROM chat_messages unread
                 WHERE unread.conversation_id = c.id
                   AND unread.sender_user_id <> :user_id
                   AND unread.read_at IS NULL) AS unread_count
         FROM chat_conversations c
         LEFT JOIN kos_buildings b ON b.id = c.building_id
         LEFT JOIN users s ON s.id = c.seeker_user_id
         LEFT JOIN users requester ON requester.id = c.requester_user_id
         LEFT JOIN users o ON o.id = b.owner_user_id
         LEFT JOIN rooms rm ON rm.id = c.room_id
         WHERE c.id = :conversation_id'
    );
    $statement->execute([
        'conversation_id' => $conversationId,
        'user_id' => $user['role'] === 'admin' ? -$userId : $userId,
    ]);
    $conversation = $statement->fetch();
    if (!$conversation) {
        throw new ChatNotFoundException('Conversation not found.');
    }

    return $conversation;
}

function chat_archive_conversation(PDO $pdo, array $user, int $conversationId, bool $archived): void
{
    if (($user['role'] ?? null) !== 'admin') {
        throw new ChatAccessDeniedException('Only admins may archive conversations.');
    }
    chat_participant_conversation($pdo, $user, $conversationId, true);
    $statement = $pdo->prepare(
        $archived
            ? 'UPDATE chat_conversations SET archived_at=CURRENT_TIMESTAMP, archived_by=:admin_id WHERE id=:id AND archived_at IS NULL'
            : 'UPDATE chat_conversations SET archived_at=NULL, archived_by=NULL WHERE id=:id AND archived_at IS NOT NULL'
    );
    $statement->execute($archived
        ? ['admin_id' => (int) $user['id'], 'id' => $conversationId]
        : ['id' => $conversationId]);
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
    $sql = 'SELECT m.id, m.conversation_id, m.sender_user_id, m.body, m.created_at, m.read_at,
                   u.name AS sender_name, u.role AS sender_role
            FROM chat_messages
            m JOIN users u ON u.id = m.sender_user_id
            WHERE m.conversation_id = :conversation_id';
    $parameters = ['conversation_id' => $conversationId];
    if ($beforeMessageId > 0) {
        $sql .= ' AND m.id < :before_message_id';
        $parameters['before_message_id'] = $beforeMessageId;
    }
    $sql .= ' ORDER BY m.id DESC LIMIT ' . $limit;

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    return array_reverse($statement->fetchAll());
}

function chat_mark_read(PDO $pdo, array $user, int $conversationId): int
{
    $userId = chat_actor_id($user);

    return chat_transaction($pdo, static function () use ($pdo, $user, $userId, $conversationId): int {
        $conversation = chat_participant_conversation($pdo, $user, $conversationId, true);
        if ($user['role'] === 'admin' && $conversation['conversation_type'] !== 'support') {
            return 0;
        }
        $statement = $pdo->prepare(
            'UPDATE chat_messages SET read_at = CURRENT_TIMESTAMP
             WHERE conversation_id = :conversation_id
               AND sender_user_id <> :user_id AND read_at IS NULL'
        );
        $statement->execute(['conversation_id' => $conversationId, 'user_id' => $userId]);

        return $statement->rowCount();
    });
}
