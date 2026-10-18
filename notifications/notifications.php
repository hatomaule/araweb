<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';

function notification_authenticated_user_id(PDO $pdo): int
{
    $sessionUserId = $_SESSION['user_id'] ?? null;
    if (is_string($sessionUserId) && ctype_digit($sessionUserId)) {
        $sessionUserId = (int) $sessionUserId;
    }
    if (!is_int($sessionUserId) || $sessionUserId < 1) {
        throw new RuntimeException('An active authenticated user is required.');
    }

    $statement = $pdo->prepare(
        "SELECT id FROM users WHERE id=:user_id AND status='active' LIMIT 1"
    );
    $statement->bindValue(':user_id', $sessionUserId, PDO::PARAM_INT);
    $statement->execute();
    $userId = $statement->fetchColumn();
    if ($userId === false) {
        unset($_SESSION['user_id']);
        throw new RuntimeException('An active authenticated user is required.');
    }

    return (int) $userId;
}

function notification_get_preference(PDO $pdo): bool
{
    $userId = notification_authenticated_user_id($pdo);
    $statement = $pdo->prepare(
        'SELECT in_app_enabled FROM notification_preferences WHERE user_id=:user_id LIMIT 1'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->execute();
    $preference = $statement->fetchColumn();

    return $preference === false || (int) $preference === 1;
}

function notification_set_preference(PDO $pdo, bool $enabled): void
{
    $userId = notification_authenticated_user_id($pdo);
    $statement = $pdo->prepare(
        'INSERT INTO notification_preferences (user_id,in_app_enabled)
         VALUES (:user_id,:enabled)
         ON DUPLICATE KEY UPDATE in_app_enabled=VALUES(in_app_enabled)'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->bindValue(':enabled', $enabled ? 1 : 0, PDO::PARAM_INT);
    $statement->execute();
}

function notification_create(
    PDO $pdo,
    int $recipientUserId,
    string $type,
    string $title,
    string $body,
    ?string $link = null
): ?int {
    if ($recipientUserId < 1 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/', $type)) {
        throw new InvalidArgumentException('The notification recipient or type is invalid.');
    }
    if ($title === '' || notification_text_length($title) > 160
        || $body === '' || notification_text_length($body) > 5000) {
        throw new InvalidArgumentException('The notification title or body is invalid.');
    }
    if ($link !== null && (strlen($link) > 2048
        || preg_match('/[\x00-\x20\x7f\\\\]/', $link)
        || preg_match('/^[a-z][a-z0-9+.-]*:/i', $link)
        || str_starts_with($link, '//'))) {
        throw new InvalidArgumentException('Notification links must be relative application paths.');
    }

    $recipient = $pdo->prepare(
        "SELECT id FROM users WHERE id=:user_id AND status='active' LIMIT 1"
    );
    $recipient->bindValue(':user_id', $recipientUserId, PDO::PARAM_INT);
    $recipient->execute();
    if ($recipient->fetchColumn() === false) {
        throw new InvalidArgumentException('The notification recipient is not an active user.');
    }

    $preference = $pdo->prepare(
        'SELECT in_app_enabled FROM notification_preferences WHERE user_id=:user_id LIMIT 1'
    );
    $preference->bindValue(':user_id', $recipientUserId, PDO::PARAM_INT);
    $preference->execute();
    $inAppEnabled = $preference->fetchColumn();
    if ($inAppEnabled !== false && (int) $inAppEnabled !== 1) {
        return null;
    }

    $statement = $pdo->prepare(
        'INSERT INTO notifications (user_id,type,title,body,link)
         VALUES (:user_id,:type,:title,:body,:link)'
    );
    $statement->execute([
        'user_id' => $recipientUserId,
        'type' => $type,
        'title' => $title,
        'body' => $body,
        'link' => $link,
    ]);

    return (int) $pdo->lastInsertId();
}

function notification_list(PDO $pdo, int $limit = 20, int $offset = 0): array
{
    if ($limit < 1 || $limit > 100 || $offset < 0) {
        throw new InvalidArgumentException('Notification pagination is out of range.');
    }
    if (!notification_get_preference($pdo)) {
        return [];
    }
    $userId = notification_authenticated_user_id($pdo);
    $statement = $pdo->prepare(
        'SELECT id,type,title,body,link,created_at,read_at
         FROM notifications
         WHERE user_id=:user_id
         ORDER BY created_at DESC,id DESC
         LIMIT :limit OFFSET :offset'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function notification_unread_count(PDO $pdo): int
{
    if (!notification_get_preference($pdo)) {
        return 0;
    }
    $userId = notification_authenticated_user_id($pdo);
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM notifications WHERE user_id=:user_id AND read_at IS NULL'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->execute();

    return (int) $statement->fetchColumn();
}

function notification_mark_read(PDO $pdo, int $notificationId): void
{
    if ($notificationId < 1) {
        throw new InvalidArgumentException('The notification ID is invalid.');
    }
    $userId = notification_authenticated_user_id($pdo);
    $statement = $pdo->prepare(
        'UPDATE notifications SET read_at=COALESCE(read_at,NOW())
         WHERE id=:notification_id AND user_id=:user_id'
    );
    $statement->bindValue(':notification_id', $notificationId, PDO::PARAM_INT);
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->execute();
}

function notification_mark_all_read(PDO $pdo): int
{
    $userId = notification_authenticated_user_id($pdo);
    $statement = $pdo->prepare(
        'UPDATE notifications SET read_at=NOW()
         WHERE user_id=:user_id AND read_at IS NULL'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->rowCount();
}

function notification_text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    $length = preg_match_all('/./us', $value);

    return $length === false ? PHP_INT_MAX : $length;
}
