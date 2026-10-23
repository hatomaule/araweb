<?php
declare(strict_types=1);

final class ChatAccessDeniedException extends RuntimeException
{
}

final class ChatNotFoundException extends RuntimeException
{
}

final class ChatValidationException extends RuntimeException
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

function chat_attachment_directory(): string
{
    $configuredDirectory = getenv('CHAT_UPLOAD_DIR');
    return $configuredDirectory !== false && trim($configuredDirectory) !== ''
        ? rtrim($configuredDirectory, DIRECTORY_SEPARATOR)
        : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'private_uploads' . DIRECTORY_SEPARATOR . 'chat';
}

function chat_attachment_path(string $filename): string
{
    if (preg_match('/^[a-f0-9]{40}\\.(?:webp|mp4|webm|mov)$/', $filename) !== 1) {
        throw new ChatNotFoundException('Attachment not found.');
    }

    return chat_attachment_directory() . DIRECTORY_SEPARATOR . $filename;
}

function chat_attachment_url(int $messageId): string
{
    return 'index.php?chat_api=1&chat_action=attachment&message_id=' . $messageId;
}

function chat_attachment_markup(array $message): string
{
    $messageId = (int) ($message['id'] ?? 0);
    $type = $message['attachment_type'] ?? null;
    if ($messageId < 1 || !in_array($type, ['image', 'video'], true)) {
        return '';
    }

    $url = e(chat_attachment_url($messageId));
    if ($type === 'image') {
        return '<img class="chat-message-attachment" src="' . $url . '" alt="Foto lampiran chat" loading="lazy">';
    }

    return '<video class="chat-message-attachment" controls preload="metadata" src="' . $url . '">'
        . 'Browser tidak mendukung pemutaran video.</video>';
}

function chat_store_attachment(?array $upload): ?array
{
    if ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !isset($upload['tmp_name'], $upload['size'])
        || !is_uploaded_file((string) $upload['tmp_name'])) {
        if (in_array($upload['error'] ?? null, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new ChatValidationException('Berkas melewati batas upload server. Atur upload_max_filesize minimal 20M dan post_max_size minimal 24M.');
        }
        throw new ChatValidationException('Berkas lampiran gagal diunggah. Silakan coba lagi.');
    }

    $size = (int) $upload['size'];
    if ($size < 1) {
        throw new ChatValidationException('Berkas lampiran kosong.');
    }
    if ($size > 20 * 1024 * 1024) {
        throw new ChatValidationException('Ukuran lampiran maksimum 20 MB per file.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file((string) $upload['tmp_name']);
    $imageTypes = ['image/webp' => 'webp'];
    $videoTypes = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
    ];
    if (isset($imageTypes[$mimeType])) {
        if ($size > 8 * 1024 * 1024) {
            throw new ChatValidationException('Ukuran foto maksimum 8 MB setelah dikompres.');
        }
        $attachmentType = 'image';
        $attachmentMime = 'image/webp';
        $extension = 'webp';
    } elseif (isset($videoTypes[$mimeType])) {
        $attachmentType = 'video';
        $attachmentMime = $mimeType;
        $extension = $videoTypes[$mimeType];
    } else {
        throw new ChatValidationException('Lampiran harus berupa foto WebP atau video MP4, WebM, atau MOV.');
    }

    $directory = chat_attachment_directory();
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder lampiran chat tidak dapat dibuat. Periksa izin penyimpanan privat.');
    }
    $filename = bin2hex(random_bytes(20)) . '.' . $extension;
    $destination = chat_attachment_path($filename);

    if (!move_uploaded_file((string) $upload['tmp_name'], $destination)) {
        throw new RuntimeException(
            $attachmentType === 'image'
                ? 'Foto chat gagal disimpan ke penyimpanan privat.'
                : 'Video chat gagal disimpan ke penyimpanan privat.'
        );
    }

    $originalName = basename((string) ($upload['name'] ?? 'lampiran'));
    $originalName = preg_replace('/[\\x00-\\x1F\\x7F]/u', '', $originalName) ?: 'lampiran';
    $originalName = pathinfo($originalName, PATHINFO_FILENAME) . '.' . $extension;
    if (function_exists('mb_substr')) {
        $originalName = mb_substr($originalName, 0, 120, 'UTF-8');
    } else {
        $originalName = substr($originalName, 0, 120);
    }

    return [
        'path' => $filename,
        'type' => $attachmentType,
        'mime' => $attachmentMime,
        'name' => $originalName,
        'size' => (int) filesize($destination),
    ];
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

    if (!$authorized) {
        throw new ChatNotFoundException('Conversation not found.');
    }

    return $conversation;
}

