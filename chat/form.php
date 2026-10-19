<?php
declare(strict_types=1);

$conversationId = max(0, (int) ($_GET['id'] ?? 0));
$supportError = null;
if (isset($_GET['support']) && $_GET['support'] === '1') {
    try {
        $conversationId = chat_start_support_conversation($pdo, $user);
    } catch (ChatAccessDeniedException $exception) {
        $supportError = 'Percakapan bantuan sedang diarsipkan Admin. Minta Admin memulihkannya sebelum mengirim pesan.';
    }
}
$conversations = chat_list_conversations($pdo, $user);
$activeConversation = null;
$messages = [];
if ($conversationId > 0) {
    $activeConversation = chat_get_conversation($pdo, $user, $conversationId);
    chat_mark_read($pdo, $user, $conversationId);
    $messages = chat_get_messages($pdo, $user, $conversationId);
}
?>
<div class="welcome-row"><div><h2>Chat <?= $user['role'] === 'owner' ? 'dengan pengguna' : 'pemilik kos' ?></h2><p>Percakapan tersimpan aman di akun masing-masing.</p></div></div>
<?php if ($supportError): ?><div class="alert error"><?= e($supportError) ?></div><?php endif; ?>
<div class="chat-layout">
    <aside class="panel chat-conversation-list" aria-label="Daftar percakapan">
        <?php if ($conversations): foreach ($conversations as $conversation): ?>
            <?php
            $isSupport = $conversation['conversation_type'] === 'support';
            $otherParty = $isSupport
                ? 'Bantuan KosKosan'
                : ($user['role'] === 'seeker' ? $conversation['building_name'] : $conversation['seeker_name']);
            ?>
            <a class="chat-conversation-link <?= (int) $conversation['id'] === $conversationId ? 'active' : '' ?>" href="index.php?page=chat&amp;id=<?= (int) $conversation['id'] ?>">
                <span class="chat-avatar"><?= $isSupport ? '?' : e(function_exists('mb_substr') ? mb_substr((string) $otherParty, 0, 1, 'UTF-8') : substr((string) $otherParty, 0, 1)) ?></span>
                <span><strong><?= e($otherParty) ?></strong><small><?= e($isSupport ? 'Bantuan admin' : ($conversation['room_number'] ? 'Kamar ' . $conversation['room_number'] : 'Percakapan kos')) ?></small></span>
                <?php if ((int) $conversation['unread_count']): ?><i class="chat-unread-count"><?= (int) $conversation['unread_count'] ?></i><?php endif; ?>
            </a>
        <?php endforeach; else: ?><p class="chat-empty">Belum ada percakapan.</p><?php endif; ?>
        <form method="post" class="chat-start-support"><?= csrf_field() ?><input type="hidden" name="action" value="start_support_conversation"><button class="button secondary full" type="submit">Hubungi admin untuk bantuan</button></form>
    </aside>
    <section class="panel chat-thread">
        <?php if ($activeConversation): ?>
            <?php $activeSupport = $activeConversation['conversation_type'] === 'support'; ?>
            <header class="chat-thread-heading"><div><h2><?= e($activeSupport ? 'Bantuan KosKosan' : ($user['role'] === 'seeker' ? $activeConversation['building_name'] : $activeConversation['seeker_name'])) ?></h2><small><?= e($activeSupport ? 'Dukungan admin' : ($activeConversation['room_number'] ? 'Kamar ' . $activeConversation['room_number'] : $activeConversation['building_name'])) ?></small></div></header>
            <div class="chat-message-list" aria-live="polite">
                <?php foreach ($messages as $message): ?><article class="chat-message <?= (int) $message['sender_user_id'] === (int) $user['id'] ? 'mine' : '' ?>"><p><?= nl2br(e($message['body'])) ?></p><small><?= e(date_id($message['created_at'])) ?></small></article><?php endforeach; ?>
                <?php if (!$messages): ?><p class="chat-empty">Belum ada pesan.</p><?php endif; ?>
            </div>
            <form class="chat-compose" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send_chat_message"><input type="hidden" name="conversation_id" value="<?= $conversationId ?>"><label class="field"><span>Pesan</span><textarea name="message" rows="2" maxlength="4000" required placeholder="Tulis pesan..." <?= $activeConversation['archived_at'] !== null ? 'disabled' : '' ?>></textarea></label><button class="button primary" type="submit" <?= $activeConversation['archived_at'] !== null ? 'disabled' : '' ?>>Kirim</button></form>
        <?php else: ?><div class="chat-thread-empty"><span>✉</span><h2>Pilih percakapan</h2><p>Pilih percakapan di samping untuk membaca dan mengirim pesan.</p></div><?php endif; ?>
    </section>
</div>
