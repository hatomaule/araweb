<?php
declare(strict_types=1);

$conversationId = max(0, (int) ($_GET['id'] ?? 0));
$folder = isset($_GET['folder']) && is_string($_GET['folder'])
    && in_array($_GET['folder'], ['inbox', 'archive', 'trash'], true) ? $_GET['folder'] : 'inbox';
$supportError = null;
if (isset($_GET['support']) && $_GET['support'] === '1') {
    try {
        $conversationId = chat_start_support_conversation($pdo, $user);
    } catch (ChatAccessDeniedException $exception) {
        $supportError = 'Percakapan bantuan tidak dapat dibuka saat ini.';
    }
}
$conversations = chat_list_conversations($pdo, $user, $folder);
$activeConversation = null;
$messages = [];
if ($conversationId > 0) {
    $activeConversation = chat_get_conversation($pdo, $user, $conversationId);
    chat_mark_read($pdo, $user, $conversationId);
    $messages = chat_get_messages($pdo, $user, $conversationId);
}
?>
<div class="welcome-row"><div><h2>Chat <?= $user['role'] === 'owner' ? 'dengan pengguna' : 'pemilik kos' ?></h2><p>Arsip dan Sampah hanya mengubah tampilan Anda; peserta lain tetap memiliki percakapannya.</p></div></div>
<?php if ($supportError): ?><div class="alert error"><?= e($supportError) ?></div><?php endif; ?>
<div class="chat-layout">
    <aside class="panel chat-conversation-list" aria-label="Daftar percakapan">
        <nav class="chat-folder-nav" aria-label="Folder chat">
            <a class="<?= $folder === 'inbox' ? 'active' : '' ?>" href="index.php?page=chat&amp;folder=inbox">Kotak masuk</a>
            <a class="<?= $folder === 'archive' ? 'active' : '' ?>" href="index.php?page=chat&amp;folder=archive">Arsip</a>
            <a class="<?= $folder === 'trash' ? 'active' : '' ?>" href="index.php?page=chat&amp;folder=trash">Sampah</a>
        </nav>
        <?php if ($conversations): foreach ($conversations as $conversation): ?>
            <?php
            $isSupport = $conversation['conversation_type'] === 'support';
            $otherParty = $isSupport
                ? 'Bantuan KosKosan'
                : ($user['role'] === 'seeker' ? $conversation['building_name'] : $conversation['seeker_name']);
            ?>
            <div class="chat-conversation-entry">
                <a class="chat-conversation-link <?= (int) $conversation['id'] === $conversationId ? 'active' : '' ?>" href="index.php?page=chat&amp;folder=<?= e($folder) ?>&amp;id=<?= (int) $conversation['id'] ?>">
                    <span class="chat-avatar"><?= $isSupport ? '?' : e(function_exists('mb_substr') ? mb_substr((string) $otherParty, 0, 1, 'UTF-8') : substr((string) $otherParty, 0, 1)) ?></span>
                    <span><strong><?= e($otherParty) ?></strong><small><?= e($isSupport ? 'Bantuan admin' : ($conversation['room_number'] ? 'Kamar ' . $conversation['room_number'] : 'Percakapan kos')) ?></small></span>
                    <?php if ((int) $conversation['unread_count']): ?><i class="chat-unread-count"><?= (int) $conversation['unread_count'] ?></i><?php endif; ?>
                </a>
                <div class="chat-conversation-actions">
                    <?php if ($folder === 'inbox'): ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="archive_chat_conversation"><input type="hidden" name="conversation_id" value="<?= (int) $conversation['id'] ?>"><button class="text-button" type="submit">Arsipkan</button></form>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="trash_chat_conversation"><input type="hidden" name="conversation_id" value="<?= (int) $conversation['id'] ?>"><button class="text-button" type="submit">Hapus</button></form>
                    <?php elseif ($folder === 'archive'): ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="restore_chat_conversation"><input type="hidden" name="conversation_id" value="<?= (int) $conversation['id'] ?>"><button class="text-button" type="submit">Pulihkan</button></form>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="trash_chat_conversation"><input type="hidden" name="conversation_id" value="<?= (int) $conversation['id'] ?>"><button class="text-button" type="submit">Hapus</button></form>
                    <?php else: ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="restore_trashed_chat_conversation"><input type="hidden" name="conversation_id" value="<?= (int) $conversation['id'] ?>"><button class="text-button" type="submit">Pulihkan</button></form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; else: ?><p class="chat-empty">Belum ada percakapan.</p><?php endif; ?>
        <?php if ($folder === 'trash'): ?><form method="post" class="chat-empty-trash-form" data-confirm="Kosongkan Sampah secara permanen? Salinan peserta lain tetap terjaga."><?= csrf_field() ?><input type="hidden" name="action" value="empty_chat_trash"><button class="button danger full" type="submit">Kosongkan Sampah sekarang</button><small>Penghapusan permanen tidak dapat dibatalkan. Salinan peserta lain tetap terjaga.</small></form><?php endif; ?>
        <form method="post" class="chat-start-support"><?= csrf_field() ?><input type="hidden" name="action" value="start_support_conversation"><button class="button secondary full" type="submit">Hubungi admin melalui chat sistem</button></form>
        <?php if ($adminWhatsappUrl = site_contact_whatsapp_url('Halo Admin KosKosan, saya memerlukan bantuan.')): ?><a class="button secondary full chat-admin-whatsapp" href="<?= e($adminWhatsappUrl) ?>" target="_blank" rel="noopener noreferrer">WhatsApp Admin ↗</a><?php endif; ?>
    </aside>
    <section class="panel chat-thread">
        <?php if ($activeConversation): ?>
            <?php $activeSupport = $activeConversation['conversation_type'] === 'support'; ?>
            <?php
            $profilePrefix = $user['role'] === 'seeker' ? 'operator' : 'seeker';
            $profile = $activeSupport ? null : [
                'id' => $activeConversation[$profilePrefix . '_id'],
                'name' => $activeConversation[$profilePrefix . '_name'],
                'email' => $activeConversation[$profilePrefix . '_email'],
                'username' => $activeConversation[$profilePrefix . '_username'],
                'profile_photo_path' => $activeConversation[$profilePrefix . '_photo'],
                'gender' => $activeConversation[$profilePrefix . '_gender'],
                'avatar_key' => $activeConversation[$profilePrefix . '_avatar'],
            ];
            $profileLink = $profile
                ? 'index.php?page=chat_profile&conversation_id=' . $conversationId . '&folder=' . rawurlencode($folder)
                : '';
            ?>
            <header class="chat-thread-heading">
                <div class="chat-profile-heading">
                    <?php if ($profile): ?><button class="chat-profile-trigger" type="button" data-dialog-open="chat-profile-dialog" aria-label="Lihat profil <?= e($profile['name']) ?>"><?= avatar_markup($profile, 'profile-avatar chat-profile-avatar') ?></button><?php endif; ?>
                    <div><h2><?= e($activeSupport ? 'Bantuan KosKosan' : ($user['role'] === 'seeker' ? $activeConversation['building_name'] : $activeConversation['seeker_name'])) ?></h2><small><?= e($activeSupport ? 'Dukungan admin' : ($activeConversation['room_number'] ? 'Kamar ' . $activeConversation['room_number'] : $activeConversation['building_name'])) ?></small></div>
                </div>
            </header>
            <?php if ($profile): ?><dialog class="action-dialog chat-profile-dialog" id="chat-profile-dialog">
                <div class="action-dialog-form">
                    <button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button>
                    <div class="chat-profile-card">
                        <?= avatar_markup($profile, 'profile-avatar large') ?>
                        <div><span class="dialog-eyebrow">PROFIL <?= $user['role'] === 'seeker' ? 'OWNER' : 'PENGGUNA' ?></span>
                            <h2><?= e($profile['name']) ?></h2>
                            <p>ID akun: <?= (int) $profile['id'] ?></p>
                            <p>Username: <?= e($profile['username'] ? '@' . $profile['username'] : '—') ?></p>
                            <p>Email: <?= e($profile['email'] ?: '—') ?></p>
                        </div>
                    </div>
                    <div class="dialog-actions"><button class="button secondary" type="button" data-dialog-close>Tutup</button><a class="button primary" href="<?= e($profileLink) ?>">Lihat profil</a></div>
                </div>
            </dialog><?php endif; ?>
            <div class="chat-message-list" aria-live="polite">
                <?php foreach ($messages as $message): ?><article class="chat-message <?= (int) $message['sender_user_id'] === (int) $user['id'] ? 'mine' : '' ?>"><p><?= nl2br(e($message['body'])) ?></p><div class="chat-message-meta"><small><?= e(date_id($message['created_at'])) ?></small><?= chat_delivery_status_markup($message, $activeConversation, $user) ?></div></article><?php endforeach; ?>
                <?php if (!$messages): ?><p class="chat-empty">Belum ada pesan.</p><?php endif; ?>
            </div>
            <?php if ($activeConversation['personal_status'] === 'active'): ?>
                <form class="chat-compose" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send_chat_message"><input type="hidden" name="conversation_id" value="<?= $conversationId ?>"><label class="field"><span>Pesan</span><textarea name="message" rows="2" maxlength="4000" required placeholder="Tulis pesan..."></textarea></label><button class="button primary" type="submit">Kirim</button></form>
            <?php else: ?><p class="chat-admin-readonly-note">Pulihkan percakapan ke Kotak masuk untuk mengirim pesan.</p><?php endif; ?>
        <?php else: ?><div class="chat-thread-empty"><span>✉</span><h2>Pilih percakapan</h2><p>Pilih percakapan di samping untuk membaca dan mengirim pesan.</p></div><?php endif; ?>
    </section>
</div>