function chat_conversation_state(PDO $pdo, int $conversationId, int $userId): string
{
    $statement = $pdo->prepare(
        'SELECT status FROM chat_conversation_user_state
         WHERE conversation_id=:conversation_id AND user_id=:user_id'
    );
    $statement->execute(['conversation_id' => $conversationId, 'user_id' => $userId]);
    return (string) ($statement->fetchColumn() ?: 'active');
}

function chat_set_conversation_state(PDO $pdo, int $conversationId, int $userId, string $status): void
{
    if (!in_array($status, ['active', 'archived', 'trash', 'purged'], true)) {
        throw new InvalidArgumentException('Invalid conversation state.');
    }
    $statement = $pdo->prepare(
        'INSERT INTO chat_conversation_user_state
            (conversation_id,user_id,status,trashed_at)
         VALUES (:conversation_id,:user_id,:status,
            CASE WHEN :trash_flag=1 THEN CURRENT_TIMESTAMP ELSE NULL END)
         ON DUPLICATE KEY UPDATE status=VALUES(status), trashed_at=VALUES(trashed_at)'
    );
    $statement->execute([
        'conversation_id' => $conversationId,
        'user_id' => $userId,
        'status' => $status,
        'trash_flag' => $status === 'trash' ? 1 : 0,
    ]);
}

function chat_purge_expired_trash(PDO $pdo): void
{
    $pdo->exec(
        "UPDATE chat_conversation_user_state
         SET status='purged', trashed_at=NULL
         WHERE status='trash' AND trashed_at <= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY)"
    );
    $pdo->exec(
        "DELETE c FROM chat_conversations c
         LEFT JOIN kos_buildings b ON b.id=c.building_id
         WHERE (
             (c.conversation_type='property'
                AND EXISTS (SELECT 1 FROM chat_conversation_user_state ss
                    WHERE ss.conversation_id=c.id AND ss.user_id=c.seeker_user_id AND ss.status='purged')
                AND EXISTS (SELECT 1 FROM chat_conversation_user_state os
                    WHERE os.conversation_id=c.id AND os.user_id=b.owner_user_id AND os.status='purged'))
             OR
             (c.conversation_type='support'
                AND EXISTS (SELECT 1 FROM chat_conversation_user_state rs
                    WHERE rs.conversation_id=c.id AND rs.user_id=c.requester_user_id AND rs.status='purged'))
         )
         AND NOT EXISTS (SELECT 1 FROM chat_conversation_user_state s
             WHERE s.conversation_id=c.id AND s.status<>'purged')
         "
    );
}

function chat_start_support_conversation(PDO $pdo, array $user): int
{
    $userId = chat_actor_id($user);
    if ($user['role'] === 'admin') {
        throw new ChatAccessDeniedException('Admins cannot open a support request.');
    }

    return chat_transaction($pdo, static function () use ($pdo, $userId): int {
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
        chat_set_conversation_state($pdo, $conversationId, $userId, 'active');

        return $conversationId;
    });
}

