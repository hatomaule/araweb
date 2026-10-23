<?php
declare(strict_types=1);

$conversationId = max(0, (int) ($_GET['conversation_id'] ?? 0));
$folder = isset($_GET['folder']) && is_string($_GET['folder'])
    && in_array($_GET['folder'], ['inbox', 'archive', 'trash'], true) ? $_GET['folder'] : 'inbox';
$status = isset($_GET['status']) && is_string($_GET['status'])
    && in_array($_GET['status'], ['active', 'archived', 'trash', 'all'], true) ? $_GET['status'] : 'active';
$conversation = $conversationId > 0 ? chat_get_conversation($pdo, $user, $conversationId) : null;
$profileUserId = null;
if ($conversation) {
    if ($user['role'] === 'admin') {
        $profileUserId = $conversation['conversation_type'] === 'support'
            ? (int) $conversation['requester_user_id']
            : (int) $conversation['seeker_id'];
    } elseif ($conversation['conversation_type'] === 'property') {
        $profileUserId = $user['role'] === 'owner'
            ? (int) $conversation['seeker_id']
            : (int) $conversation['operator_id'];
    }
}
$profile = $profileUserId ? one(
    $pdo,
    "SELECT id,name,email,username,profile_photo_path,gender,avatar_key,role
     FROM users WHERE id=:id AND role IN ('owner','seeker')",
    ['id' => $profileUserId]
) : null;
if (!$profile) {
    http_response_code(404);
    echo '<div class="alert error">Profil tidak tersedia untuk percakapan ini.</div>';
    return;
}
$backUrl = $user['role'] === 'admin'
    ? 'index.php?page=admin_chat&status=' . rawurlencode($status)
    : 'index.php?page=chat&folder=' . rawurlencode($folder);
?>
<section class="panel chat-profile-page">
    <div class="chat-profile-card">
        <?= avatar_markup($profile, 'profile-avatar large') ?>
        <div><span class="dialog-eyebrow">PROFIL <?= $profile['role'] === 'owner' ? 'OWNER' : 'PENGGUNA' ?></span>
            <h2><?= e($profile['name']) ?></h2>
            <p>ID akun: <?= (int) $profile['id'] ?></p>
            <p>Username: <?= e($profile['username'] ? '@' . $profile['username'] : '—') ?></p>
            <p>Email: <?= e($profile['email'] ?: '—') ?></p>
        </div>
    </div>
    <div class="form-actions"><a class="button secondary" href="<?= e($backUrl) ?>&amp;id=<?= $conversationId ?>">Kembali ke chat</a></div>
</section>
