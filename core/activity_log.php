<?php
declare(strict_types=1);

function activity_log_record(
    PDO $pdo,
    ?array $actor,
    string $category,
    string $action,
    string $summary,
    string $outcome = 'success',
    ?string $page = null,
    ?string $entityType = null,
    ?int $entityId = null,
    ?int $buildingId = null
): void {
    if (!in_array($category, ['request', 'auth', 'business', 'system'], true)
        || !in_array($outcome, ['success', 'error'], true)) {
        throw new InvalidArgumentException('Invalid activity log category or outcome.');
    }

    $actorId = isset($actor['id']) ? (int) $actor['id'] : null;
    $actorRole = $actor['role'] ?? 'system';
    if (!in_array($actorRole, ['admin', 'owner', 'seeker', 'system'], true)) {
        $actorRole = 'system';
        $actorId = null;
    }

    $statement = $pdo->prepare(
        'INSERT INTO activity_logs
            (actor_user_id,actor_role,category,action,outcome,page,entity_type,entity_id,building_id,summary)
         VALUES
            (:actor_user_id,:actor_role,:category,:action,:outcome,:page,:entity_type,:entity_id,:building_id,:summary)'
    );
    $statement->execute([
        'actor_user_id' => $actorId,
        'actor_role' => $actorRole,
        'category' => substr($category, 0, 32),
        'action' => substr($action, 0, 80),
        'outcome' => $outcome,
        'page' => $page !== null ? substr($page, 0, 80) : null,
        'entity_type' => $entityType !== null ? substr($entityType, 0, 40) : null,
        'entity_id' => $entityId,
        'building_id' => $buildingId,
        'summary' => substr($summary, 0, 240),
    ]);
}

function activity_log_set_error(PDO $pdo, ?array $actor, string $action, ?string $page = null): void
{
    try {
        activity_log_record(
            $pdo,
            $actor,
            'system',
            $action,
            'Permintaan gagal diproses.',
            'error',
            $page
        );
    } catch (PDOException $exception) {
        error_log('Activity error log write failed: ' . $exception->getMessage());
    }
}

function activity_log_request(PDO $pdo, ?array $actor, string $page): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    register_shutdown_function(static function () use ($pdo, $actor, $page): void {
        $postedAction = $_POST['action'] ?? $_POST['chat_action'] ?? $_POST['notification_action'] ?? null;
        $action = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
            ? (is_string($postedAction)
                ? preg_replace('/[^a-zA-Z0-9_-]/', '', $postedAction) : 'form_submit')
            : 'page_view';
        $lastError = error_get_last();
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        $loginFailed = $action === 'login' && ($_SESSION['flash']['type'] ?? null) === 'error';
        $failed = $loginFailed || http_response_code() >= 400
            || ($lastError && in_array($lastError['type'], $fatalTypes, true));
        $category = $loginFailed ? 'auth'
            : ($failed ? 'system' : (in_array($action, ['login', 'logout'], true)
                ? 'auth' : ($action === 'page_view' ? 'request' : 'business')));
        $summary = $failed
            ? 'Permintaan gagal diproses oleh aplikasi.'
            : ($action === 'page_view' ? 'Membuka halaman.' : 'Mengirim tindakan aplikasi.');

        try {
            $logActor = $loginFailed && !$actor ? ['role' => 'seeker']
                : ($action === 'login' && !$actor ? current_user() : $actor);
            $logAction = $loginFailed ? 'login_failed' : ($action !== '' ? $action : 'unknown');
            $buildingId = $logActor && isset($logActor['id'])
                ? activity_log_building_for_request($pdo, $logActor) : null;
            activity_log_record(
                $pdo,
                $logActor,
                $category,
                $logAction,
                $summary,
                $failed ? 'error' : 'success',
                $page,
                null,
                null,
                $buildingId
            );
        } catch (PDOException $exception) {
            error_log('Activity request log write failed: ' . $exception->getMessage());
        }
    });
}