function chat_insert_message(
    PDO $pdo,
    int $conversationId,
    int $senderUserId,
    string $body,
    ?array $attachment = null
): int
{
    $statement = $pdo->prepare(
        'INSERT INTO chat_messages
            (conversation_id, sender_user_id, body, attachment_path, attachment_type,
             attachment_mime, attachment_name, attachment_size)
         VALUES
            (:conversation_id, :sender_user_id, :body, :attachment_path, :attachment_type,
             :attachment_mime, :attachment_name, :attachment_size)'
    );
    $statement->execute([
        'conversation_id' => $conversationId,
        'sender_user_id' => $senderUserId,
        'body' => $body,
        'attachment_path' => $attachment['path'] ?? null,
        'attachment_type' => $attachment['type'] ?? null,
        'attachment_mime' => $attachment['mime'] ?? null,
        'attachment_name' => $attachment['name'] ?? null,
        'attachment_size' => $attachment['size'] ?? null,
    ]);

    $update = $pdo->prepare(
        'UPDATE chat_conversations SET last_message_at = CURRENT_TIMESTAMP WHERE id = :id'
    );
    $update->execute(['id' => $conversationId]);
    $pdo->prepare(
        "DELETE FROM chat_conversation_user_state
         WHERE conversation_id=:id AND status='purged' AND user_id<>:sender_id"
    )->execute(['id' => $conversationId, 'sender_id' => $senderUserId]);

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
            $conversationId = (int) $existingConversation['id'];
        }

        chat_insert_message($pdo, $conversationId, $userId, $body);
        chat_set_conversation_state($pdo, $conversationId, $userId, 'active');
        return $conversationId;
    });
}

