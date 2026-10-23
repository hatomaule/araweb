<?php
declare(strict_types=1);

$filterType = isset($_GET['type']) && is_string($_GET['type'])
    && in_array($_GET['type'], ['support', 'property'], true) ? $_GET['type'] : 'all';
$filterStatus = isset($_GET['status']) && is_string($_GET['status'])
    && in_array($_GET['status'], ['active', 'archived', 'trash', 'all'], true) ? $_GET['status'] : 'active';
$filterSearch = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$conversations = chat_admin_list_conversations($pdo, $filterType, $filterStatus, $filterSearch, (int) $user['id']);
$conversationId = max(0, (int) ($_GET['id'] ?? 0));
$activeConversation = null;
$messages = [];
if ($conversationId > 0) {
    $activeConversation = chat_get_conversation($pdo, $user, $conversationId);
    chat_mark_read($pdo, $user, $conversationId);
    $messages = chat_get_messages($pdo, $user, $conversationId);
}
$chatQuery = http_build_query(array_filter([
    'page' => 'admin_chat',
    'type' => $filterType !== 'all' ? $filterType : null,
    'status' => $filterStatus !== 'active' ? $filterStatus : null,
    'q' => $filterSearch !== '' ? $filterSearch : null,
]));
?>
<div class="welcome-row">
    <div><h2>Kelola percakapan</h2><p>Tinjau semua chat. Arsip dan Sampah hanya mengubah tampilan admin, tanpa mengubah percakapan peserta lain.</p></div>