function activity_log_current_request_error(string $action = 'application_error'): void
{
    try {
        $pdo = db();
        activity_log_set_error(
            $pdo,
            function_exists('current_user') ? current_user() : null,
            $action,
            isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : 'home'
        );
    } catch (Throwable $exception) {
        error_log('Unable to write application error to activity log: ' . $exception->getMessage());
    }
}

function activity_log_building_for_request(PDO $pdo, array $user): ?int
{
    if (($user['role'] ?? null) !== 'owner') {
        return null;
    }

    $buildingId = filter_var(
        $_POST['building_id'] ?? $_GET['building_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $entityIds = [
        'building_id' => filter_var(
            $_POST['building_id'] ?? $_GET['building_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ),
        'room_id' => filter_var(
            $_POST['room_id'] ?? $_GET['room_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ),
        'rental_id' => filter_var(
            $_POST['rental_id'] ?? $_GET['rental_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ),
        'invoice_id' => filter_var(
            $_POST['invoice_id'] ?? $_GET['invoice_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ),
        'payment_id' => filter_var(
            $_POST['payment_id'] ?? $_GET['payment_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ),
        'tenant_id' => filter_var(
            $_POST['tenant_id'] ?? $_GET['tenant_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ),
        'review_id' => filter_var(
            $_POST['review_id'] ?? $_GET['review_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ),
        'conversation_id' => filter_var(
            $_POST['conversation_id'] ?? $_GET['conversation_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ),
    ];
    $entityQueries = [
        'building_id' => 'SELECT id FROM kos_buildings WHERE id=:entity_id AND owner_user_id=:owner_id',
        'room_id' => 'SELECT b.id FROM rooms r JOIN kos_buildings b ON b.id=r.building_id
                      WHERE r.id=:entity_id AND b.owner_user_id=:owner_id',
        'rental_id' => 'SELECT b.id FROM rentals r JOIN rooms rm ON rm.id=r.room_id
                        JOIN kos_buildings b ON b.id=rm.building_id
                        WHERE r.id=:entity_id AND b.owner_user_id=:owner_id',
        'invoice_id' => 'SELECT b.id FROM invoices i JOIN rentals r ON r.id=i.rental_id
                         JOIN rooms rm ON rm.id=r.room_id JOIN kos_buildings b ON b.id=rm.building_id
                         WHERE i.id=:entity_id AND b.owner_user_id=:owner_id',
        'payment_id' => 'SELECT b.id FROM payments p JOIN invoices i ON i.id=p.invoice_id
                         JOIN rentals r ON r.id=i.rental_id JOIN rooms rm ON rm.id=r.room_id
                         JOIN kos_buildings b ON b.id=rm.building_id
                         WHERE p.id=:entity_id AND b.owner_user_id=:owner_id',
        'tenant_id' => 'SELECT b.id FROM rentals r JOIN rooms rm ON rm.id=r.room_id
                        JOIN kos_buildings b ON b.id=rm.building_id
                        WHERE r.tenant_id=:entity_id AND b.owner_user_id=:owner_id
                        ORDER BY r.id DESC LIMIT 1',
        'review_id' => 'SELECT b.id FROM property_reviews pr JOIN kos_buildings b ON b.id=pr.building_id
                        WHERE pr.id=:entity_id AND b.owner_user_id=:owner_id',
        'conversation_id' => 'SELECT b.id FROM chat_conversations c JOIN kos_buildings b ON b.id=c.building_id
                             WHERE c.id=:entity_id AND b.owner_user_id=:owner_id',
    ];
    foreach ($entityQueries as $key => $query) {
        $entityId = $entityIds[$key];
        if ($entityId === false || $entityId === null) {
            continue;
        }
        $statement = $pdo->prepare($query);
        $statement->execute(['entity_id' => $entityId, 'owner_id' => $user['id']]);
        $ownedBuildingId = $statement->fetchColumn();
        if ($ownedBuildingId !== false) {
            return (int) $ownedBuildingId;
        }
    }

    return null;
}
