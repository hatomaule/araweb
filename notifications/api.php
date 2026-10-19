<?php
declare(strict_types=1);

function notification_api_respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function notification_api_dispatch(PDO $pdo, ?array $user): never
{
    if (!$user || !in_array($user['role'] ?? null, ['admin', 'owner', 'seeker'], true)) {
        notification_api_respond(['error' => 'Silakan masuk untuk mengelola notifikasi.'], 401);
    }
    try {
        $userId = (int) $user['id'];
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $action = isset($_GET['notification_action']) && is_string($_GET['notification_action'])
                ? $_GET['notification_action'] : 'state';
            $enabled = notification_get_preference($pdo);
            $latestId = (int) one(
                $pdo,
                'SELECT COALESCE(MAX(id),0) AS latest_id FROM notifications WHERE user_id=:user_id',
                ['user_id' => $userId]
            )['latest_id'];
            $unreadCount = notification_unread_count($pdo);
            if ($action === 'state') {
                $pushCount = (int) one(
                    $pdo,
                    'SELECT COUNT(*) AS total FROM web_push_subscriptions WHERE user_id=:user_id',
                    ['user_id' => $userId]
                )['total'];
                notification_api_respond([
                    'enabled' => $enabled,
                    'public_key' => web_push_public_key(),
                    'subscribed' => $pushCount > 0,
                    'latest_id' => $latestId,
                    'unread_count' => $unreadCount,
                ]);
            }
            if ($action === 'poll') {
                $afterId = filter_var($_GET['after_id'] ?? 0, FILTER_VALIDATE_INT);
                if ($afterId === false || $afterId < 0) {
                    notification_api_respond(['error' => 'Kursor notifikasi tidak valid.'], 400);
                }
                $items = [];
                if ($enabled) {
                    $items = rows(
                        $pdo,
                        'SELECT id,title,body,created_at FROM notifications
                         WHERE user_id=:user_id AND id>:after_id AND read_at IS NULL
                         ORDER BY id LIMIT 20',
                        ['user_id' => $userId, 'after_id' => $afterId]
                    );
                }
                notification_api_respond([
                    'enabled' => $enabled,
                    'latest_id' => $latestId,
                    'unread_count' => $unreadCount,
                    'notifications' => $items,
                ]);
            }
            notification_api_respond(['error' => 'Aksi notifikasi tidak dikenal.'], 400);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: GET, POST');
            notification_api_respond(['error' => 'Metode request tidak diizinkan.'], 405);
        }
        if (!valid_csrf()) {
            notification_api_respond(['error' => 'Sesi formulir kedaluwarsa. Muat ulang halaman.'], 419);
        }

        $action = post_value('notification_action');
        if ($action === 'subscribe') {
            $subscriptionJson = post_value('subscription');
            try {
                $subscription = json_decode($subscriptionJson, true, 8, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                notification_api_respond(['error' => 'Data langganan push tidak valid.'], 400);
            }
            $endpoint = $subscription['endpoint'] ?? null;
            $keys = $subscription['keys'] ?? null;
            if (!is_string($endpoint) || strlen($endpoint) > 2048
                || !web_push_endpoint_is_supported($endpoint)
                || !is_array($keys) || !is_string($keys['p256dh'] ?? null)
                || !is_string($keys['auth'] ?? null)) {
                notification_api_respond(['error' => 'Langganan browser tidak memenuhi syarat Web Push.'], 422);
            }
            $p256dh = web_push_base64url_decode($keys['p256dh']);
            $auth = web_push_base64url_decode($keys['auth']);
            if (strlen($p256dh) !== 65 || $p256dh[0] !== "\x04" || strlen($auth) < 16 || strlen($auth) > 32) {
                notification_api_respond(['error' => 'Kunci langganan browser tidak valid.'], 422);
            }
            $endpointHash = hash('sha256', $endpoint);
            $existing = one(
                $pdo,
                'SELECT user_id FROM web_push_subscriptions WHERE endpoint_hash=:endpoint_hash',
                ['endpoint_hash' => $endpointHash]
            );
            if ($existing && (int) $existing['user_id'] !== $userId) {
                notification_api_respond(['error' => 'Langganan browser sudah terhubung ke akun lain.'], 409);
            }
            $statement = $pdo->prepare(
                'INSERT INTO web_push_subscriptions
                    (user_id,endpoint,endpoint_hash,p256dh_key,auth_secret,user_agent)
                 VALUES (:user_id,:endpoint,:endpoint_hash,:p256dh,:auth,:user_agent)
                 ON DUPLICATE KEY UPDATE p256dh_key=VALUES(p256dh_key),
                    auth_secret=VALUES(auth_secret),user_agent=VALUES(user_agent)'
            );
            $statement->execute([
                'user_id' => $userId,
                'endpoint' => $endpoint,
                'endpoint_hash' => $endpointHash,
                'p256dh' => $keys['p256dh'],
                'auth' => $keys['auth'],
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            ]);
            notification_api_respond(['ok' => true]);
        }
        if ($action === 'unsubscribe') {
            $endpoint = post_value('endpoint');
            if ($endpoint === '' || strlen($endpoint) > 2048) {
                notification_api_respond(['error' => 'Endpoint langganan tidak valid.'], 422);
            }
            $pdo->prepare(
                'DELETE FROM web_push_subscriptions
                 WHERE user_id=:user_id AND endpoint_hash=:endpoint_hash'
            )->execute(['user_id' => $userId, 'endpoint_hash' => hash('sha256', $endpoint)]);
            notification_api_respond(['ok' => true]);
        }

        notification_api_respond(['error' => 'Aksi notifikasi tidak dikenal.'], 400);
    } catch (PDOException $exception) {
        error_log('Notification API database error: ' . $exception->getMessage());
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            activity_log_set_error($pdo, $user, 'notification_api_database_error', 'notifications');
        }
        notification_api_respond(['error' => 'Pengaturan notifikasi tidak dapat disimpan saat ini.'], 503);
    } catch (Throwable $exception) {
        error_log('Notification API failed: ' . $exception->getMessage());
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            activity_log_set_error($pdo, $user, 'notification_api_error', 'notifications');
        }
        notification_api_respond(['error' => 'Terjadi kesalahan saat memproses notifikasi.'], 500);
    }
}