</div>
<div class="chat-admin-layout">
    <aside class="panel chat-admin-sidebar" aria-label="Filter dan daftar percakapan">
        <form class="chat-admin-filters" method="get" data-auto-submit>
            <input type="hidden" name="page" value="admin_chat">
            <label class="field"><span>Cari akun atau kos</span><input type="search" name="q" value="<?= e($filterSearch) ?>" placeholder="Nama, email, nama kos"></label>
            <label class="field"><span>Jenis percakapan</span><select name="type">
                <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>Semua chat</option>
                <option value="support" <?= $filterType === 'support' ? 'selected' : '' ?>>Bantuan admin</option>
                <option value="property" <?= $filterType === 'property' ? 'selected' : '' ?>>User dan Owner</option>
            </select></label>
            <label class="field"><span>Status</span><select name="status">
                <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Aktif</option>
                <option value="archived" <?= $filterStatus === 'archived' ? 'selected' : '' ?>>Diarsipkan</option>
                <option value="trash" <?= $filterStatus === 'trash' ? 'selected' : '' ?>>Sampah</option>
                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Semua status</option>
            </select></label>
        </form>
        <div class="chat-admin-conversations">
            <?php if ($conversations): foreach ($conversations as $conversation): ?>
                <?php
                $isSupport = $conversation['conversation_type'] === 'support';
                $name = $isSupport
                    ? ($conversation['requester_name'] ?: 'Pengguna')
                    : ($conversation['seeker_name'] ?: 'Pengguna') . ' · ' . ($conversation['building_name'] ?: 'Kos');
                $subtitle = $isSupport
                    ? ucfirst((string) ($conversation['requester_role'] ?: 'user')) . ' · Bantuan admin'
                    : 'User ↔ Owner';
                ?>
                <div class="chat-conversation-entry">
                    <a class="chat-conversation-link <?= (int) $conversation['id'] === $conversationId ? 'active' : '' ?>"
                        href="index.php?<?= e($chatQuery) ?>&amp;id=<?= (int) $conversation['id'] ?>">
                        <span class="chat-avatar"><?= $isSupport ? '?' : e(function_exists('mb_substr') ? mb_substr($name, 0, 1, 'UTF-8') : substr($name, 0, 1)) ?></span>
                        <span><strong><?= e($name) ?></strong><small><?= e($subtitle) ?></small>
                            <?php if ($conversation['last_message']): ?><small class="chat-admin-preview"><?= e($conversation['last_message']) ?></small><?php endif; ?>
                        </span>
                        <?php if ((int) $conversation['unread_count'] > 0): ?><i class="chat-unread-count"><?= (int) $conversation['unread_count'] ?></i><?php endif; ?>
                    </a>
                    <div class="chat-admin-actions">
                        <?php if ($conversation['personal_status'] === 'archived'): ?>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="restore_chat_conversation"><input type="hidden" name="conversation_id" value="<?= (int) $conversation['id'] ?>"><button class="text-button" type="submit">Pulihkan</button></form>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="trash_chat_conversation"><input type="hidden" name="conversation_id" value="<?= (int) $conversation['id'] ?>"><button class="text-button" type="submit">Hapus</button></form>
                        <?php elseif ($conversation['personal_status'] === 'trash'): ?>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="restore_trashed_chat_conversation"><input type="hidden" name="conversation_id" value="<?= (int) $conversation['id'] ?>"><button class="text-button" type="submit">Pulihkan</button></form>
                        <?php else: ?>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="archive_chat_conversation"><input type="hidden" name="conversation_id" value="<?= (int) $conversation['id'] ?>"><button class="text-button" type="submit">Arsipkan</button></form>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="trash_chat_conversation"><input type="hidden" name="conversation_id" value="<?= (int) $conversation['id'] ?>"><button class="text-button" type="submit">Hapus</button></form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <p class="chat-empty">Tidak ada percakapan yang cocok dengan filter ini.</p>
            <?php endif; ?>
        </div>
        <?php if ($filterStatus === 'trash'): ?>
            <form method="post" class="chat-empty-trash-form" data-confirm="Kosongkan Sampah secara permanen? Salinan peserta lain tetap terjaga."><?= csrf_field() ?><input type="hidden" name="action" value="empty_chat_trash"><button class="button danger full" type="submit">Kosongkan Sampah sekarang</button><small>Penghapusan permanen ini tidak dapat dibatalkan; salinan peserta lain tetap terjaga.</small></form>
        <?php endif; ?>
    </aside>
    <section class="panel chat-thread chat-admin-thread">
        <?php if ($activeConversation): ?>
            <?php
            $isSupport = $activeConversation['conversation_type'] === 'support';
            $canAdminReply = $isSupport && $activeConversation['personal_status'] === 'active';
            $threadTitle = $isSupport
                ? ($activeConversation['requester_name'] ?: 'Pengguna')
                : ($activeConversation['seeker_name'] ?: 'Pengguna') . ' · ' . ($activeConversation['building_name'] ?: 'Kos');
            $profile = $isSupport
                ? [
                    'id' => $activeConversation['requester_user_id'],
                    'name' => $activeConversation['requester_name'],
                    'email' => $activeConversation['requester_email'],
                    'username' => $activeConversation['requester_username'],
                    'profile_photo_path' => $activeConversation['requester_photo'],
                    'gender' => $activeConversation['requester_gender'],
                    'avatar_key' => $activeConversation['requester_avatar'],
                    'role' => $activeConversation['requester_role'],
                ]
                : [
                    'id' => $activeConversation['seeker_id'],
                    'name' => $activeConversation['seeker_name'],
                    'email' => $activeConversation['seeker_email'],
                    'username' => $activeConversation['seeker_username'],
                    'profile_photo_path' => $activeConversation['seeker_photo'],
                    'gender' => $activeConversation['seeker_gender'],
                    'avatar_key' => $activeConversation['seeker_avatar'],
                    'role' => 'seeker',
                ];
            ?>
            <header class="chat-thread-heading chat-admin-thread-heading">
                <div class="chat-profile-heading">
                    <button class="chat-profile-trigger" type="button" data-dialog-open="admin-chat-profile-dialog" aria-label="Lihat profil <?= e($profile['name'] ?: 'pengguna') ?>"><?= avatar_markup($profile, 'profile-avatar chat-profile-avatar') ?></button>
                    <div><h2><?= e($threadTitle) ?></h2>
                    <small><?= e($isSupport
                        ? ucfirst((string) ($activeConversation['requester_role'] ?: 'user')) . ' · Bantuan admin'
                        : 'Percakapan privat User dan Owner' . ($activeConversation['room_number'] ? ' · Kamar ' . $activeConversation['room_number'] : '')) ?></small></div>
                </div>
                <div class="chat-admin-actions">
                    <?php if ($activeConversation['personal_status'] === 'active'): ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="archive_chat_conversation"><input type="hidden" name="conversation_id" value="<?= $conversationId ?>"><button class="button secondary" type="submit">Arsipkan</button></form>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="trash_chat_conversation"><input type="hidden" name="conversation_id" value="<?= $conversationId ?>"><button class="button danger" type="submit">Hapus</button></form>
                    <?php elseif ($activeConversation['personal_status'] === 'archived'): ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="restore_chat_conversation"><input type="hidden" name="conversation_id" value="<?= $conversationId ?>"><button class="button secondary" type="submit">Pulihkan</button></form>
                    <?php elseif ($activeConversation['personal_status'] === 'trash'): ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="restore_trashed_chat_conversation"><input type="hidden" name="conversation_id" value="<?= $conversationId ?>"><button class="button secondary" type="submit">Pulihkan dari Sampah</button></form>
                    <?php endif; ?>
                </div>
            </header>
            <dialog class="action-dialog chat-profile-dialog" id="admin-chat-profile-dialog">
                <div class="action-dialog-form">
                    <button type="button" class="dialog-close" aria-label="Tutup" data-dialog-close>×</button>
                    <div class="chat-profile-card">
                        <?= avatar_markup($profile, 'profile-avatar large') ?>
                        <div><span class="dialog-eyebrow">PROFIL <?= $profile['role'] === 'owner' ? 'OWNER' : 'PENGGUNA' ?></span>
                            <h2><?= e($profile['name'] ?: 'Pengguna') ?></h2>
                            <p>ID akun: <?= (int) $profile['id'] ?></p>
                            <p>Username: <?= e($profile['username'] ? '@' . $profile['username'] : '—') ?></p>
                            <p>Email: <?= e($profile['email'] ?: '—') ?></p>
                        </div>
                    </div>
                    <div class="dialog-actions"><button class="button secondary" type="button" data-dialog-close>Tutup</button><a class="button primary" href="index.php?page=chat_profile&amp;conversation_id=<?= $conversationId ?>&amp;status=<?= e($filterStatus) ?>">Lihat profil</a></div>
                </div>
            </dialog>
            <?php if (!$isSupport): ?><p class="chat-admin-readonly-note">Chat ini hanya dapat ditinjau admin. Balasan tetap dilakukan oleh User dan Owner.</p><?php endif; ?>
            <?php if ($activeConversation['personal_status'] !== 'active'): ?><p class="chat-admin-readonly-note">Chat ini berada di <?= $activeConversation['personal_status'] === 'trash' ? 'Sampah' : 'Arsip' ?> admin. Status percakapan peserta lain tidak berubah.</p><?php endif; ?>
            <div class="chat-message-list chat-admin-message-list" aria-live="polite">
                <?php foreach ($messages as $message): ?>
                    <article class="chat-message <?= (int) $message['sender_user_id'] === (int) $user['id'] ? 'mine' : '' ?>">
                        <strong><?= e($message['sender_name']) ?> · <?= e(['admin' => 'Admin', 'owner' => 'Owner', 'seeker' => 'User'][$message['sender_role']] ?? $message['sender_role']) ?></strong>
                        <p><?= nl2br(e($message['body'])) ?></p><small><?= e(date_id($message['created_at'])) ?></small>
                    </article>
                <?php endforeach; ?>
                <?php if (!$messages): ?><p class="chat-empty">Belum ada pesan di percakapan ini.</p><?php endif; ?>
            </div>
            <?php if ($canAdminReply): ?>
                <form class="chat-compose" method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="send_chat_message">
                    <input type="hidden" name="conversation_id" value="<?= $conversationId ?>">
                    <label class="field"><span>Balas permintaan bantuan</span><textarea name="message" rows="2" maxlength="4000" required placeholder="Tulis balasan..."></textarea></label>
                    <button class="button primary" type="submit">Kirim</button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <div class="chat-thread-empty"><span>✉</span><h2>Pilih percakapan</h2><p>Gunakan filter di sebelah kiri untuk meninjau chat bantuan atau percakapan properti.</p></div>
        <?php endif; ?>
    </section>
</div>
