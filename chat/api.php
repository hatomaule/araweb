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
            if ($action === 'list') {
                $folder = isset($_GET['folder']) && is_string($_GET['folder']) ? $_GET['folder'] : 'inbox';
                $conversations = array_map(
                    static function (array $conversation) use ($user): array {
                        $isSupport = $conversation['conversation_type'] === 'support';
                        $otherParty = $user['role'] === 'seeker'
                            ? ($conversation['operator_name'] ?: $conversation['building_name'] ?: 'Pengelola kos')
                            : ($conversation['seeker_name'] ?: 'Pengguna');
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
                    ],
                    'messages' => array_map(
                        static fn (array $message): array => [
                            'id' => (int) $message['id'],
                            'sender_user_id' => (int) $message['sender_user_id'],
                            'body' => $message['body'],
                            'created_at' => $message['created_at'],
                        ],
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
            chat_send_message($pdo, $user, $conversationId, post_value('message'));
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
