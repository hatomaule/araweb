<?php
if ($user && in_array($user['role'], ['seeker', 'owner', 'admin'], true)):
    $chatWidgetUnreadCount = chat_unread_count($pdo, $user);
?>
<div class="chat-widget" data-chat-widget data-chat-role="<?= e($user['role']) ?>"
    data-chat-csrf="<?= e(csrf_token()) ?>" data-chat-user-id="<?= (int) $user['id'] ?>">
    <button class="chat-widget-launcher" type="button" data-chat-toggle aria-expanded="false"
        aria-controls="chat-widget-panel">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.75A2.75 2.75 0 0 1 6.75 3h10.5A2.75 2.75 0 0 1 20 5.75v8.5A2.75 2.75 0 0 1 17.25 17H10l-5.5 4v-4.3A2.75 2.75 0 0 1 4 14.5Z"></path></svg>
        <span>Chat</span><i class="chat-widget-badge" data-chat-total-unread
            <?= $chatWidgetUnreadCount > 0 ? '' : 'hidden' ?>><?= $chatWidgetUnreadCount > 0 ? e($chatWidgetUnreadCount) : '' ?></i>
    </button>
    <section class="chat-widget-panel" id="chat-widget-panel" aria-label="Chat KosKosan" hidden>
        <header class="chat-widget-header">
            <div><strong>Chat</strong><small data-chat-status>Memuat percakapan...</small></div>
            <div class="chat-widget-header-actions">
                <a href="index.php?page=<?= $user['role'] === 'admin' ? 'admin_chat' : 'chat' ?>"
                    aria-label="Buka halaman chat penuh" title="Buka halaman penuh">↗</a>
                <button type="button" data-chat-close aria-label="Tutup chat">×</button>
            </div>
        </header>
        <div class="chat-widget-body">
            <aside class="chat-widget-list-pane" aria-label="Daftar percakapan">
                <label class="chat-widget-search"><span class="visually-hidden">Cari percakapan</span>
                    <input type="search" data-chat-search placeholder="Cari nama">
                </label>
                <nav class="chat-widget-folders" aria-label="Folder chat">
                    <button type="button" data-chat-folder="inbox" aria-pressed="true">Kotak masuk</button>
                    <button type="button" data-chat-folder="archive" aria-pressed="false">Arsip</button>
                    <button type="button" data-chat-folder="trash" aria-pressed="false">Sampah</button>
                </nav>
                <button type="button" class="chat-widget-empty-trash" data-chat-empty-trash hidden>Kosongkan Sampah sekarang</button>
                <?php if ($user['role'] !== 'admin'): ?>
                    <button class="chat-widget-support" type="button" data-chat-start-support>
                        <span aria-hidden="true">?</span><span><strong>Bantuan KosKosan</strong><small>Hubungi admin</small></span>
                    </button>
                    <?php if ($adminWhatsappUrl = site_contact_whatsapp_url('Halo Admin KosKosan, saya memerlukan bantuan.')): ?><a class="chat-widget-support chat-widget-whatsapp" href="<?= e($adminWhatsappUrl) ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">↗</span><span><strong>WhatsApp Admin</strong><small>Buka WhatsApp / WhatsApp Web</small></span></a><?php endif; ?>
                <?php endif; ?>
                <div class="chat-widget-conversations" data-chat-conversations aria-live="polite"></div>
            </aside>
            <section class="chat-widget-thread">
                <header class="chat-widget-thread-header" data-chat-thread-header>
                    <button type="button" data-chat-back aria-label="Kembali ke daftar chat">‹</button>
                    <div class="chat-widget-thread-identity" data-chat-thread-identity><strong>Pilih percakapan</strong><small>Pesan tersimpan aman di akun masing-masing.</small></div>
                    <div class="chat-widget-thread-actions" data-chat-thread-actions></div>
                </header>
                <div class="chat-widget-messages" data-chat-messages aria-live="polite">
                    <div class="chat-widget-empty">Pilih percakapan di sebelah kiri untuk mulai chat.</div>
                </div>
                <form class="chat-widget-compose" data-chat-compose hidden>
                    <label class="visually-hidden" for="chat-widget-message">Tulis pesan</label>
                    <textarea id="chat-widget-message" name="message" rows="2" maxlength="4000"
                        placeholder="Tulis pesan..." required></textarea>
                    <button type="submit" aria-label="Kirim pesan">Kirim</button>
                </form>
                <p class="chat-widget-readonly" data-chat-readonly hidden></p>
            </section>
        </div>
    </section>
</div>
<?php endif; ?>
