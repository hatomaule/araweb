<?php
declare(strict_types=1);

function chat_api_respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function chat_api_dispatch(PDO $pdo, ?array $user): never
{
    if (!$user || !in_array($user['role'] ?? null, ['seeker', 'owner', 'admin'], true)) {
        chat_api_respond(['error' => 'Silakan masuk untuk menggunakan chat.'], 401);
    }

    try {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $action = isset($_GET['chat_action']) && is_string($_GET['chat_action'])
                ? $_GET['chat_action'] : 'list';
            if ($action === 'attachment') {
                $messageId = max(0, (int) ($_GET['message_id'] ?? 0));
                $statement = $pdo->prepare(
                    'SELECT m.id,m.conversation_id,m.attachment_path,m.attachment_type,
                            m.attachment_mime,m.attachment_name
                     FROM chat_messages m
                     WHERE m.id=:message_id AND m.attachment_path IS NOT NULL'
                );
                $statement->execute(['message_id' => $messageId]);
                $attachment = $statement->fetch();
                if (!$attachment) {
                    throw new ChatNotFoundException('Attachment not found.');
                }
                $conversationId = (int) $attachment['conversation_id'];
                chat_participant_conversation($pdo, $user, $conversationId);
                if (chat_conversation_state($pdo, $conversationId, (int) $user['id']) === 'purged') {
                    throw new ChatNotFoundException('Attachment not found.');
                }
                $filePath = chat_attachment_path((string) $attachment['attachment_path']);
                if (!is_file($filePath)) {
                    throw new ChatNotFoundException('Attachment not found.');
                }
                $fileSize = filesize($filePath);
                $start = 0;
                $end = $fileSize - 1;
                $status = 200;
                if (isset($_SERVER['HTTP_RANGE'])
                    && preg_match('/^bytes=(\d*)-(\d*)$/', (string) $_SERVER['HTTP_RANGE'], $range) === 1) {
                    if ($range[1] === '' && $range[2] === '') {
                        http_response_code(416);
                        header('Content-Range: bytes */' . $fileSize);
                        exit;
                    }
                    if ($range[1] === '') {
                        $start = max(0, $fileSize - (int) $range[2]);
                    } else {
                        $start = (int) $range[1];
                        $end = $range[2] === '' ? $end : min($end, (int) $range[2]);
                    }
                    if ($start > $end || $start >= $fileSize) {
                        http_response_code(416);
                        header('Content-Range: bytes */' . $fileSize);
                        exit;
                    }
                    $status = 206;
                }
                $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $attachment['attachment_name']) ?: 'lampiran';
                http_response_code($status);
                header('Content-Type: ' . $attachment['attachment_mime']);
                header('Content-Disposition: inline; filename="' . $safeName . '"');
                header('X-Content-Type-Options: nosniff');
                header('Cache-Control: private, no-store');
                header('Accept-Ranges: bytes');
                header('Content-Length: ' . ($end - $start + 1));
                if ($status === 206) {
                    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
                }
                $handle = fopen($filePath, 'rb');
                if ($handle === false) {
                    throw new RuntimeException('Chat attachment could not be opened.');
                }
                fseek($handle, $start);
                $remaining = $end - $start + 1;
                while ($remaining > 0 && !feof($handle)) {
                    $chunk = fread($handle, min(8192, $remaining));
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    echo $chunk;
                    $remaining -= strlen($chunk);
                }
                fclose($handle);
                exit;
            }
            if ($action === 'list') {
                $folder = isset($_GET['folder']) && is_string($_GET['folder']) ? $_GET['folder'] : 'inbox';
                $conversations = array_map(
                    static function (array $conversation) use ($user): array {
                        $isSupport = $conversation['conversation_type'] === 'support';
                        $otherParty = $user['role'] === 'seeker'
                            ? ($conversation['operator_name'] ?: $conversation['building_name'] ?: 'Pengelola kos')
                            : ($conversation['seeker_name'] ?: 'Pengguna');
                        $profile = match ($user['role']) {
                            'admin' => $isSupport
                                ? [$conversation['requester_profile_id'], $conversation['requester_name'], $conversation['requester_profile_photo']]
                                : [$conversation['seeker_profile_id'], $conversation['seeker_name'], $conversation['seeker_profile_photo']],
                            'seeker' => !$isSupport
                                ? [$conversation['operator_profile_id'], $conversation['operator_name'], $conversation['operator_profile_photo']]
                                : [null, null, null],
                            default => !$isSupport
                                ? [$conversation['seeker_profile_id'], $conversation['seeker_name'], $conversation['seeker_profile_photo']]
                                : [null, null, null],
                        };
                        $profileId = (int) ($profile[0] ?? 0);
                        $profileName = $profile[1] ?? null;
                        return [
                            'id' => (int) $conversation['id'],
                            'type' => $conversation['conversation_type'],
                            'title' => $isSupport
                                ? ($user['role'] === 'admin'
                                    ? ($conversation['requester_name'] ?: 'Pengguna')
                                    : 'Bantuan KosKosan')
                                : ($user['role'] === 'admin'
                                    ? $otherParty . ' · ' . ($conversation['building_name'] ?: 'Kos')
                                    : $otherParty),
                            'subtitle' => $isSupport
                                ? ($user['role'] === 'admin'
                                    ? ucfirst((string) ($conversation['requester_role'] ?: 'user')) . ' · Bantuan admin'
                                    : 'Dukungan admin')
                                : ($conversation['building_name'] ?: 'Percakapan kos'),
                            'last_message' => (string) ($conversation['last_message'] ?? ''),
                            'unread_count' => (int) $conversation['unread_count'],
                            'personal_status' => $conversation['personal_status'],
                            'profile_name' => $profileName,
                            'profile_photo' => $profile[2] ?: null,
                            'profile_url' => $profileId > 0
                                ? 'index.php?page=chat_profile&conversation_id=' . (int) $conversation['id']
                                : null,
                        ];
                    },
                    array_slice(chat_list_conversations($pdo, $user, $folder), 0, 100)
                );
                chat_api_respond(['conversations' => $conversations]);
            }
            if ($action === 'messages') {
                $conversationId = max(0, (int) ($_GET['conversation_id'] ?? 0));
                $conversation = chat_get_conversation($pdo, $user, $conversationId);
                chat_mark_read($pdo, $user, $conversationId);
                $messages = chat_get_messages($pdo, $user, $conversationId, 0, 100);
                chat_api_respond([
                    'conversation' => [
                        'id' => (int) $conversation['id'],
                        'type' => $conversation['conversation_type'],
                        'title' => $conversation['conversation_type'] === 'support'
                            ? ($user['role'] === 'admin'
                                ? ($conversation['requester_name'] ?: 'Pengguna')
                                : 'Bantuan KosKosan')
                            : ($user['role'] === 'seeker'
                                ? ($conversation['operator_name'] ?: $conversation['building_name'])
                                : ($conversation['seeker_name'] ?: 'Pengguna')),
                        'subtitle' => $conversation['conversation_type'] === 'support'
                            ? 'Dukungan KosKosan'
                            : ($conversation['building_name'] ?: 'Percakapan kos')
                                . ($conversation['room_number'] ? ' · Kamar ' . $conversation['room_number'] : ''),
                        'can_send' => $conversation['personal_status'] === 'active'
                            && ($conversation['conversation_type'] === 'support'
                                ? ($user['role'] === 'admin'
                                    || (int) $conversation['requester_user_id'] === (int) $user['id'])
                                : $user['role'] !== 'admin'),
                        'archived' => $conversation['personal_status'] === 'archived',
                        'personal_status' => $conversation['personal_status'],
                        'recipient_online' => (bool) $conversation['recipient_online'],
                    ],
                    'messages' => array_map(
                        static function (array $message) use ($conversation, $user): array {
                            return [
                                'id' => (int) $message['id'],
                                'sender_user_id' => (int) $message['sender_user_id'],
                                'body' => $message['body'],
                                'created_at' => $message['created_at'],
                                'attachment_type' => $message['attachment_type'],
                                'attachment_name' => $message['attachment_name'],
                                'attachment_url' => $message['attachment_type'] !== null
                                    ? chat_attachment_url((int) $message['id']) : null,
                                'delivery_status' => chat_message_delivery_status($message, $conversation, $user),
                            ];
                        },
                        $messages
                    ),
                    'current_user_id' => (int) $user['id'],
                ]);
            }
            chat_api_respond(['error' => 'Aksi chat tidak dikenal.'], 400);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: GET, POST');
            chat_api_respond(['error' => 'Metode request tidak diizinkan.'], 405);
        }
        if (!valid_csrf()) {
            chat_api_respond(['error' => 'Sesi formulir kedaluwarsa. Muat ulang halaman dan coba lagi.'], 419);
        }

        $action = post_value('chat_action');
        if ($action === 'archive' || $action === 'trash'
            || $action === 'restore' || $action === 'restore_trash') {
            $conversationId = (int) post_value('conversation_id');
            if ($action === 'archive') {
                chat_archive_conversation($pdo, $user, $conversationId, true);
            } elseif ($action === 'trash') {
                chat_trash_conversation($pdo, $user, $conversationId);
            } elseif ($action === 'restore') {
                chat_archive_conversation($pdo, $user, $conversationId, false);
            } else {
                chat_restore_trashed_conversation($pdo, $user, $conversationId);
            }
            chat_api_respond(['ok' => true]);
        }
        if ($action === 'empty_trash') {
            chat_empty_trash($pdo, $user);
            chat_api_respond(['ok' => true]);
        }
        if ($action === 'start_support') {
            if ($user['role'] === 'admin') {
                chat_api_respond(['error' => 'Admin tidak dapat membuat chat bantuan.'], 403);
            }
            $conversationId = chat_start_support_conversation($pdo, $user);
            chat_api_respond(['conversation_id' => $conversationId], 201);
        }
        if ($action === 'send') {
            $conversationId = (int) post_value('conversation_id');
            $attachment = isset($_FILES['attachment']) && is_array($_FILES['attachment'])
                ? $_FILES['attachment'] : null;
            chat_send_message($pdo, $user, $conversationId, post_value('message'), $attachment);
            $conversation = chat_get_conversation($pdo, $user, $conversationId);
            chat_notify_message_recipients($pdo, $user, $conversation, $conversationId);
            chat_api_respond(['ok' => true]);
        }

        chat_api_respond(['error' => 'Aksi chat tidak dikenal.'], 400);
    } catch (ChatNotFoundException $exception) {
        chat_api_respond(['error' => 'Percakapan tidak ditemukan atau tidak dapat diakses.'], 404);
    } catch (ChatAccessDeniedException|ChatValidationException $exception) {
        chat_api_respond(['error' => $exception->getMessage()], 422);
    } catch (PDOException $exception) {
        error_log('Chat API database error: ' . $exception->getMessage());
        chat_api_respond(['error' => 'Chat tidak dapat dimuat saat ini. Coba lagi sebentar.'], 503);
    } catch (JsonException $exception) {
        error_log('Chat API JSON encoding failed: ' . $exception->getMessage());
        chat_api_respond(['error' => 'Respons chat gagal disiapkan.'], 500);
    } catch (Throwable $exception) {
        error_log('Chat API failed: ' . $exception->getMessage());
        chat_api_respond(['error' => 'Terjadi kesalahan saat memproses chat.'], 500);
    }
}