function chat_send_message(
    PDO $pdo,
    array $user,
    int $conversationId,
    string $message,
    ?array $upload = null
): int
{
    $userId = chat_actor_id($user);
    $hasUpload = $upload !== null && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $body = trim($message) === '' && $hasUpload
        ? 'Lampiran'
        : chat_validate_message($message);
    $storedAttachment = null;

    try {
        return chat_transaction($pdo, static function () use (
            $pdo,
            $user,
            $userId,
            $conversationId,
            $body,
            $upload,
            &$storedAttachment
        ): int {
            $conversation = chat_participant_conversation($pdo, $user, $conversationId, true);
            if (chat_conversation_state($pdo, $conversationId, $userId) !== 'active') {
                throw new ChatAccessDeniedException('Restore this conversation to your inbox before sending a message.');
            }
            if ($conversation['conversation_type'] === 'support') {
                if ($user['role'] !== 'admin'
                    && (int) $conversation['requester_user_id'] !== $userId) {
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

            $storedAttachment = chat_store_attachment($upload);
            $messageBody = $body === 'Lampiran'
                ? ($storedAttachment['type'] === 'image' ? 'Foto' : 'Video')
                : $body;
            if ($conversation['conversation_type'] === 'support' && $user['role'] === 'admin') {
                $messageId = chat_insert_message($pdo, $conversationId, $userId, $messageBody, $storedAttachment);
                chat_set_conversation_state($pdo, $conversationId, $userId, 'active');
                return $messageId;
            }

            return chat_insert_message($pdo, $conversationId, $userId, $messageBody, $storedAttachment);
        });
    } catch (Throwable $exception) {
        if ($storedAttachment !== null) {
            $storedPath = chat_attachment_path($storedAttachment['path']);
            if (is_file($storedPath) && !unlink($storedPath)) {
                error_log('Unable to remove unsaved chat attachment: ' . $storedPath);
            }
        }
        throw $exception;
    }
}

function chat_list_conversations(PDO $pdo, array $user, string $folder = 'inbox'): array
{
    chat_purge_expired_trash($pdo);
    $userId = chat_actor_id($user);
    $folderStatus = ['inbox' => 'active', 'archive' => 'archived', 'trash' => 'trash'][$folder] ?? 'active';
    $participantFilter = $user['role'] === 'admin'
        ? '1=1'
        : ($user['role'] === 'seeker'
            ? '(c.conversation_type = \'support\' AND c.requester_user_id = :requester_id)
                OR (c.conversation_type = \'property\' AND c.seeker_user_id = :participant_id)'
            : '(c.conversation_type = \'support\' AND c.requester_user_id = :requester_id)
                OR (c.conversation_type = \'property\' AND b.owner_user_id = :participant_id)');
    $unreadQuery = $user['role'] === 'admin'
        ? "SELECT COUNT(*) FROM chat_messages unread
            JOIN users unread_sender ON unread_sender.id=unread.sender_user_id
            WHERE c.conversation_type='support' AND unread.conversation_id=c.id
              AND unread.read_at IS NULL AND unread_sender.role <> 'admin'"
        : 'SELECT COUNT(*) FROM chat_messages unread
            WHERE unread.conversation_id=c.id AND unread.sender_user_id <> :unread_user_id
              AND unread.read_at IS NULL';
    $parameters = [
        'folder_status' => $folderStatus,
        'user_id' => $userId,
    ];
    if ($user['role'] !== 'admin') {
        $parameters += ['requester_id' => $userId, 'participant_id' => $userId, 'unread_user_id' => $userId];
    }

    $statement = $pdo->prepare(
        'SELECT c.id, c.conversation_type, c.seeker_user_id, c.requester_user_id,
                c.building_id, c.room_id, c.created_at, c.updated_at, c.last_message_at,
                c.archived_at, b.name AS building_name, rm.room_number,
                s.id AS seeker_profile_id, s.name AS seeker_name,
                s.profile_photo_path AS seeker_profile_photo,
                requester.id AS requester_profile_id, requester.name AS requester_name,
                requester.profile_photo_path AS requester_profile_photo,
                requester.role AS requester_role,
                o.id AS operator_profile_id, o.name AS operator_name,
                o.profile_photo_path AS operator_profile_photo,
                COALESCE(state.status, \'active\') AS personal_status,
                (SELECT m.body FROM chat_messages m WHERE m.conversation_id = c.id
                 ORDER BY m.id DESC LIMIT 1) AS last_message,
                (' . $unreadQuery . ') AS unread_count
         FROM chat_conversations c
         LEFT JOIN kos_buildings b ON b.id = c.building_id
         LEFT JOIN users s ON s.id = c.seeker_user_id
         LEFT JOIN users requester ON requester.id = c.requester_user_id
         LEFT JOIN users o ON o.id = b.owner_user_id
         LEFT JOIN rooms rm ON rm.id = c.room_id
         LEFT JOIN chat_conversation_user_state state
            ON state.conversation_id=c.id AND state.user_id=:user_id
         WHERE COALESCE(state.status, \'active\')=:folder_status
           AND (' . $participantFilter . ')
         ORDER BY COALESCE(c.last_message_at, c.created_at) DESC, c.id DESC'
    );
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
             LEFT JOIN chat_conversation_user_state state
                ON state.conversation_id=c.id AND state.user_id=:user_id
             WHERE c.conversation_type='support' AND m.read_at IS NULL
               AND sender.role <> 'admin' AND COALESCE(state.status,'active')='active'"
        );
    } else {
        $propertyParticipant = $user['role'] === 'seeker'
            ? 'c.seeker_user_id = :participant_id'
            : 'b.owner_user_id = :participant_id';
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM chat_messages m
             JOIN chat_conversations c ON c.id=m.conversation_id
             LEFT JOIN kos_buildings b ON b.id=c.building_id
             LEFT JOIN chat_conversation_user_state state
                ON state.conversation_id=c.id AND state.user_id=:user_id
             WHERE m.read_at IS NULL AND m.sender_user_id <> :sender_id
               AND COALESCE(state.status,\'active\')=\'active\'
               AND ((c.conversation_type=\'support\' AND c.requester_user_id=:requester_id)
                    OR (c.conversation_type=\'property\' AND ' . $propertyParticipant . '))'
        );
        $statement->execute([
            'user_id' => $userId,
            'sender_id' => $userId,
            'requester_id' => $userId,
            'participant_id' => $userId,
        ]);
        return (int) $statement->fetchColumn();
    }

    $statement->execute(['user_id' => $userId]);
    return (int) $statement->fetchColumn();
}

function chat_admin_list_conversations(PDO $pdo, string $type, string $status, string $search, int $userId): array
{
    chat_purge_expired_trash($pdo);
    $where = [];
    $parameters = [];
    if (in_array($type, ['property', 'support'], true)) {
        $where[] = 'c.conversation_type = :conversation_type';
        $parameters['conversation_type'] = $type;
    }

    if (in_array($status, ['active', 'archived', 'trash'], true)) {
        $where[] = 'COALESCE(state.status, \'active\') = :personal_status';
        $parameters['personal_status'] = $status;
    }
    $where[] = 'COALESCE(state.status, \'active\') <> \'purged\'';
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
    $whereSql = 'WHERE ' . ($where ? implode(' AND ', $where) . ' AND ' : '');
    $statement = $pdo->prepare(
        'SELECT c.id, c.conversation_type, c.seeker_user_id, c.requester_user_id,
                c.building_id, c.room_id, c.created_at, c.last_message_at,
                c.archived_at, b.name AS building_name, rm.room_number,
                s.name AS seeker_name, requester.name AS requester_name,
                requester.email AS requester_email, requester.role AS requester_role,
                requester.profile_photo_path AS requester_photo,
                requester.gender AS requester_gender, requester.avatar_key AS requester_avatar,
                s.profile_photo_path AS seeker_photo, s.gender AS seeker_gender,
                s.avatar_key AS seeker_avatar, o.name AS operator_name,
                COALESCE(state.status, \'active\') AS personal_status,
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
         LEFT JOIN chat_conversation_user_state state
            ON state.conversation_id=c.id AND state.user_id=:state_user_id
         ' . $whereSql . '1=1
         ORDER BY COALESCE(c.last_message_at, c.created_at) DESC, c.id DESC
         LIMIT 200'
    );
    $parameters['state_user_id'] = $userId;
    $statement->execute($parameters);

    return $statement->fetchAll();
}

function chat_get_conversation(PDO $pdo, array $user, int $conversationId): array
{
    chat_purge_expired_trash($pdo);
    chat_participant_conversation($pdo, $user, $conversationId);
    $userId = (int) $user['id'];
    $statement = $pdo->prepare(
        'SELECT c.id, c.conversation_type, c.seeker_user_id, c.requester_user_id,
                c.building_id, c.room_id, c.created_at, c.updated_at, c.last_message_at,
                c.archived_at, c.archived_by, b.name AS building_name,
                rm.room_number, s.name AS seeker_name, s.id AS seeker_id,
                s.email AS seeker_email, s.username AS seeker_username,
                s.profile_photo_path AS seeker_photo, s.gender AS seeker_gender,
                s.avatar_key AS seeker_avatar,
                (s.last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) AS seeker_online,
                o.name AS operator_name, o.id AS operator_id, o.email AS operator_email,
                o.username AS operator_username, o.profile_photo_path AS operator_photo,
                o.gender AS operator_gender, o.avatar_key AS operator_avatar,
                (o.last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) AS operator_online,
                requester.name AS requester_name, requester.id AS requester_user_id,
                requester.email AS requester_email, requester.username AS requester_username,
                requester.profile_photo_path AS requester_photo, requester.gender AS requester_gender,
                requester.avatar_key AS requester_avatar, requester.role AS requester_role,
                (requester.last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) AS requester_online,
                (SELECT COUNT(*) FROM users online_admin
                 WHERE online_admin.role=\'admin\' AND online_admin.status=\'active\'
                   AND online_admin.last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) AS online_admin_count,
                COALESCE(state.status, \'active\') AS personal_status,
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
         LEFT JOIN chat_conversation_user_state state
            ON state.conversation_id=c.id AND state.user_id=:state_user_id
         WHERE c.id = :conversation_id'
    );
    $statement->execute([
        'conversation_id' => $conversationId,
        'user_id' => $user['role'] === 'admin' ? -$userId : $userId,
        'state_user_id' => $userId,
    ]);
    $conversation = $statement->fetch();
    if (!$conversation || $conversation['personal_status'] === 'purged') {
        throw new ChatNotFoundException('Conversation not found.');
    }

    $conversation['recipient_online'] = $conversation['conversation_type'] === 'support'
        ? ($user['role'] === 'admin'
            ? (bool) $conversation['requester_online']
            : (int) $conversation['online_admin_count'] > 0)
        : ($user['role'] === 'seeker'
            ? (bool) $conversation['operator_online']
            : ($user['role'] === 'owner' && (bool) $conversation['seeker_online']));

    return $conversation;
}

function chat_archive_conversation(PDO $pdo, array $user, int $conversationId, bool $archived): void
{
    $userId = chat_actor_id($user);
    chat_participant_conversation($pdo, $user, $conversationId, true);
    chat_set_conversation_state($pdo, $conversationId, $userId, $archived ? 'archived' : 'active');
}

function chat_trash_conversation(PDO $pdo, array $user, int $conversationId): void
{
    $userId = chat_actor_id($user);
    chat_participant_conversation($pdo, $user, $conversationId, true);
    chat_set_conversation_state($pdo, $conversationId, $userId, 'trash');
}

function chat_restore_trashed_conversation(PDO $pdo, array $user, int $conversationId): void
{
    $userId = chat_actor_id($user);
    chat_participant_conversation($pdo, $user, $conversationId, true);
    if (chat_conversation_state($pdo, $conversationId, $userId) !== 'trash') {
        throw new ChatNotFoundException('Conversation not found in trash.');
    }
    chat_set_conversation_state($pdo, $conversationId, $userId, 'active');
}

function chat_empty_trash(PDO $pdo, array $user): void
{
    $userId = chat_actor_id($user);
    $statement = $pdo->prepare(
        "UPDATE chat_conversation_user_state SET status='purged', trashed_at=NULL
         WHERE user_id=:user_id AND status='trash'"
    );
    $statement->execute(['user_id' => $userId]);
    chat_purge_expired_trash($pdo);
}

function chat_get_messages(
    PDO $pdo,
    array $user,
    int $conversationId,
    int $beforeMessageId = 0,
    int $limit = 50
): array {
    chat_participant_conversation($pdo, $user, $conversationId);
    if (chat_conversation_state($pdo, $conversationId, (int) $user['id']) === 'purged') {
        throw new ChatNotFoundException('Conversation not found.');
    }
    if ($beforeMessageId < 0) {
        throw new ChatValidationException('Invalid message cursor.');
    }
    $limit = max(1, min($limit, 100));
    $sql = 'SELECT m.id, m.conversation_id, m.sender_user_id, m.body, m.created_at, m.read_at,
                   m.attachment_path, m.attachment_type, m.attachment_mime, m.attachment_name, m.attachment_size,
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

function chat_message_delivery_status(array $message, array $conversation, array $user): ?string
{
    if ((int) $message['sender_user_id'] !== (int) $user['id']) {
        return null;
    }
    if ($message['read_at'] !== null) {
        return 'read';
    }

    return !empty($conversation['recipient_online']) ? 'delivered' : 'sent';
}

function chat_delivery_status_markup(array $message, array $conversation, array $user): string
{
    $deliveryStatus = chat_message_delivery_status($message, $conversation, $user);
    if ($deliveryStatus === null) {
        return '';
    }

    $label = match ($deliveryStatus) {
        'read' => 'Dibaca',
        'delivered' => 'Diterima',
        default => 'Terkirim',
    };
    $class = $deliveryStatus === 'read' ? 'chat-delivery-status is-read' : 'chat-delivery-status';
    $icon = $deliveryStatus === 'sent'
        ? '<path d="m2 7 4 4L16 1"/>'
        : '<path d="m1 7 4 4L15 1"/><path d="m7 7 4 4L19 1"/>';

    return '<span class="' . $class . '" role="img" aria-label="' . $label . '" title="' . $label
        . '"><svg viewBox="0 0 20 14" aria-hidden="true">' . $icon . '</svg></span>';
}

function chat_mark_read(PDO $pdo, array $user, int $conversationId): int
{
    $userId = chat_actor_id($user);

    return chat_transaction($pdo, static function () use ($pdo, $user, $userId, $conversationId): int {
        $conversation = chat_participant_conversation($pdo, $user, $conversationId, true);
        if (chat_conversation_state($pdo, $conversationId, $userId) === 'purged') {
            throw new ChatNotFoundException('Conversation not found.');
        }
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
