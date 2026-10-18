<?php
declare(strict_types=1);

$conversations = chat_list_conversations($pdo, $user);
$conversationId = max(0, (int) ($_GET['id'] ?? 0));
$activeConversation = null;
$messages = [];
if ($conversationId > 0) {
    $activeConversation = chat_get_conversation($pdo, $user, $conversationId);
    chat_mark_read($pdo, $user, $conversationId);
    $messages = chat_get_messages($pdo, $user, $conversationId);
}
?>
<div class="welcome-row"><div><h2>Chat <?= $user['role'] === 'operator' ? 'dengan pengguna' : 'pemilik kos' ?></h2><p>Percakapan tersimpan aman di akun masing-masing.</p></div></div>
<div class="chat-layout">
    <aside class="panel chat-conversation-list" aria-label="Daftar percakapan">
        <?php if ($conversations): foreach ($conversations as $conversation): ?>
            <?php $otherParty = $user['role'] === 'seeker' ? $conversation['building_name'] : $conversation['seeker_name']; ?>
            <a class="chat-conversation-link <?= (int) $conversation['id'] === $conversationId ? 'active' : '' ?>" href="index.php?page=chat&amp;id=<?= (int) $conversation['id'] ?>">
                <span class="chat-avatar"><?= e(function_exists('mb_substr') ? mb_substr($otherParty, 0, 1, 'UTF-8') : substr($otherParty, 0, 1)) ?></span>
                <span><strong><?= e($otherParty) ?></strong><small><?= e($conversation['room_number'] ? 'Kamar ' . $conversation['room_number'] : 'Percakapan kos') ?></small></span>
                <?php if ((int) $conversation['unread_count']): ?><i class="chat-unread-count"><?= (int) $conversation['unread_count'] ?></i><?php endif; ?>
            </a>
        <?php endforeach; else: ?><p class="chat-empty">Belum ada percakapan. Mulai chat dari profil kos.</p><?php endif; ?>
    </aside>
    <section class="panel chat-thread">
        <?php if ($activeConversation): ?>
            <header class="chat-thread-heading"><div><h2><?= e($user['role'] === 'seeker' ? $activeConversation['building_name'] : $activeConversation['seeker_name']) ?></h2><small><?= e($activeConversation['room_number'] ? 'Kamar ' . $activeConversation['room_number'] : $activeConversation['building_name']) ?></small></div></header>
            <div class="chat-message-list" aria-live="polite">
                <?php foreach ($messages as $message): ?><article class="chat-message <?= (int) $message['sender_user_id'] === (int) $user['id'] ? 'mine' : '' ?>"><p><?= nl2br(e($message['body'])) ?></p><small><?= e(date_id($message['created_at'])) ?></small></article><?php endforeach; ?>
                <?php if (!$messages): ?><p class="chat-empty">Belum ada pesan.</p><?php endif; ?>
            </div>
            <form class="chat-compose" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send_chat_message"><input type="hidden" name="conversation_id" value="<?= $conversationId ?>"><label class="field"><span>Pesan</span><textarea name="message" rows="2" maxlength="4000" required placeholder="Tulis pesan..."></textarea></label><button class="button primary" type="submit">Kirim</button></form>
        <?php else: ?><div class="chat-thread-empty"><span>✉</span><h2>Pilih percakapan</h2><p>Pilih percakapan di samping untuk membaca dan mengirim pesan.</p></div><?php endif; ?>
    </section>
</div>
