const initializeApp = () => {
    document.querySelectorAll('form[data-auto-submit]').forEach((form) => {
        let submitTimer = 0;
        form.querySelectorAll('select').forEach((select) => {
            select.addEventListener('change', () => {
                window.clearTimeout(submitTimer);
                form.requestSubmit();
            });
        });
        form.querySelectorAll('input[type="search"]').forEach((input) => {
            input.addEventListener('input', () => {
                window.clearTimeout(submitTimer);
                submitTimer = window.setTimeout(() => form.requestSubmit(), 350);
            });
        });
    });
    document.querySelectorAll('.chat-compose textarea, [data-chat-compose] textarea').forEach((textarea) => {
        textarea.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;
            event.preventDefault();
            if (textarea.value.trim()) textarea.closest('form')?.requestSubmit();
        });
    });
    document.querySelectorAll('[data-expense-type]').forEach((select) => {
        const form = select.closest('form');
        const routineField = form?.querySelector('[data-expense-routine]');
        const otherField = form?.querySelector('[data-expense-other]');
        const routineSelect = routineField?.querySelector('select');
        const otherInput = otherField?.querySelector('input');
        if (!(select instanceof HTMLSelectElement) || !routineField || !otherField
            || !(routineSelect instanceof HTMLSelectElement) || !(otherInput instanceof HTMLInputElement)) return;
        const update = () => {
            const isRoutine = select.value === 'routine';
            routineField.hidden = !isRoutine;
            otherField.hidden = isRoutine;
            routineSelect.disabled = !isRoutine || routineSelect.options.length < 2;
            routineSelect.required = isRoutine && routineSelect.options.length > 1;
            otherInput.disabled = isRoutine;
            otherInput.required = !isRoutine;
        };
        select.addEventListener('change', update);
        update();
    });
    document.querySelectorAll('[data-print-page]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });
    const tableViewIcons = {
        table: '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 4.5h14M3 8h14M3 11.5h14M3 15h14"/><path d="M6 3v14"/></svg>',
        cards: '<svg viewBox="0 0 20 20" aria-hidden="true"><rect x="3" y="3" width="5.5" height="5.5" rx="1"/><rect x="11.5" y="3" width="5.5" height="5.5" rx="1"/><rect x="3" y="11.5" width="5.5" height="5.5" rx="1"/><rect x="11.5" y="11.5" width="5.5" height="5.5" rx="1"/></svg>',
    };
    const tableViewLabels = { table: 'Tabel', cards: 'Kartu' };
    const createDataViewPicker = (ariaLabel) => {
        const picker = document.createElement('details');
        picker.className = 'data-table-view-switch data-view-picker';
        picker.setAttribute('aria-label', ariaLabel);
        picker.innerHTML = '<summary class="data-view-picker-trigger"><span class="data-view-current-icon" data-view-current-icon aria-hidden="true"></span><span data-view-current-label></span><span class="data-view-picker-chevron" aria-hidden="true">⌄</span></summary><div class="data-view-picker-menu" role="group" aria-label="Pilihan tampilan"></div>';
        const menu = picker.querySelector('.data-view-picker-menu');
        const buttons = new Map();
        Object.entries(tableViewLabels).forEach(([view, label]) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'data-view-option';
            button.dataset.viewValue = view;
            button.setAttribute('aria-pressed', 'false');
            button.innerHTML = `<span class="data-view-option-icon" aria-hidden="true">${tableViewIcons[view]}</span><span>${label}</span><span class="data-view-option-check" aria-hidden="true">✓</span>`;
            buttons.set(view, button);
            menu.append(button);
        });
        return { picker, buttons };
    };
    const syncDataViewPicker = (picker, buttons, view) => {
        const selectedButton = buttons.get(view);
        if (!selectedButton) return;
        const currentIcon = picker.querySelector('[data-view-current-icon]');
        const currentLabel = picker.querySelector('[data-view-current-label]');
        const optionIcon = selectedButton.querySelector('.data-view-option-icon');
        const optionLabel = selectedButton.querySelector('span:nth-child(2)');
        if (currentIcon && optionIcon) currentIcon.innerHTML = optionIcon.innerHTML;
        if (currentLabel && optionLabel) currentLabel.textContent = optionLabel.textContent;
        buttons.forEach((button, buttonView) => {
            button.setAttribute('aria-pressed', String(buttonView === view));
        });
    };
    document.addEventListener('click', (event) => {
        document.querySelectorAll('.data-view-picker[open]').forEach((picker) => {
            if (event.target instanceof Node && !picker.contains(event.target)) picker.open = false;
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.data-view-picker[open]').forEach((picker) => {
            picker.open = false;
            picker.querySelector('summary')?.focus();
        });
    });
    const notificationMenu = document.querySelector('[data-notification-menu]');
    const messageMenu = document.querySelector('[data-message-menu]');
    [notificationMenu, messageMenu].filter(Boolean).forEach((menu) => {
        let closeTimer = 0;
        menu.addEventListener('pointerenter', () => {
            window.clearTimeout(closeTimer);
            if (matchMedia('(hover: hover)').matches) menu.open = true;
        });
        menu.addEventListener('pointerleave', () => {
            if (matchMedia('(hover: hover)').matches && !menu.contains(document.activeElement)) {
                closeTimer = window.setTimeout(() => {
                    if (!menu.matches(':hover') && !menu.contains(document.activeElement)) {
                        menu.open = false;
                    }
                }, 180);
            }
        });
    });
    document.addEventListener('click', (event) => {
        [notificationMenu, messageMenu].filter(Boolean).forEach((menu) => {
            if (event.target instanceof Node && !menu.contains(event.target)) menu.open = false;
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        [notificationMenu, messageMenu].filter(Boolean).forEach((menu) => {
            menu.open = false;
            menu.querySelector('summary')?.focus();
        });
    });

    document.querySelectorAll('[data-profile-photo-trigger]').forEach((button) => {
        const editor = button.closest('.profile-photo-editor');
        const input = editor?.querySelector('[data-profile-photo-input]');
        const filename = editor?.querySelector('[data-profile-photo-filename]');
        const preview = editor?.querySelector('.profile-photo-preview');
        const removePhoto = editor?.querySelector('[data-profile-photo-remove]');
        if (!(input instanceof HTMLInputElement) || !(filename instanceof HTMLElement)) return;
        let previewUrl = '';
        button.addEventListener('click', () => input.click());
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) return;
            if (removePhoto instanceof HTMLInputElement) {
                removePhoto.checked = false;
                editor?.classList.remove('will-remove-photo');
            }
            filename.textContent = file.name;
            if (!file.type.startsWith('image/')) return;
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(file);
            if (preview instanceof HTMLImageElement) {
                preview.src = previewUrl;
            } else if (preview instanceof HTMLElement) {
                const image = document.createElement('img');
                image.className = 'profile-photo-preview';
                image.alt = 'Pratinjau foto profil';
                image.src = previewUrl;
                preview.replaceWith(image);
            }
        });
        removePhoto?.addEventListener('change', () => {
            editor?.classList.toggle('will-remove-photo', removePhoto.checked);
            filename.textContent = removePhoto.checked
                ? 'Avatar default akan digunakan setelah disimpan'
                : 'Foto profil saat ini';
        });
    });

    document.querySelectorAll('[data-username-check]').forEach((input) => {
        const feedback = input.closest('form')?.querySelector('[data-username-feedback]');
        if (!(input instanceof HTMLInputElement) || !(feedback instanceof HTMLElement)) return;
        let timer = 0;
        let requestNumber = 0;
        input.addEventListener('input', () => {
            window.clearTimeout(timer);
            const username = input.value.trim();
            const currentRequest = ++requestNumber;
            if (!/^[a-z]{1,32}$/i.test(username)) {
                feedback.textContent = 'Gunakan 1–32 huruf A-Z saja.';
                feedback.dataset.state = 'invalid';
                return;
            }
            feedback.textContent = 'Memeriksa ketersediaan username…';
            feedback.dataset.state = 'checking';
            timer = window.setTimeout(async () => {
                try {
                    const response = await fetch(`index.php?username_check=1&username=${encodeURIComponent(username)}`, {
                        headers: {'Accept': 'application/json'}
                    });
                    if (!response.ok) throw new Error('Pemeriksaan username gagal.');
                    const result = await response.json();
                    if (currentRequest !== requestNumber) return;
                    feedback.textContent = result.available
                        ? 'Username tersedia.'
                        : (result.valid ? 'Username sudah digunakan.' : 'Format username tidak valid.');
                    feedback.dataset.state = result.available ? 'available' : 'taken';
                } catch (error) {
                    console.error('Username availability check failed:', error);
                    if (currentRequest !== requestNumber) return;
                    feedback.textContent = 'Ketersediaan username belum dapat diperiksa. Coba lagi.';
                    feedback.dataset.state = 'error';
                }
            }, 250);
        });
    });

    const notificationApi = 'index.php?notifications_api=1';
    const notificationSeen = new Set();
    let notificationCursor = 0;
    let notificationEnabled = true;
    const updateNotificationBadge = (unreadCount) => {
        const summary = notificationMenu?.querySelector('summary');
        if (!summary) return;
        let badge = summary.querySelector('span');
        const count = Number(unreadCount) || 0;
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                summary.append(badge);
            }
            badge.textContent = count > 99 ? '99+' : String(count);
            summary.setAttribute('aria-label', `Notifikasi, ${count} belum dibaca`);
        } else {
            badge?.remove();
            summary.setAttribute('aria-label', 'Notifikasi');
        }
    };
    const notificationToast = (item) => {
        const id = Number(item.id);
        if (!Number.isSafeInteger(id) || id < 1 || notificationSeen.has(id)) return;
        notificationSeen.add(id);
        const toast = document.createElement('a');
        toast.className = 'notification-toast';
        toast.href = 'index.php?page=notifications';
        const title = document.createElement('strong');
        title.textContent = item.title || 'Notifikasi baru';
        const body = document.createElement('span');
        body.textContent = item.body || '';
        toast.append(title, body);
        let container = document.querySelector('[data-notification-toasts]');
        if (!container) {
            container = document.createElement('div');
            container.className = 'notification-toast-stack';
            container.dataset.notificationToasts = '';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-relevant', 'additions');
            document.body.append(container);
        }
        container.append(toast);
        window.setTimeout(() => {
            toast.remove();
            if (container.childElementCount === 0) container.remove();
        }, 8000);
    };
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const enablePushButton = document.querySelector('[data-enable-browser-notifications]');
    const pushStatus = document.querySelector('[data-browser-notification-status]');
    const updatePushStatus = (text) => {
        if (pushStatus) pushStatus.textContent = text;
    };
    const registerServiceWorker = async () => {
        if (!('serviceWorker' in navigator) || !window.isSecureContext) {
            throw new Error('Notifikasi perangkat memerlukan HTTPS dan dukungan service worker.');
        }
        const registration = await navigator.serviceWorker.register('service-worker.js');
        await navigator.serviceWorker.ready;
        return registration;
    };
    const loadNotificationState = async () => {
        const response = await fetch(`${notificationApi}&notification_action=state`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error(`Gagal memuat status notifikasi (${response.status}).`);
        const state = await response.json();
        notificationEnabled = state.enabled === true;
        notificationCursor = Number(state.latest_id) || 0;
        updateNotificationBadge(state.unread_count);
        if (!state.enabled && pushStatus) {
            updatePushStatus('Notifikasi aplikasi disenyapkan. Badge dan daftar notifikasi tetap aktif.');
        } else if (pushStatus && !('Notification' in window)) {
            updatePushStatus('Browser ini tidak mendukung notifikasi perangkat.');
        } else if (pushStatus && Notification.permission === 'denied') {
            updatePushStatus('Izin ditolak di browser. Ubah izin situs pada pengaturan browser/perangkat.');
        } else if (pushStatus && state.subscribed) {
            updatePushStatus('Notifikasi perangkat aktif pada browser ini.');
        } else if (pushStatus && !state.public_key) {
            updatePushStatus('Notifikasi di dalam aplikasi aktif. Push perangkat belum dikonfigurasi server.');
        } else if (pushStatus) {
            updatePushStatus('Izin browser belum diaktifkan pada perangkat ini.');
        }
        if (enablePushButton) {
            enablePushButton.disabled = !state.enabled || !state.public_key;
        }
        return state;
    };
    const pollNotifications = async () => {
        if (!notificationMenu || document.visibilityState === 'prerender') return;
        try {
            const response = await fetch(
                `${notificationApi}&notification_action=poll&after_id=${notificationCursor}`,
                { credentials: 'same-origin', headers: { Accept: 'application/json' } },
            );
            if (!response.ok) throw new Error(`Gagal memeriksa notifikasi (${response.status}).`);
            const state = await response.json();
            notificationEnabled = state.enabled === true;
            updateNotificationBadge(state.unread_count);
            if (!notificationEnabled) {
                notificationCursor = Math.max(notificationCursor, Number(state.latest_id) || 0);
                return;
            }
            for (const item of state.notifications || []) {
                notificationCursor = Math.max(notificationCursor, Number(item.id) || 0);
                if ('Notification' in window && Notification.permission === 'denied') {
                    continue;
                }
                if (document.visibilityState === 'visible') {
                    notificationToast(item);
                } else if ('Notification' in window && Notification.permission === 'granted') {
                    notificationSeen.add(Number(item.id));
                    const registration = await registerServiceWorker();
                    await registration.showNotification(item.title || 'Notifikasi KosKosan', {
                        body: item.body || '',
                        icon: 'public-icon.svg',
                        tag: `koskosan-${item.id}`,
                        data: { url: 'index.php?page=notifications' },
                    });
                }
            }
            notificationCursor = Math.max(notificationCursor, Number(state.latest_id) || 0);
        } catch (error) {
            console.error('Notification polling failed:', error);
        }
    };
    if (notificationMenu) {
        loadNotificationState().then((state) => {
            if (state.public_key && window.isSecureContext) {
                registerServiceWorker().catch((error) => {
                    console.error('Service worker registration failed:', error);
                });
            }
        }).catch((error) => console.error('Notification state failed:', error));
        window.setInterval(pollNotifications, 20000);
        navigator.serviceWorker?.addEventListener('message', (event) => {
            if (event.data?.type === 'push-notification') {
                notificationToast(event.data.notification || {});
            }
        });
    }
    enablePushButton?.addEventListener('click', async () => {
        enablePushButton.disabled = true;
        try {
            const state = await loadNotificationState();
            if (!state.enabled) {
                throw new Error('Aktifkan notifikasi di Pengaturan akun terlebih dahulu.');
            }
            if (!state.public_key) {
                throw new Error('Push perangkat belum dikonfigurasi oleh administrator.');
            }
            if (!('Notification' in window)) {
                throw new Error('Browser ini tidak mendukung notifikasi perangkat.');
            }
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                throw new Error('Izin notifikasi tidak diberikan. Periksa pengaturan browser/perangkat.');
            }
            const registration = await registerServiceWorker();
            const applicationServerKey = Uint8Array.from(
                atob(state.public_key.replace(/-/g, '+').replace(/_/g, '/')
                    + '='.repeat((4 - state.public_key.length % 4) % 4)),
                (character) => character.charCodeAt(0),
            );
            const subscription = await registration.pushManager.getSubscription()
                || await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey,
                });
            const response = await fetch(notificationApi, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                },
                body: new URLSearchParams({
                    csrf_token: csrfToken,
                    notification_action: 'subscribe',
                    subscription: JSON.stringify(subscription),
                }),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'Langganan push tidak dapat disimpan.');
            updatePushStatus('Notifikasi perangkat aktif pada browser ini.');
        } catch (error) {
            updatePushStatus(error.message || 'Notifikasi browser gagal diaktifkan.');
        } finally {
            enablePushButton.disabled = false;
        }
    });
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const sidebarBackdrop = document.querySelector('.sidebar-backdrop');
    const closeMobileMenu = () => {
        document.body.classList.remove('mobile-sidebar-open');
        mobileMenuToggle?.setAttribute('aria-expanded', 'false');
    };
    mobileMenuToggle?.addEventListener('click', () => {
        const isOpen = document.body.classList.toggle('mobile-sidebar-open');
        mobileMenuToggle.setAttribute('aria-expanded', String(isOpen));
    });
    sidebarBackdrop?.addEventListener('click', closeMobileMenu);
    document.querySelectorAll('.sidebar .main-nav a').forEach((link) => {
        link.addEventListener('click', closeMobileMenu);
    });
    document.querySelectorAll('[data-public-search]').forEach((form) => {
        const searchInput = form.querySelector('input[name="q"]');
        if (!(searchInput instanceof HTMLInputElement)) return;
        const openSearch = () => {
            form.classList.add('is-open');
            document.body.classList.add('public-search-open');
        };
        const closeSearch = () => {
            form.classList.remove('is-open');
            document.body.classList.remove('public-search-open');
        };
        searchInput.addEventListener('focus', openSearch);
        searchInput.addEventListener('click', openSearch);
        form.querySelectorAll('[data-search-destination]').forEach((button) => {
            button.addEventListener('click', () => {
                searchInput.value = button.dataset.searchDestination || '';
                form.querySelectorAll('[data-nearby-latitude], [data-nearby-longitude]')
                    .forEach((coordinate) => {
                        if (coordinate instanceof HTMLInputElement) coordinate.value = '';
                    });
                form.requestSubmit();
            });
        });
        document.addEventListener('click', (event) => {
            if (event.target instanceof Node && !form.contains(event.target)) closeSearch();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && form.classList.contains('is-open')) {
                closeSearch();
                searchInput.blur();
            }
        });
    });
    document.querySelectorAll('[data-search-nearby]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = button.closest('form');
            const latitude = form?.querySelector('[data-nearby-latitude]');
            const longitude = form?.querySelector('[data-nearby-longitude]');
            const status = form?.querySelector('[data-nearby-status]');
            if (!(latitude instanceof HTMLInputElement) || !(longitude instanceof HTMLInputElement)) return;
            if (!navigator.geolocation) {
                if (status) status.textContent = 'Browser ini tidak mendukung lokasi. Cari menggunakan nama atau alamat.';
                return;
            }
            button.disabled = true;
            if (status) status.textContent = 'Meminta izin lokasi...';
            navigator.geolocation.getCurrentPosition((position) => {
                latitude.value = String(position.coords.latitude);
                longitude.value = String(position.coords.longitude);
                if (status) status.textContent = 'Lokasi diterima. Menampilkan kos terdekat...';
                form?.requestSubmit();
            }, (error) => {
                button.disabled = false;
                if (status) status.textContent = error.code === error.PERMISSION_DENIED
                    ? 'Izin lokasi ditolak. Anda tetap dapat mencari berdasarkan nama atau alamat.'
                    : 'Lokasi tidak dapat dimuat. Coba lagi atau gunakan pencarian alamat.';
            }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
        });
    });
    document.querySelectorAll('[data-share-url]').forEach((button) => {
        button.addEventListener('click', async () => {
            const url = new URL(button.dataset.shareUrl || location.href, location.href).href;
            const title = button.dataset.shareTitle || document.title;
            try {
                if (navigator.share) await navigator.share({ title, url });
                else if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(url);
                    button.textContent = 'Tautan disalin';
                    window.setTimeout(() => { button.textContent = 'Bagikan'; }, 1800);
                } else {
                    window.prompt('Salin tautan kos ini:', url);
                }
            } catch (error) {
                if (error.name !== 'AbortError') console.error('Property sharing failed:', error);
            }
        });
    });
    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element
            ? event.target.closest('a[href*="page=payment_proof"]') : null;
        if (!(link instanceof HTMLAnchorElement)) return;
        event.preventDefault();
        let dialog = document.getElementById('payment-proof-dialog');
        if (!dialog) {
            dialog = document.createElement('dialog');
            dialog.id = 'payment-proof-dialog';
            dialog.className = 'photo-lightbox payment-proof-dialog';
            dialog.setAttribute('aria-label', 'Bukti pembayaran');
            dialog.innerHTML = '<button class="photo-lightbox-close" type="button" data-proof-close aria-label="Tutup bukti">×</button><img alt="Bukti pembayaran">';
            document.body.append(dialog);
            dialog.addEventListener('click', (closeEvent) => {
                if (closeEvent.target === dialog || closeEvent.target.closest('[data-proof-close]')) dialog.close();
            });
        }
        const proofImage = dialog.querySelector('img');
        if (proofImage) proofImage.src = link.href;
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else window.open(link.href, '_blank', 'noopener');
    });
    const selectMenuControls = new Map();
    let selectDropdownCount = 0;
    const initializeSelectDropdowns = (root = document) => {
        const selects = root instanceof HTMLSelectElement
            ? [root]
            : Array.from(root.querySelectorAll('select'));
        selects.forEach((select) => {
            if (select.multiple || select.size > 1 || select.dataset.customSelectInitialized === 'true') return;
            select.dataset.customSelectInitialized = 'true';

            const wrapper = document.createElement('span');
            wrapper.className = 'select-control';
            select.parentNode.insertBefore(wrapper, select);
            wrapper.append(select);
            select.classList.add('visually-hidden', 'select-native');
            select.tabIndex = -1;
            select.setAttribute('aria-hidden', 'true');

            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'select-trigger';
            trigger.setAttribute('role', 'combobox');
            trigger.setAttribute('aria-haspopup', 'listbox');
            trigger.setAttribute('aria-expanded', 'false');
            trigger.setAttribute('aria-autocomplete', 'none');

            const label = select.labels?.[0];
            const labelText = label?.querySelector('span')?.textContent?.trim()
                || label?.textContent?.trim()
                || select.getAttribute('aria-label')
                || 'Pilih opsi';
            trigger.setAttribute('aria-label', select.getAttribute('aria-label') || labelText);

            const valueLabel = document.createElement('span');
            valueLabel.className = 'select-trigger-value';
            const chevron = document.createElement('span');
            chevron.className = 'select-trigger-chevron';
            chevron.setAttribute('aria-hidden', 'true');
            trigger.append(valueLabel, chevron);

            const errorMessage = document.createElement('span');
            errorMessage.className = 'select-error-message';
            errorMessage.setAttribute('role', 'alert');
            errorMessage.hidden = true;
            wrapper.append(trigger, errorMessage);

            const menu = document.createElement('div');
            menu.className = 'select-menu';
            menu.id = `koskosan-select-menu-${++selectDropdownCount}`;
            menu.setAttribute('role', 'listbox');
            menu.setAttribute('aria-label', labelText);
            menu.hidden = true;
            document.body.append(menu);
            trigger.setAttribute('aria-controls', menu.id);

            const sync = () => {
                const selectedOption = select.selectedOptions[0];
                valueLabel.textContent = selectedOption?.label || 'Pilih opsi';
                trigger.disabled = select.disabled;
                trigger.setAttribute('aria-required', String(select.required));
                menu.replaceChildren();
                Array.from(select.options).forEach((option, index) => {
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'select-option';
                    item.dataset.optionIndex = String(index);
                    item.setAttribute('role', 'option');
                    item.setAttribute('aria-selected', String(index === select.selectedIndex));
                    item.tabIndex = -1;
                    item.disabled = option.disabled || select.disabled;
                    item.textContent = option.label;
                    item.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        if (item.disabled) return;
                        select.selectedIndex = index;
                        select.dispatchEvent(new Event('input', { bubbles: true }));
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                        wrapper.classList.remove('has-error');
                        trigger.removeAttribute('aria-invalid');
                        errorMessage.hidden = true;
                        closeMenu(true);
                    });
                    menu.append(item);
                });
                if (!menu.hidden) positionMenu();
            };

            const positionMenu = () => {
                const bounds = trigger.getBoundingClientRect();
                const availableBelow = window.innerHeight - bounds.bottom - 10;
                const availableAbove = bounds.top - 10;
                const openAbove = availableBelow < Math.min(menu.scrollHeight, 220)
                    && availableAbove > availableBelow;
                const menuWidth = Math.min(bounds.width, window.innerWidth - 16);
                menu.style.left = `${Math.max(8, Math.min(bounds.left, window.innerWidth - menuWidth - 8))}px`;
                menu.style.width = `${menuWidth}px`;
                menu.style.maxHeight = `${Math.max(100, Math.min(260, openAbove ? availableAbove : availableBelow))}px`;
                menu.style.top = openAbove
                    ? `${Math.max(8, bounds.top - menu.offsetHeight - 6)}px`
                    : `${Math.min(window.innerHeight - 8, bounds.bottom + 6)}px`;
                menu.dataset.placement = openAbove ? 'top' : 'bottom';
            };

            const closeMenu = (returnFocus = false) => {
                if (menu.hidden) return;
                menu.hidden = true;
                wrapper.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
                if (returnFocus) trigger.focus();
            };

            const openMenu = (focusSelected = true) => {
                if (trigger.disabled) return;
                document.querySelectorAll('.select-menu:not([hidden])').forEach((openMenuElement) => {
                    if (openMenuElement !== menu) {
                        const controls = selectMenuControls.get(openMenuElement);
                        openMenuElement.hidden = true;
                        controls?.wrapper.classList.remove('is-open');
                        controls?.trigger.setAttribute('aria-expanded', 'false');
                    }
                });
                menu.hidden = false;
                wrapper.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
                positionMenu();
                const items = Array.from(menu.querySelectorAll('.select-option:not(:disabled)'));
                const selected = items.find((item) => Number(item.dataset.optionIndex) === select.selectedIndex);
                if (focusSelected) (selected || items[0])?.focus();
            };
            selectMenuControls.set(menu, { wrapper, trigger });

            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                if (menu.hidden) openMenu();
                else closeMenu();
            });
            trigger.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openMenu();
                }
            });
            menu.addEventListener('keydown', (event) => {
                const items = Array.from(menu.querySelectorAll('.select-option:not(:disabled)'));
                const currentIndex = items.indexOf(document.activeElement);
                let targetIndex = currentIndex;
                if (event.key === 'ArrowDown') targetIndex = Math.min(items.length - 1, currentIndex + 1);
                else if (event.key === 'ArrowUp') targetIndex = Math.max(0, currentIndex - 1);
                else if (event.key === 'Home') targetIndex = 0;
                else if (event.key === 'End') targetIndex = items.length - 1;
                else if (event.key === 'Escape') {
                    event.preventDefault();
                    closeMenu(true);
                    return;
                } else if (event.key === 'Tab') {
                    closeMenu(true);
                    return;
                } else if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                    const match = items.find((item) => item.textContent.trim()
                        .toLocaleLowerCase('id').startsWith(event.key.toLocaleLowerCase('id')));
                    if (match) {
                        event.preventDefault();
                        match.focus();
                    }
                    return;
                } else return;
                event.preventDefault();
                items[targetIndex]?.focus();
            });
            select.addEventListener('change', sync);
            select.addEventListener('invalid', (event) => {
                event.preventDefault();
                wrapper.classList.add('has-error');
                trigger.setAttribute('aria-invalid', 'true');
                errorMessage.textContent = `Pilih ${labelText.toLocaleLowerCase('id')}.`;
                errorMessage.hidden = false;
                trigger.focus();
                openMenu(false);
            });
            select.form?.addEventListener('reset', () => window.setTimeout(sync));
            document.addEventListener('pointerdown', (event) => {
                if (!wrapper.contains(event.target) && !menu.contains(event.target)) closeMenu();
            });
            window.addEventListener('resize', () => {
                if (!menu.hidden) positionMenu();
            });
            document.addEventListener('scroll', () => {
                if (!menu.hidden) positionMenu();
            }, true);
            const optionObserver = new MutationObserver(sync);
            optionObserver.observe(select, { childList: true, subtree: true, characterData: true, attributes: true });
            sync();
        });
    };

    const themeStorageKey = 'koskosan-theme';
    const themeSwitchers = document.querySelectorAll('[data-theme-switcher]');
    let savedTheme = 'light';
    try {
        savedTheme = localStorage.getItem(themeStorageKey) === 'dark' ? 'dark' : 'light';
    } catch (error) {
        console.error('Saved display theme could not be read:', error);
    }
    document.documentElement.dataset.theme = savedTheme;
    themeSwitchers.forEach((switcher) => {
        const syncThemeMenu = (theme) => {
            const label = switcher.querySelector('[data-theme-label]');
            const icon = switcher.querySelector('[data-theme-current-icon]');
            if (label) label.textContent = theme === 'dark' ? 'Dark' : 'Light';
            if (icon) icon.textContent = theme === 'dark' ? '☾' : '☀';
            switcher.querySelectorAll('[data-theme-choice]').forEach((choice) => {
                choice.setAttribute('aria-pressed', String(choice.dataset.themeChoice === theme));
            });
        };
        syncThemeMenu(savedTheme);
        switcher.querySelectorAll('[data-theme-choice]').forEach((choice) => {
            choice.addEventListener('click', () => {
                const selectedTheme = choice.dataset.themeChoice === 'dark' ? 'dark' : 'light';
                document.documentElement.dataset.theme = selectedTheme;
                themeSwitchers.forEach((otherSwitcher) => {
                    const label = otherSwitcher.querySelector('[data-theme-label]');
                    const icon = otherSwitcher.querySelector('[data-theme-current-icon]');
                    if (label) label.textContent = selectedTheme === 'dark' ? 'Dark' : 'Light';
                    if (icon) icon.textContent = selectedTheme === 'dark' ? '☾' : '☀';
                    otherSwitcher.querySelectorAll('[data-theme-choice]').forEach((otherChoice) => {
                        otherChoice.setAttribute('aria-pressed', String(otherChoice.dataset.themeChoice === selectedTheme));
                    });
                    otherSwitcher.open = false;
                });
                try {
                    localStorage.setItem(themeStorageKey, selectedTheme);
                } catch (error) {
                    console.error('Display theme preference could not be saved:', error);
                }
            });
        });
    });
    document.addEventListener('click', (event) => {
        themeSwitchers.forEach((switcher) => {
            if (event.target instanceof Node && !switcher.contains(event.target)) switcher.open = false;
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        themeSwitchers.forEach((switcher) => {
            if (!switcher.open) return;
            switcher.open = false;
            switcher.querySelector('summary')?.focus();
        });
    });

    let generatedPasswordId = 0;
    const regionLevels = ['province', 'regency', 'district', 'village'];
    const regionLabels = {
        province: 'Provinsi',
        regency: 'Kabupaten/Kota',
        district: 'Kecamatan',
        village: 'Kelurahan/Desa',
    };
    const loadRegionOptions = async (level, parentCode = '') => {
        const endpoint = new URL('index.php', document.baseURI);
        endpoint.searchParams.set('region_options', '1');
        endpoint.searchParams.set('level', level);
        if (parentCode) endpoint.searchParams.set('parent_code', parentCode);
        const response = await fetch(endpoint, { credentials: 'same-origin' });
        const result = await response.json();
        if (!response.ok || !Array.isArray(result.regions)) {
            throw new Error(result.error || 'Data wilayah tidak dapat dimuat.');
        }
        return result.regions;
    };
    const reportRegionError = (container, message) => {
        let error = container.querySelector('.region-load-error');
        if (!error) {
            error = document.createElement('p');
            error.className = 'region-load-error';
            error.setAttribute('role', 'alert');
            container.append(error);
        }
        error.textContent = message;
    };
    const initializeRegionPicker = async (container) => {
        const selects = regionLevels.map((level) =>
            container.querySelector(`[data-region-level="${level}"]`)
        );
        if (selects.some((select) => !(select instanceof HTMLSelectElement))) return;
        const initialOptions = new Map(selects.map((select, index) => [
            regionLevels[index],
            Array.from(select.options, (option) => option.cloneNode(true)),
        ]));
        selects.forEach((select, index) => {
            select.disabled = index > 0;
        });

        const populate = (select, level, regions, selectedCode = '') => {
            const savedOption = selectedCode
                ? initialOptions.get(level).find((option) => option.value === selectedCode)
                : null;
            select.replaceChildren(new Option(
                select.dataset.defaultLabel || `Pilih ${regionLabels[level].toLocaleLowerCase('id')}`,
                ''
            ));
            regions.forEach((region) => select.add(new Option(region.name, region.code)));
            if (selectedCode && !regions.some((region) => region.code === selectedCode) && savedOption) {
                const staleOption = savedOption.cloneNode(true);
                staleOption.textContent = `${staleOption.textContent} (data lama)`;
                select.add(staleOption);
            }
            select.value = selectedCode;
        };
        const populateChildren = async (parentIndex, restoreSaved) => {
            let parentCode = selects[parentIndex].value;
            for (let index = parentIndex + 1; index < regionLevels.length; index += 1) {
                const select = selects[index];
                const level = regionLevels[index];
                select.disabled = true;
                populate(select, level, [], '');
                if (!parentCode) continue;
                const regions = await loadRegionOptions(level, parentCode);
                const selectedCode = restoreSaved ? select.dataset.selectedCode || '' : '';
                populate(select, level, regions, selectedCode);
                select.disabled = false;
                parentCode = select.value;
                if (!parentCode) restoreSaved = false;
            }
            container.querySelector('.region-load-error')?.remove();
        };

        try {
            const provinces = await loadRegionOptions('province');
            populate(selects[0], 'province', provinces, selects[0].dataset.selectedCode || '');
            selects[0].disabled = false;
            await populateChildren(0, true);
        } catch (error) {
            console.error('Indonesia region picker failed to load:', error);
            reportRegionError(container, 'Data wilayah gagal dimuat. Pastikan migrasi wilayah Indonesia telah diterapkan, lalu muat ulang halaman.');
        }

        selects.forEach((select, index) => {
            select.addEventListener('change', async () => {
                for (let childIndex = index + 1; childIndex < selects.length; childIndex += 1) {
                    selects[childIndex].dataset.selectedCode = '';
                }
                try {
                    await populateChildren(index, false);
                } catch (error) {
                    console.error('Indonesia region children failed to load:', error);
                    reportRegionError(container, 'Pilihan wilayah berikutnya gagal dimuat. Periksa koneksi lalu coba lagi.');
                }
                container.dispatchEvent(new Event('regionchange', { bubbles: true }));
            });
        });
    };
    document.querySelectorAll('[data-region-picker]').forEach(initializeRegionPicker);

    document.querySelectorAll('[data-building-gallery]').forEach((gallery) => {
        const input = document.getElementById('building-gallery-input');
        const grid = gallery.querySelector('[data-building-gallery-grid]');
        const addButton = gallery.querySelector('[data-building-gallery-add]');
        const status = gallery.querySelector('[data-building-gallery-status]');
        const count = gallery.querySelector('[data-building-gallery-count]');
        const form = document.getElementById('building-form');
        if (!(input instanceof HTMLInputElement) || !grid || !(addButton instanceof HTMLButtonElement)
            || !status || !count || !(form instanceof HTMLFormElement)) return;

        let pendingFiles = [];
        const syncInputFiles = () => {
            const transfer = new DataTransfer();
            pendingFiles.forEach(({ file }) => transfer.items.add(file));
            input.files = transfer.files;
        };
        const renderPendingFiles = () => {
            grid.querySelectorAll('[data-building-pending-photo]').forEach((photo) => photo.remove());
            pendingFiles.forEach(({ file, previewUrl }, index) => {
                const card = document.createElement('article');
                card.dataset.buildingPendingPhoto = '';
                card.className = 'building-pending-photo';
                const image = document.createElement('img');
                image.src = previewUrl;
                image.alt = `Pratinjau ${file.name}`;
                const details = document.createElement('div');
                details.className = 'building-pending-photo-details';
                const name = document.createElement('span');
                name.textContent = file.name;
                const size = document.createElement('small');
                size.textContent = `${(file.size / (1024 * 1024)).toFixed(2)} MB · Siap disimpan`;
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'text-button';
                remove.textContent = 'Hapus pilihan';
                remove.setAttribute('aria-label', `Hapus ${file.name} dari pilihan`);
                remove.addEventListener('click', () => {
                    URL.revokeObjectURL(pendingFiles[index].previewUrl);
                    pendingFiles.splice(index, 1);
                    syncInputFiles();
                    renderPendingFiles();
                    status.textContent = 'Foto yang dipilih akan tersimpan saat Anda menekan tombol simpan perubahan.';
                });
                details.append(name, size, remove);
                card.append(image, details);
                grid.append(card);
            });

            const savedCount = grid.querySelectorAll('article:not([data-building-pending-photo])').length;
            count.textContent = pendingFiles.length
                ? `${savedCount} foto tersimpan · ${pendingFiles.length} foto baru siap disimpan`
                : `${savedCount} foto tersimpan`;
            addButton.disabled = pendingFiles.length >= 4;
        };

        addButton.addEventListener('click', () => input.click());
        input.addEventListener('change', () => {
            const selectedFiles = Array.from(input.files || []);
            const uniqueFiles = selectedFiles.filter((file) => !pendingFiles.some((pending) =>
                pending.file.name === file.name && pending.file.size === file.size
                    && pending.file.lastModified === file.lastModified
            ));
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            const allowedExtensions = /\.(jpe?g|png|webp)$/i;
            const invalidFile = uniqueFiles.find((file) =>
                file.size < 1 || file.size > 4 * 1024 * 1024
                    || (!allowedTypes.includes(file.type)
                        && !(file.type === '' && allowedExtensions.test(file.name)))
            );
            if (invalidFile) {
                syncInputFiles();
                status.textContent = `${invalidFile.name}: pilih gambar JPG, PNG, atau WebP maksimal 4 MB.`;
                return;
            }
            if (pendingFiles.length + uniqueFiles.length > 4) {
                syncInputFiles();
                status.textContent = 'Maksimal 4 foto baru dapat ditambahkan setiap kali menyimpan.';
                return;
            }

            uniqueFiles.forEach((file) => pendingFiles.push({
                file,
                previewUrl: URL.createObjectURL(file),
            }));
            syncInputFiles();
            renderPendingFiles();
            status.textContent = pendingFiles.length
                ? `${pendingFiles.length} foto baru siap disimpan bersama perubahan gedung.`
                : 'Pilih foto untuk menambahkannya ke galeri gedung.';
        });
        form.addEventListener('submit', () => {
            if (!pendingFiles.length) return;
            status.textContent = `Mengunggah ${pendingFiles.length} foto ke galeri gedung...`;
            addButton.disabled = true;
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = true;
            });
            document.querySelectorAll('button[form="building-form"]').forEach((button) => {
                button.disabled = true;
            });
        });
        renderPendingFiles();
    });

    document.querySelectorAll('[data-building-card-list]').forEach((list) => {
        const cards = Array.from(list.querySelectorAll('[data-building-card]'));
        const buildingRows = Array.from(list.querySelectorAll('[data-building-table-row]'));
        const search = list.querySelector('[data-building-card-search]');
        const pageSizeSelect = list.querySelector('[data-building-card-page-size]');
        const summary = list.querySelector('[data-building-card-summary]');
        const previous = list.querySelector('[data-building-card-previous]');
        const next = list.querySelector('[data-building-card-next]');
        const empty = list.querySelector('[data-building-card-empty]');
        const cardGrid = list.querySelector('[data-building-card-grid]');
        const tableView = list.querySelector('[data-building-table-view]');
        const pickerHost = list.querySelector('[data-building-view-switch]');
        if (!(search instanceof HTMLInputElement) || !(pageSizeSelect instanceof HTMLSelectElement)
            || !(previous instanceof HTMLButtonElement) || !(next instanceof HTMLButtonElement)
            || !(empty instanceof HTMLElement) || !(cardGrid instanceof HTMLElement)
            || !(tableView instanceof HTMLElement) || !(pickerHost instanceof HTMLDetailsElement)
            || !summary) return;

        const { picker, buttons } = createDataViewPicker('Pilih tampilan gedung');
        pickerHost.replaceChildren(...Array.from(picker.childNodes));
        const storageKey = `koskosan-building-view:${list.dataset.buildingViewStorageKey || 'buildings'}`;

        let currentPage = 1;
        let selectedView = 'cards';
        try {
            if (localStorage.getItem(storageKey) === 'table') selectedView = 'table';
        } catch (error) {
            console.error('Building view preference could not be read:', error);
        }
        const setView = (view) => {
            selectedView = view;
            const cardView = view === 'cards';
            cardGrid.hidden = !cardView;
            tableView.hidden = cardView;
            list.classList.toggle('building-cards-active', cardView);
            syncDataViewPicker(pickerHost, buttons, view);
            try {
                localStorage.setItem(storageKey, view);
            } catch (error) {
                console.error('Building view preference could not be saved:', error);
            }
        };
        const render = () => {
            const query = search.value.trim().toLocaleLowerCase('id');
            const matches = cards.filter((card) => !query
                || card.textContent.toLocaleLowerCase('id').includes(query));
            const pageSize = pageSizeSelect.value === 'all'
                ? Math.max(matches.length, 1) : Number(pageSizeSelect.value);
            const pageCount = Math.max(1, Math.ceil(matches.length / pageSize));
            currentPage = Math.min(currentPage, pageCount);
            const first = pageSizeSelect.value === 'all' ? 0 : (currentPage - 1) * pageSize;
            const visibleCards = new Set(matches.slice(first, first + pageSize));
            cards.forEach((card) => { card.hidden = !visibleCards.has(card); });
            const visibleIds = new Set(Array.from(visibleCards, (card) => card.dataset.buildingId));
            buildingRows.forEach((row) => { row.hidden = !visibleIds.has(row.dataset.buildingId); });
            pageSizeSelect.closest('label')?.toggleAttribute('hidden', matches.length <= 5);
            empty.hidden = matches.length > 0 || (selectedView === 'cards' && cards.length > 0);
            if (matches.length === 0) {
                summary.textContent = cards.length ? 'Tidak ada hasil' : '0 gedung';
            } else {
                summary.textContent = `${first + 1}–${Math.min(first + pageSize, matches.length)} dari ${matches.length}`;
            }
            previous.disabled = currentPage <= 1;
            next.disabled = currentPage >= pageCount;
        };

        search.addEventListener('input', () => {
            currentPage = 1;
            render();
        });
        pageSizeSelect.addEventListener('change', () => {
            currentPage = 1;
            render();
        });
        previous.addEventListener('click', () => {
            currentPage -= 1;
            render();
        });
        next.addEventListener('click', () => {
            currentPage += 1;
            render();
        });
        buttons.forEach((button, view) => {
            button.addEventListener('click', () => {
                setView(view);
                pickerHost.open = false;
            });
        });
        syncDataViewPicker(pickerHost, buttons, selectedView);
        setView(selectedView);
        render();
    });

    document.querySelectorAll('[data-room-management]').forEach((management) => {
        const table = management.querySelector('[data-room-table]');
        const tableView = management.querySelector('[data-room-table-view]');
        const cardView = management.querySelector('[data-room-card-view]');
        const buildingFilter = management.querySelector('[data-room-building]');
        const typeFilter = management.querySelector('select[data-room-type]');
        const search = management.querySelector('[data-room-search]');
        const viewPicker = management.querySelector('[data-room-view-switch]');
        const viewButtons = new Map(Array.from(management.querySelectorAll('[data-room-view]'))
            .map((button) => [button.dataset.roomView, button]));
        const pagination = management.querySelector('[data-room-pagination]');
        const pageSizeSelect = management.querySelector('[data-room-page-size]');
        const summary = management.querySelector('[data-room-summary]');
        const previous = management.querySelector('[data-room-previous]');
        const next = management.querySelector('[data-room-next]');
        const storageKey = management.dataset.roomViewStorageKey || 'roomManagementView';
        if (!(table instanceof HTMLTableElement) || !tableView || !cardView
            || !(buildingFilter instanceof HTMLSelectElement) || !(typeFilter instanceof HTMLSelectElement)
            || !(search instanceof HTMLInputElement) || !pagination
            || !(pageSizeSelect instanceof HTMLSelectElement) || !summary
            || !(previous instanceof HTMLButtonElement) || !(next instanceof HTMLButtonElement)
            || !(viewPicker instanceof HTMLDetailsElement) || !viewButtons.size) return;

        const tbody = table.tBodies[0];
        const rows = Array.from(tbody.querySelectorAll('[data-room-row]'));
        const groups = Array.from(cardView.querySelectorAll('[data-room-group]')).map((group) => ({
            element: group,
            cards: Array.from(group.querySelectorAll('[data-room-card]')),
        }));
        let currentPage = 1;
        let selectedView = 'table';
        try {
            const savedView = localStorage.getItem(storageKey);
            if (savedView === 'table' || savedView === 'cards') selectedView = savedView;
        } catch (error) {
            console.error('Room view preference could not be read:', error);
        }

        const updateRoomTypes = () => {
            const selectedBuilding = buildingFilter.value;
            const roomTypes = Array.from(new Set(rows
                .filter((row) => !selectedBuilding || row.dataset.buildingId === selectedBuilding)
                .map((row) => row.dataset.roomType)
                .filter(Boolean)))
                .sort((first, second) => first.localeCompare(second, 'id'));
            typeFilter.replaceChildren(new Option('Semua tipe', ''));
            roomTypes.forEach((type) => typeFilter.add(new Option(type, type)));
            typeFilter.disabled = !selectedBuilding || roomTypes.length === 0;
            typeFilter.value = '';
        };

        const render = () => {
            const selectedBuilding = buildingFilter.value;
            const selectedType = typeFilter.value;
            const query = search.value.trim().toLocaleLowerCase('id');
            const matchingRows = rows.filter((row) => {
                const matchesBuilding = !selectedBuilding || row.dataset.buildingId === selectedBuilding;
                const matchesType = !selectedType || row.dataset.roomType === selectedType;
                return matchesBuilding && matchesType
                    && (!query || row.textContent.toLocaleLowerCase('id').includes(query));
            });
            pageSizeSelect.closest('label')?.toggleAttribute('hidden', matchingRows.length <= 5);
            const matchingCards = [];
            groups.forEach(({ element, cards }) => {
                const matchesGroup = (!selectedBuilding || element.dataset.buildingId === selectedBuilding)
                    && (!selectedType || element.dataset.roomType === selectedType);
                const visibleCards = cards.filter((card) =>
                    matchesGroup && (!query || card.textContent.toLocaleLowerCase('id').includes(query))
                );
                cards.forEach((card) => { card.hidden = !visibleCards.includes(card); });
                element.hidden = visibleCards.length === 0;
                matchingCards.push(...visibleCards);
                const track = element.querySelector('.room-carousel-track');
                if (track instanceof HTMLElement) track.scrollLeft = 0;
            });
            cardView.querySelectorAll('[data-room-building-group]').forEach((buildingGroup) => {
                buildingGroup.hidden = !buildingGroup.querySelector('[data-room-group]:not([hidden])');
            });
            const pageSize = pageSizeSelect.value === 'all'
                ? matchingRows.length || 1 : Number(pageSizeSelect.value);
            const pageCount = Math.max(1, Math.ceil(matchingRows.length / pageSize));
            currentPage = Math.min(currentPage, pageCount);
            const start = pageSizeSelect.value === 'all' ? 0 : (currentPage - 1) * pageSize;
            const visibleRows = matchingRows.slice(start, start + pageSize);
            const visibleRowSet = new Set(visibleRows);
            rows.forEach((row) => { row.hidden = !visibleRowSet.has(row); });
            let emptyRow = tbody.querySelector('[data-room-empty]');
            if (!emptyRow) {
                emptyRow = document.createElement('tr');
                emptyRow.dataset.roomEmpty = '';
                const cell = document.createElement('td');
                cell.colSpan = 5;
                cell.className = 'no-data';
                cell.textContent = 'Tidak ada kamar yang cocok dengan pencarian atau filter.';
                emptyRow.append(cell);
                tbody.append(emptyRow);
            }
            emptyRow.hidden = matchingRows.length > 0;
            const first = matchingRows.length ? start + 1 : 0;
            const last = Math.min(start + visibleRows.length, matchingRows.length);
            summary.textContent = `${first}–${last} dari ${matchingRows.length}`;
            previous.disabled = currentPage <= 1 || pageSizeSelect.value === 'all';
            next.disabled = currentPage >= pageCount || pageSizeSelect.value === 'all';
            if (selectedView === 'cards') window.dispatchEvent(new Event('resize'));
        };

        const setView = (view) => {
            selectedView = view;
            tableView.hidden = view !== 'table';
            cardView.hidden = view !== 'cards';
            pagination.hidden = view !== 'table';
            syncDataViewPicker(viewPicker, viewButtons, view);
            try {
                localStorage.setItem(storageKey, view);
            } catch (error) {
                console.error('Room view preference could not be saved:', error);
            }
            if (view === 'cards') window.dispatchEvent(new Event('resize'));
        };

        buildingFilter.addEventListener('change', () => {
            updateRoomTypes();
            currentPage = 1;
            render();
        });
        typeFilter.addEventListener('change', () => { currentPage = 1; render(); });
        search.addEventListener('input', () => { currentPage = 1; render(); });
        pageSizeSelect.addEventListener('change', () => { currentPage = 1; render(); });
        previous.addEventListener('click', () => { currentPage -= 1; render(); });
        next.addEventListener('click', () => { currentPage += 1; render(); });
        viewButtons.forEach((button, view) => {
            button.addEventListener('click', () => {
                setView(view);
                viewPicker.open = false;
            });
        });

        updateRoomTypes();
        render();
        setView(selectedView);
    });

    document.querySelectorAll('.main-area .content table').forEach((table) => {
        if (!(table instanceof HTMLTableElement) || !table.tHead || !table.tBodies.length) return;
        if (table.closest('[data-room-management]') || table.hasAttribute('data-custom-table-controls')) return;
        const tbody = table.tBodies[0];
        const rows = Array.from(tbody.rows).filter((row) =>
            !row.hasAttribute('data-table-group-heading') && !row.querySelector('.no-data')
        );
        if (!rows.length) return;

        const tools = document.createElement('div');
        tools.className = 'data-table-tools';

        const searchLabel = document.createElement('label');
        searchLabel.className = 'data-table-search';
        const searchCaption = document.createElement('span');
        searchCaption.textContent = 'Cari di tabel';
        const search = document.createElement('input');
        search.type = 'search';
        search.placeholder = 'Nama, email, ID, status...';
        search.setAttribute('aria-label', 'Cari data dalam tabel');
        if (table.id === 'activity-log-table') {
            search.placeholder = 'Akun, aktivitas, jenis, kos...';
            search.maxLength = 120;
            const currentQuery = document.querySelector('.activity-log-filters input[name="q"]');
            if (currentQuery instanceof HTMLInputElement) search.value = currentQuery.value;
        }
        searchLabel.append(searchCaption, search);
        tools.append(searchLabel);

        let buildingFilter;
        if (table.hasAttribute('data-building-filter')) {
            const buildings = new Map();
            rows.forEach((row) => {
                let rowBuildings = {};
                try {
                    rowBuildings = JSON.parse(row.dataset.buildingMap || '{}');
                } catch (error) {
                    console.error('Tenant building filter data is invalid:', error);
                }
                Object.entries(rowBuildings).forEach(([value, label]) => {
                    if (!buildings.has(value)) buildings.set(value, label);
                });
                if (!Object.keys(rowBuildings).length && row.dataset.buildingFilter === '') {
                    buildings.set('__none', 'Belum tercatat sebagai penghuni aktif');
                }
            });
            if (buildings.size) {
                const buildingLabel = document.createElement('label');
                buildingLabel.className = 'data-table-select';
                const caption = document.createElement('span');
                caption.textContent = 'Gedung kos';
                buildingFilter = document.createElement('select');
                buildingFilter.setAttribute('aria-label', 'Filter berdasarkan gedung kos');
                buildingFilter.add(new Option('Semua gedung', ''));
                Array.from(buildings.entries())
                    .sort((first, second) => first[1].localeCompare(second[1], 'id'))
                    .forEach(([value, label]) => buildingFilter.add(new Option(label, value)));
                buildingLabel.append(caption, buildingFilter);
                tools.append(buildingLabel);
            }
        }

        const regionFilters = [];
        if (table.hasAttribute('data-region-filter')) {
            const regionControls = document.createElement('div');
            regionControls.className = 'data-table-region-filters';
            regionControls.dataset.regionPicker = '';
            regionLevels.forEach((level) => {
                const label = document.createElement('label');
                label.className = 'data-table-select';
                const caption = document.createElement('span');
                caption.textContent = regionLabels[level];
                const select = document.createElement('select');
                select.dataset.regionLevel = level;
                select.dataset.defaultLabel = `Semua ${regionLabels[level].toLocaleLowerCase('id')}`;
                select.disabled = level !== 'province';
                select.setAttribute('aria-label', `Filter berdasarkan ${regionLabels[level].toLocaleLowerCase('id')}`);
                select.add(new Option(`Semua ${regionLabels[level].toLocaleLowerCase('id')}`, ''));
                label.append(caption, select);
                regionControls.append(label);
                regionFilters.push(select);
            });
            tools.append(regionControls);
            initializeRegionPicker(regionControls);
            regionControls.addEventListener('regionchange', () => { currentPage = 1; render(); });
        }

        const pageSizeLabel = document.createElement('label');
        pageSizeLabel.className = 'data-table-select data-table-page-size';
        const pageSizeCaption = document.createElement('span');
        pageSizeCaption.textContent = 'Tampilkan';
        const pageSizeSelect = document.createElement('select');
        pageSizeSelect.setAttribute('aria-label', 'Jumlah data yang ditampilkan');
        pageSizeSelect.add(new Option('5', '5'));
        pageSizeSelect.add(new Option('10', '10'));
        pageSizeSelect.add(new Option('Semua', 'all'));
        pageSizeSelect.value = '5';
        pageSizeLabel.append(pageSizeCaption, pageSizeSelect);

        const navigation = document.createElement('div');
        navigation.className = 'data-table-navigation';
        const summary = document.createElement('span');
        summary.className = 'data-table-summary';
        summary.setAttribute('aria-live', 'polite');
        const previous = document.createElement('button');
        previous.type = 'button';
        previous.className = 'button secondary compact-button';
        previous.textContent = 'Sebelumnya';
        const next = document.createElement('button');
        next.type = 'button';
        next.className = 'button secondary compact-button';
        next.textContent = 'Berikutnya';
        navigation.append(summary, previous, next);
        const tableControls = document.createElement('div');
        tableControls.className = 'data-table-controls';
        const { picker: viewSwitch, buttons: viewButtons } = createDataViewPicker('Pilih tampilan data');
        const tableKey = table.id || `${location.pathname}:${new URLSearchParams(location.search).get('page') || 'page'}:${Array.from(document.querySelectorAll('.main-area .content table')).indexOf(table)}`;
        const storageKey = `koskosan-table-view:${tableKey}`;
        let tableContainer = table.closest('.table-scroll');
        if (!tableContainer) {
            tableContainer = document.createElement('div');
            tableContainer.className = 'table-scroll';
            table.before(tableContainer);
            tableContainer.append(table);
        }
        const applyView = (view) => {
            const cardView = view === 'cards';
            const selectedView = cardView ? 'cards' : 'table';
            syncDataViewPicker(viewSwitch, viewButtons, selectedView);
            tableContainer.classList.toggle('data-table-card-view', cardView);
            tableContainer.classList.toggle('floating-card-table', cardView);
            tools.classList.toggle('floating-card-tools', cardView);
            table.closest('.panel')?.classList.toggle('floating-table-cards', cardView);
            try {
                localStorage.setItem(storageKey, selectedView);
            } catch (error) {
                console.error('Table display preference could not be saved:', error);
            }
        };
        rows.forEach((row) => {
            Array.from(row.cells).forEach((cell, index) => {
                const heading = table.tHead.rows[0]?.cells[index];
                if (heading && !cell.hasAttribute('colspan')) {
                    cell.dataset.label = heading.dataset.cardLabel ?? heading.textContent.trim();
                }
            });
        });
        let selectedTableView = 'table';
        try {
            selectedTableView = localStorage.getItem(storageKey) === 'cards' ? 'cards' : 'table';
        } catch (error) {
            console.error('Table display preference could not be read:', error);
        }
        viewButtons.forEach((button, view) => {
            button.addEventListener('click', () => {
                applyView(view);
                render();
                viewSwitch.open = false;
            });
        });
        tableControls.append(navigation, pageSizeLabel, viewSwitch);
        tools.append(tableControls);
        tableContainer.before(tools);
        applyView(selectedTableView);

        const emptyRow = document.createElement('tr');
        emptyRow.hidden = true;
        emptyRow.dataset.tableEmpty = 'true';
        const emptyCell = document.createElement('td');
        emptyCell.className = 'no-data';
        emptyCell.colSpan = table.tHead.rows[0]?.cells.length || 1;
        emptyCell.textContent = 'Tidak ada data yang cocok dengan pencarian atau filter.';
        emptyRow.append(emptyCell);
        tbody.append(emptyRow);

        let currentPage = 1;
        const render = () => {
            const query = search.value.trim().toLocaleLowerCase('id');
            const selectedBuilding = buildingFilter?.value ?? '';
            const matchingRows = rows.filter((row) => {
                const searchableText = `${row.textContent} ${row.dataset.buildingLabel || ''}`.toLocaleLowerCase('id');
                const matchesSearch = !query || searchableText.includes(query);
                const matchesBuilding = !selectedBuilding
                    || (row.dataset.buildingFilter || '').split(',').includes(selectedBuilding);
                const matchesRegions = regionFilters.every((filter, index) => {
                    if (!filter.value) return true;
                    const codes = (row.dataset[`${regionLevels[index]}Codes`] || '').split(',');
                    return codes.includes(filter.value);
                });
                return matchesSearch && matchesBuilding && matchesRegions;
            });
            const pageSize = pageSizeSelect.value === 'all'
                ? matchingRows.length || 1 : Number(pageSizeSelect.value);
            pageSizeLabel.hidden = matchingRows.length <= 5;
            const pageCount = Math.max(1, Math.ceil(matchingRows.length / pageSize));
            currentPage = Math.min(currentPage, pageCount);
            const start = pageSizeSelect.value === 'all' ? 0 : (currentPage - 1) * pageSize;
            const visibleRows = matchingRows.slice(start, start + pageSize);
            const visibleSet = new Set(visibleRows);
            rows.forEach((row) => { row.hidden = !visibleSet.has(row); });
            emptyRow.hidden = matchingRows.length !== 0
                || tableContainer.classList.contains('data-table-card-view');

            const groupCounts = new Map();
            matchingRows.forEach((row) => {
                const groupKey = row.dataset.tableGroupKey;
                if (groupKey !== undefined) groupCounts.set(groupKey, (groupCounts.get(groupKey) || 0) + 1);
            });
            tbody.querySelectorAll('[data-table-group-heading]').forEach((heading) => {
                const key = heading.dataset.tableGroupKey;
                const count = groupCounts.get(key) || 0;
                heading.hidden = !visibleRows.some((row) => row.dataset.tableGroupKey === key);
                const countLabel = heading.querySelector('[data-table-group-count]');
                if (countLabel) countLabel.textContent = `${count} user`;
            });

            const first = matchingRows.length ? start + 1 : 0;
            const last = Math.min(start + visibleRows.length, matchingRows.length);
            summary.textContent = `${first}–${last} dari ${matchingRows.length}`;
            previous.disabled = currentPage <= 1 || pageSizeSelect.value === 'all';
            next.disabled = currentPage >= pageCount || pageSizeSelect.value === 'all';
        };

        search.addEventListener('input', () => {
            if (table.id === 'activity-log-table') {
                document.querySelectorAll('.activity-log-filters input[name="q"],[data-log-delete-search]')
                    .forEach((queryInput) => {
                        if (queryInput instanceof HTMLInputElement) queryInput.value = search.value;
                    });
            }
            currentPage = 1;
            render();
        });
        buildingFilter?.addEventListener('change', () => { currentPage = 1; render(); });
        pageSizeSelect.addEventListener('change', () => { currentPage = 1; render(); });
        previous.addEventListener('click', () => { currentPage -= 1; render(); });
        next.addEventListener('click', () => { currentPage += 1; render(); });
        render();
    });

    document.querySelectorAll('[data-photo-upload]').forEach((uploader) => {
        const input = uploader.querySelector('[data-photo-upload-input]');
        const trigger = uploader.querySelector('[data-photo-upload-trigger]');
        const count = uploader.querySelector('[data-photo-upload-count]');
        const status = uploader.querySelector('[data-photo-upload-status]');
        const grid = uploader.querySelector('[data-photo-upload-grid]');
        if (!(input instanceof HTMLInputElement) || !(trigger instanceof HTMLButtonElement)
            || !count || !status || !grid) return;

        input.setAttribute('aria-hidden', 'true');
        input.tabIndex = -1;
        const limit = Number(uploader.dataset.photoUploadLimit);
        const savedCount = Number(uploader.dataset.photoUploadSaved) || 0;
        const idleLabel = uploader.dataset.photoUploadIdleLabel
            || (savedCount ? `${savedCount} foto tersimpan` : 'Belum ada foto yang dipilih');
        if (!Number.isInteger(limit) || limit < 1) return;
        let pendingFiles = [];
        const syncInputFiles = () => {
            const transfer = new DataTransfer();
            pendingFiles.forEach(({ file }) => transfer.items.add(file));
            input.files = transfer.files;
        };
        const renderPendingFiles = () => {
            grid.replaceChildren();
            pendingFiles.forEach(({ file, previewUrl }, index) => {
                const card = document.createElement('article');
                card.className = 'building-pending-photo photo-upload-pending';
                const image = document.createElement('img');
                image.src = previewUrl;
                image.alt = `Pratinjau ${file.name}`;
                const details = document.createElement('div');
                details.className = 'building-pending-photo-details';
                const name = document.createElement('span');
                name.textContent = file.name;
                const size = document.createElement('small');
                size.textContent = `${(file.size / (1024 * 1024)).toFixed(2)} MB · Siap disimpan`;
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'text-button';
                remove.textContent = 'Hapus pilihan';
                remove.setAttribute('aria-label', `Hapus ${file.name} dari pilihan`);
                remove.addEventListener('click', () => {
                    URL.revokeObjectURL(pendingFiles[index].previewUrl);
                    pendingFiles.splice(index, 1);
                    syncInputFiles();
                    renderPendingFiles();
                    status.textContent = pendingFiles.length
                        ? `${pendingFiles.length} foto baru siap disimpan.`
                        : 'Pilih foto untuk menambahkannya.';
                });
                details.append(name, size, remove);
                card.append(image, details);
                grid.append(card);
            });

            count.textContent = pendingFiles.length
                ? `${savedCount} foto tersimpan · ${pendingFiles.length} foto baru siap disimpan`
                : idleLabel;
            const emptyState = uploader.parentElement?.querySelector('[data-photo-upload-empty]');
            if (emptyState instanceof HTMLElement) emptyState.hidden = savedCount > 0 || pendingFiles.length > 0;
            trigger.disabled = pendingFiles.length >= limit;
        };

        trigger.addEventListener('click', () => input.click());
        input.addEventListener('change', () => {
            const selectedFiles = Array.from(input.files || []);
            if (!selectedFiles.length) return;
            const uniqueFiles = selectedFiles.filter((file) => !pendingFiles.some((pending) =>
                pending.file.name === file.name && pending.file.size === file.size
                    && pending.file.lastModified === file.lastModified
            ));
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            const allowedExtensions = /\.(jpe?g|png|webp)$/i;
            const invalidFile = uniqueFiles.find((file) =>
                file.size < 1 || file.size > 4 * 1024 * 1024
                    || (!allowedTypes.includes(file.type)
                        && !(file.type === '' && allowedExtensions.test(file.name)))
            );
            if (invalidFile) {
                syncInputFiles();
                status.textContent = `${invalidFile.name}: pilih gambar JPG, PNG, atau WebP maksimal 4 MB.`;
                return;
            }
            if (pendingFiles.length + uniqueFiles.length > limit) {
                syncInputFiles();
                status.textContent = `Maksimal ${limit} foto baru dapat ditambahkan.`;
                return;
            }

            uniqueFiles.forEach((file) => pendingFiles.push({
                file,
                previewUrl: URL.createObjectURL(file),
            }));
            syncInputFiles();
            renderPendingFiles();
            status.textContent = pendingFiles.length
                ? `${pendingFiles.length} foto baru siap disimpan bersama perubahan.`
                : 'Pilih foto untuk menambahkannya.';
        });
        input.form?.addEventListener('submit', () => {
            if (!pendingFiles.length) return;
            status.textContent = `Mengunggah ${pendingFiles.length} foto...`;
            trigger.disabled = true;
            document.querySelectorAll('button[type="submit"]').forEach((button) => {
                if (button.form !== input.form) return;
                button.disabled = true;
            });
        });
        input.form?.querySelector('[name="remove_profile_photo"]')?.addEventListener('click', () => {
            pendingFiles.forEach(({ previewUrl }) => URL.revokeObjectURL(previewUrl));
            pendingFiles = [];
            input.value = '';
            renderPendingFiles();
        });
    });

    document.querySelectorAll('input[type="password"]').forEach((input) => {
        let wrapper = input.closest('.password-input-wrap');
        if (wrapper && wrapper.querySelector('[data-password-toggle]')) return;

        if (!wrapper) {
            wrapper = document.createElement('span');
            wrapper.className = 'password-input-wrap';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.append(input);
        }

        if (!input.id) {
            do {
                generatedPasswordId += 1;
                input.id = `password-input-${generatedPasswordId}`;
            } while (document.getElementById(input.id) !== input);
        }

        const toggle = document.createElement('button');
        toggle.className = 'password-visibility-toggle';
        toggle.type = 'button';
        toggle.dataset.passwordToggle = input.id;
        toggle.setAttribute('aria-label', 'Tampilkan kata sandi');
        toggle.setAttribute('aria-pressed', 'false');
        toggle.innerHTML = `
            <svg class="password-eye-icon password-eye-visible" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <svg class="password-eye-icon password-eye-hidden" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                <path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7s-3.6 7-10 7c-1.1 0-2.2-.2-3.1-.6M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7"></path>
            </svg>`;
        wrapper.append(toggle);
    });

    document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const input = document.getElementById(toggle.dataset.passwordToggle);
            if (!(input instanceof HTMLInputElement)) return;

            const isVisible = input.type === 'password';
            input.type = isVisible ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(isVisible));
            toggle.setAttribute('aria-label', isVisible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            toggle.dataset.visible = String(isVisible);
            input.focus();
        });
    });

    const room = document.getElementById('room-select');
    const rate = document.getElementById('rate-input');
    if (room && rate) {
        room.addEventListener('change', () => {
            const option = room.options[room.selectedIndex];
            if (option && option.dataset.rate) {
                rate.value = option.dataset.rate;
                formatMoney(rate);
            }
        });
    }

    const rental = document.getElementById('invoice-rental');
    const invoiceAmount = document.getElementById('invoice-amount');
    if (rental && invoiceAmount) {
        rental.addEventListener('change', () => {
            const option = rental.options[rental.selectedIndex];
            if (option && option.dataset.rate) {
                invoiceAmount.value = option.dataset.rate;
                formatMoney(invoiceAmount);
            }
        });
    }

    const paymentInvoice = document.getElementById('payment-invoice');
    const paymentAmount = document.getElementById('payment-amount');
    if (paymentInvoice && paymentAmount) {
        paymentInvoice.addEventListener('change', () => {
            const option = paymentInvoice.options[paymentInvoice.selectedIndex];
            if (option && option.dataset.remaining) paymentAmount.max = option.dataset.remaining;
        });
    }

    const menuToggle = document.querySelector('.public-menu-toggle');
    const navLinks = document.querySelector('.public-nav-links');
    if (menuToggle && navLinks) {
        menuToggle.addEventListener('click', () => {
            const isOpen = navLinks.classList.toggle('is-open');
            menuToggle.setAttribute('aria-expanded', String(isOpen));
            menuToggle.setAttribute('aria-label', isOpen ? 'Tutup navigasi' : 'Buka navigasi');
        });
        navLinks.addEventListener('click', (event) => {
            if (event.target instanceof HTMLAnchorElement) {
                navLinks.classList.remove('is-open');
                menuToggle.setAttribute('aria-expanded', 'false');
                menuToggle.setAttribute('aria-label', 'Buka navigasi');
            }
        });
    }

    document.querySelectorAll('[data-carousel-target]').forEach((button) => {
        const track = document.getElementById(button.dataset.carouselTarget);
        if (!(track instanceof HTMLElement)) return;
        const arrows = document.querySelectorAll(`[data-carousel-target="${button.dataset.carouselTarget}"]`);
        const updateArrows = () => {
            const maxScroll = track.scrollWidth - track.clientWidth;
            arrows.forEach((arrow) => {
                const direction = arrow.dataset.carouselDirection;
                arrow.disabled = direction === 'left'
                    ? track.scrollLeft <= 2
                    : track.scrollLeft >= maxScroll - 2;
            });
        };
        button.addEventListener('click', () => {
            const direction = button.dataset.carouselDirection === 'left' ? -1 : 1;
            const pageWidth = track.clientWidth * (track.classList.contains('room-carousel-track') ? 1 : 0.8);
            track.scrollBy({ left: direction * Math.max(pageWidth, 220), behavior: 'smooth' });
        });
        track.addEventListener('scroll', updateArrows, { passive: true });
        window.addEventListener('resize', updateArrows);
        updateArrows();
    });

    document.querySelectorAll('[data-dialog-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const accountMenu = trigger.closest('.account-menu');
            if (accountMenu instanceof HTMLDetailsElement) accountMenu.open = false;
            const dialog = document.getElementById(trigger.dataset.dialogOpen);
            if (dialog instanceof HTMLDialogElement) dialog.showModal();
        });

    });

    document.querySelectorAll('[data-dialog-close]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const dialog = trigger.closest('dialog');
            if (dialog instanceof HTMLDialogElement) dialog.close();
        });
    });

    document.querySelectorAll('dialog.action-dialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    });

    const confirmationDialog = document.createElement('dialog');
    confirmationDialog.className = 'action-dialog';
    confirmationDialog.setAttribute('aria-labelledby', 'app-confirmation-title');
    confirmationDialog.innerHTML = `
        <div class="action-dialog-form app-confirmation-content">
            <button type="button" class="dialog-close" aria-label="Tutup" data-confirmation-cancel>×</button>
            <span class="dialog-eyebrow">KONFIRMASI TINDAKAN</span>
            <h2 id="app-confirmation-title">Apakah Anda yakin?</h2>
            <p data-confirmation-message></p>
            <div class="dialog-actions">
                <button class="button secondary" type="button" data-confirmation-cancel>Batal</button>
                <button class="button danger" type="button" data-confirmation-accept>Ya, lanjutkan</button>
            </div>
        </div>`;
    document.body.append(confirmationDialog);
    const confirmationMessage = confirmationDialog.querySelector('[data-confirmation-message]');
    const confirmationCancelButtons = confirmationDialog.querySelectorAll('[data-confirmation-cancel]');
    const confirmationAcceptButton = confirmationDialog.querySelector('[data-confirmation-accept]');
    const approvedForms = new WeakSet();
    let pendingConfirmation = null;
    const confirmAction = (message) => new Promise((resolve) => {
        pendingConfirmation = {resolve};
        if (confirmationMessage) confirmationMessage.textContent = message;
        confirmationDialog.showModal();
    });
    const dismissConfirmation = (confirmed) => {
        confirmationDialog.close();
        pendingConfirmation?.resolve(confirmed);
        pendingConfirmation = null;
    };
    confirmationCancelButtons.forEach((button) => {
        button.addEventListener('click', () => dismissConfirmation(false));
    });
    confirmationAcceptButton?.addEventListener('click', () => dismissConfirmation(true));
    confirmationDialog.addEventListener('click', (event) => {
        if (event.target === confirmationDialog) dismissConfirmation(false);
    });
    confirmationDialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        dismissConfirmation(false);
    });

    document.querySelectorAll('dialog.auth-dialog').forEach((dialog) => {
        let backdropPointerDown = false;
        dialog.addEventListener('pointerdown', (event) => {
            backdropPointerDown = event.target === dialog;
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog && backdropPointerDown) dialog.close();
            backdropPointerDown = false;
        });
        dialog.addEventListener('pointercancel', () => { backdropPointerDown = false; });
    });

    document.addEventListener('keydown', (event) => {
        const input = event.target;
        if (!(input instanceof HTMLTextAreaElement) || input.name !== 'message'
            || event.key !== 'Enter' || event.shiftKey || event.ctrlKey || event.altKey
            || event.metaKey || event.isComposing) return;

        const form = input.form;
        const action = form?.querySelector('input[name="action"]')?.value;
        if (!form || !['send_chat_message', 'start_chat_conversation'].includes(action)) return;

        event.preventDefault();
        form.requestSubmit();
    });

    document.querySelectorAll('[data-bank-settings]').forEach((form) => {
        const accountPicker = form.querySelector('[data-bank-account-picker]');
        const existingField = form.querySelector('[data-bank-existing-field]');
        const sharedNote = form.querySelector('[data-bank-shared-note]');
        const bankName = form.querySelector('[name="bank_name"]');
        const accountName = form.querySelector('[name="account_name"]');
        const accountNumber = form.querySelector('[name="account_number"]');
        const modeInputs = form.querySelectorAll('input[name="bank_mode"]');
        if (!(accountPicker instanceof HTMLSelectElement)
            || !(bankName instanceof HTMLInputElement)
            || !(accountName instanceof HTMLInputElement)
            || !(accountNumber instanceof HTMLInputElement)) return;

        const setMode = (mode, clearNewAccount = false) => {
            const useExisting = mode === 'existing';
            if (existingField instanceof HTMLElement) existingField.hidden = !useExisting;
            accountPicker.disabled = !useExisting;
            accountPicker.required = useExisting;
            if (sharedNote instanceof HTMLElement) sharedNote.hidden = !useExisting;
            const selected = accountPicker.selectedOptions[0];
            if (useExisting && selected?.value) {
                bankName.value = selected.dataset.bankName || '';
                accountName.value = selected.dataset.accountName || '';
                accountNumber.value = selected.dataset.accountNumber || '';
            }
            if (clearNewAccount && !useExisting) {
                bankName.value = '';
                accountName.value = '';
                accountNumber.value = '';
            }
        };

        modeInputs.forEach((input) => {
            input.addEventListener('change', () => setMode(input.value, true));
        });
        accountPicker.addEventListener('change', () => {
            const selected = accountPicker.selectedOptions[0];
            if (!selected?.value) return;
            bankName.value = selected.dataset.bankName || '';
            accountName.value = selected.dataset.accountName || '';
            accountNumber.value = selected.dataset.accountNumber || '';
        });
        const checkedMode = form.querySelector('input[name="bank_mode"]:checked');
        if (checkedMode instanceof HTMLInputElement) setMode(checkedMode.value);
    });

    document.querySelectorAll('.chat-admin-filters').forEach((form) => {
        const search = form.querySelector('input[name="q"]');
        if (!(search instanceof HTMLInputElement)) return;
        let submitTimer = 0;
        const submitFilters = (delay = 0) => {
            window.clearTimeout(submitTimer);
            submitTimer = window.setTimeout(() => form.requestSubmit(), delay);
        };
        search.addEventListener('input', () => {
            submitFilters(300);
        });
        form.querySelectorAll('select').forEach((select) => {
            select.addEventListener('change', () => submitFilters(50));
        });
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('form[data-confirm]')) return;
        if (approvedForms.has(form)) {
            approvedForms.delete(form);
            return;
        }
        event.preventDefault();
        void confirmAction(form.dataset.confirm || 'Lanjutkan tindakan ini?').then((confirmed) => {
            if (!confirmed || !form.isConnected) return;
            approvedForms.add(form);
            form.requestSubmit();
        });
    });

    document.addEventListener('submit', (event) => {
        if (event.defaultPrevented || !(event.target instanceof HTMLFormElement)) return;
        event.target.querySelectorAll('input[data-money-input]').forEach((input) => {
            if (!(input instanceof HTMLInputElement)) return;
            const value = input.value;
            const groupedInteger = /^\d{1,3}(?:\.\d{3})+$/.test(value);
            if (input.dataset.moneyDecimals === '2' && !value.includes(',')
                && /^\d+\.\d{1,2}$/.test(value) && !groupedInteger) return;
            input.value = value.replace(/\./g, '').replace(',', '.');
        });
    });

    const formatMoney = (input) => {
        const allowDecimals = input.dataset.moneyDecimals === '2';
        const cursor = input.selectionStart ?? input.value.length;
        const textBeforeCursor = input.value.slice(0, cursor);
        const digitsBeforeCursor = textBeforeCursor.replace(/\D/g, '').length;
        const commaBeforeCursor = allowDecimals && textBeforeCursor.includes(',');
        const normalized = input.value.replace(allowDecimals ? /[^\d,]/g : /\D/g, '');
        const commaIndex = normalized.indexOf(',');
        const hasDecimal = allowDecimals && commaIndex >= 0;
        let integer = hasDecimal ? normalized.slice(0, commaIndex) : normalized;
        const decimal = hasDecimal ? normalized.slice(commaIndex + 1).replace(/,/g, '').slice(0, 2) : '';
        integer = integer.replace(/^0+(?=\d)/, '');
        const formatted = integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
            + (hasDecimal ? `,${decimal}` : '');
        input.value = formatted;
        if (document.activeElement === input && typeof input.setSelectionRange === 'function') {
            let nextCursor = 0;
            let digitsSeen = 0;
            while (nextCursor < formatted.length && digitsSeen < digitsBeforeCursor) {
                if (/\d/.test(formatted[nextCursor])) digitsSeen += 1;
                nextCursor += 1;
            }
            if (commaBeforeCursor) {
                const formattedComma = formatted.indexOf(',');
                if (formattedComma >= 0) {
                    const decimalDigitsBeforeCursor = textBeforeCursor.slice(textBeforeCursor.indexOf(',') + 1)
                        .replace(/\D/g, '').length;
                    nextCursor = Math.min(formatted.length, formattedComma + 1 + decimalDigitsBeforeCursor);
                }
            }
            input.setSelectionRange(nextCursor, nextCursor);
        }
    };
    document.querySelectorAll('[data-money-input]').forEach((input) => {
        if (!(input instanceof HTMLInputElement)) return;
        input.addEventListener('input', () => formatMoney(input));
        formatMoney(input);
    });

    const chatWidget = document.querySelector('[data-chat-widget]');
    if (chatWidget) {
        const panel = chatWidget.querySelector('#chat-widget-panel');
        const toggle = chatWidget.querySelector('[data-chat-toggle]');
        const close = chatWidget.querySelector('[data-chat-close]');
        const back = chatWidget.querySelector('[data-chat-back]');
        const list = chatWidget.querySelector('[data-chat-conversations]');
        const search = chatWidget.querySelector('[data-chat-search]');
        const threadIdentity = chatWidget.querySelector('[data-chat-thread-identity]');
        const threadActions = chatWidget.querySelector('[data-chat-thread-actions]');
        const messages = chatWidget.querySelector('[data-chat-messages]');
        const compose = chatWidget.querySelector('[data-chat-compose]');
        const messageInput = compose?.querySelector('textarea');
        const readonlyNote = chatWidget.querySelector('[data-chat-readonly]');
        const status = chatWidget.querySelector('[data-chat-status]');
        const unreadBadge = chatWidget.querySelector('[data-chat-total-unread]');
        const supportButton = chatWidget.querySelector('[data-chat-start-support]');
        const folderButtons = chatWidget.querySelectorAll('[data-chat-folder]');
        const emptyTrashButton = chatWidget.querySelector('[data-chat-empty-trash]');
        let activeConversationId = 0;
        let currentFolder = 'inbox';
        let refreshTimer;
        let lastMessageId = 0;
        let listItems = [];
        let loadingMessages = false;
        const conversationDrafts = new Map();
        let dragState = null;
        let suppressToggleClick = false;

        const requestJson = async (url, options) => {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {'Accept': 'application/json', ...(options?.headers || {})},
                ...options,
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.error || 'Chat tidak dapat dimuat.');
            return payload;
        };
        const apiGet = (action, conversationId = 0) => requestJson(
            `index.php?chat_api=1&chat_action=${encodeURIComponent(action)}${conversationId ? `&conversation_id=${conversationId}` : ''}${action === 'list' ? `&folder=${encodeURIComponent(currentFolder)}` : ''}`
        );
        const apiPost = (fields) => {
            const body = new URLSearchParams({
                csrf_token: chatWidget.dataset.chatCsrf || '',
                ...fields,
            });
            return requestJson('index.php?chat_api=1', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                body,
            });
        };
        const showError = (error) => {
            if (status) status.textContent = error.message || 'Chat tidak dapat dimuat.';
        };
        const formatMessageTime = (value) => {
            const parsed = new Date(String(value).replace(' ', 'T'));
            return Number.isNaN(parsed.getTime())
                ? String(value)
                : parsed.toLocaleString('id-ID', {dateStyle: 'short', timeStyle: 'short'});
        };
        const renderConversations = () => {
            if (!list) return;
            const term = (search instanceof HTMLInputElement ? search.value : '').trim().toLocaleLowerCase('id');
            const visible = listItems.filter((conversation) =>
                `${conversation.title} ${conversation.subtitle} ${conversation.last_message}`.toLocaleLowerCase('id').includes(term)
            );
            list.replaceChildren();
            if (!visible.length) {
                const empty = document.createElement('p');
                empty.className = 'chat-widget-empty';
                empty.textContent = term ? 'Tidak ada hasil pencarian.' : 'Belum ada percakapan.';
                list.append(empty);
                return;
            }
            visible.forEach((conversation) => {
                const row = document.createElement('div');
                row.className = 'chat-widget-conversation-row';
                const avatarElement = conversation.profile_photo
                    ? document.createElement('img')
                    : document.createElement('span');
                avatarElement.className = 'chat-avatar';
                if (conversation.profile_photo) {
                    avatarElement.src = conversation.profile_photo;
                    avatarElement.alt = '';
                    avatarElement.addEventListener('error', () => {
                        const fallback = document.createElement('span');
                        fallback.className = 'chat-avatar';
                        fallback.textContent = conversation.profile_name
                            ? String(conversation.profile_name).slice(0, 1).toLocaleUpperCase('id')
                            : '?';
                        avatarElement.replaceWith(fallback);
                    }, {once: true});
                } else {
                    avatarElement.textContent = conversation.profile_name
                        ? String(conversation.profile_name).slice(0, 1).toLocaleUpperCase('id')
                        : '?';
                }
                if (conversation.profile_url) {
                    const profileLink = document.createElement('a');
                    profileLink.className = 'chat-widget-profile-link';
                    profileLink.href = conversation.profile_url;
                    profileLink.setAttribute('aria-label', `Lihat profil ${conversation.profile_name || conversation.title}`);
                    profileLink.title = `Lihat profil ${conversation.profile_name || conversation.title}`;
                    profileLink.append(avatarElement);
                    row.append(profileLink);
                } else {
                    row.append(avatarElement);
                }
                const button = document.createElement('button');
                button.type = 'button';
                button.className = `chat-widget-conversation${Number(conversation.id) === activeConversationId ? ' active' : ''}`;
                const copy = document.createElement('span');
                copy.className = 'chat-widget-conversation-copy';
                const title = document.createElement('strong');
                title.textContent = conversation.title;
                const subtitle = document.createElement('small');
                subtitle.textContent = conversation.last_message || conversation.subtitle;
                copy.append(title, subtitle);
                button.append(copy);
                if (conversation.unread_count > 0) {
                    const unread = document.createElement('i');
                    unread.className = 'chat-unread-count';
                    unread.textContent = String(conversation.unread_count);
                    button.append(unread);
                }
                button.addEventListener('click', () => {
                    if (messageInput instanceof HTMLTextAreaElement && activeConversationId) {
                        conversationDrafts.set(activeConversationId, messageInput.value);
                    }
                    activeConversationId = Number(conversation.id);
                    if (messageInput instanceof HTMLTextAreaElement) {
                        messageInput.value = conversationDrafts.get(activeConversationId) || '';
                    }
                    chatWidget.classList.add('show-thread');
                    renderConversations();
                    loadMessages();
                });
                row.append(button);
                list.append(row);
            });
        };
        const renderThreadActions = (conversation) => {
            if (!threadActions) return;
            threadActions.replaceChildren();
            if (!conversation) return;
            const addAction = (label, action, danger = false) => {
                const actionButton = document.createElement('button');
                actionButton.type = 'button';
                actionButton.className = `chat-widget-thread-action${danger ? ' danger' : ''}`;
                actionButton.textContent = label;
                actionButton.setAttribute('aria-label', `${label}: ${conversation.title}`);
                actionButton.addEventListener('click', async () => {
                    try {
                        await apiPost({chat_action: action, conversation_id: String(conversation.id)});
                        await loadConversations();
                        if (listItems.some((item) => Number(item.id) === Number(conversation.id))) {
                            await loadMessages();
                        }
                    } catch (error) {
                        showError(error);
                    }
                });
                threadActions.append(actionButton);
            };
            if (currentFolder === 'inbox') {
                addAction('Arsipkan', 'archive');
                addAction('Hapus', 'trash', true);
            } else if (currentFolder === 'archive') {
                addAction('Pulihkan', 'restore');
                addAction('Hapus', 'trash', true);
            } else {
                addAction('Pulihkan', 'restore_trash');
            }
        };
        const loadConversations = async () => {
            const payload = await apiGet('list');
            listItems = payload.conversations || [];
            const unreadCount = listItems.reduce((total, item) => total + Number(item.unread_count || 0), 0);
            if (unreadBadge) {
                unreadBadge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
                unreadBadge.hidden = unreadCount === 0;
            }
            renderConversations();
            renderThreadActions(listItems.find((item) => Number(item.id) === activeConversationId));
            if (emptyTrashButton instanceof HTMLButtonElement) {
                emptyTrashButton.hidden = currentFolder !== 'trash' || listItems.length === 0;
            }
            if (status) status.textContent = listItems.length
                ? `${listItems.length} percakapan`
                : (chatWidget.dataset.chatRole === 'admin' ? 'Belum ada chat masuk' : 'Mulai chat atau minta bantuan admin');
        };
        const loadMessages = async () => {
            if (!activeConversationId || loadingMessages || !messages) return;
            const requestedConversationId = activeConversationId;
            loadingMessages = true;
            try {
                const payload = await apiGet('messages', requestedConversationId);
                if (Number(payload.conversation.id) !== activeConversationId) return;
                const currentLatestId = payload.messages.length
                    ? Number(payload.messages[payload.messages.length - 1].id) : 0;
                const shouldScroll = currentLatestId !== lastMessageId;
                lastMessageId = currentLatestId;
                const title = document.createElement('strong');
                title.textContent = payload.conversation.title;
                const subtitle = document.createElement('small');
                subtitle.textContent = payload.conversation.subtitle;
                threadIdentity?.replaceChildren(title, subtitle);
                renderThreadActions(listItems.find((item) => Number(item.id) === activeConversationId));
                messages.replaceChildren();
                if (!payload.messages.length) {
                    const empty = document.createElement('div');
                    empty.className = 'chat-widget-empty';
                    empty.textContent = 'Belum ada pesan. Tulis pesan untuk memulai.';
                    messages.append(empty);
                }
                payload.messages.forEach((message) => {
                    const bubble = document.createElement('article');
                    bubble.className = `chat-message${Number(message.sender_user_id) === Number(chatWidget.dataset.chatUserId) ? ' mine' : ''}`;
                    const body = document.createElement('p');
                    body.textContent = message.body;
                    const time = document.createElement('small');
                    time.textContent = formatMessageTime(message.created_at);
                    bubble.append(body, time);
                    messages.append(bubble);
                });
                if (compose) compose.hidden = !payload.conversation.can_send;
                if (readonlyNote) {
                    readonlyNote.hidden = payload.conversation.can_send;
                    readonlyNote.textContent = payload.conversation.personal_status === 'archived'
                        ? 'Chat di Arsip Anda. Pulihkan ke Kotak masuk untuk mengirim pesan.'
                        : (payload.conversation.personal_status === 'trash'
                            ? 'Chat di Sampah. Pulihkan ke Kotak masuk untuk mengirim pesan.'
                            : 'Chat User dan Owner hanya dapat ditinjau admin, bukan dibalas.');
                }
                if (shouldScroll) messages.scrollTop = messages.scrollHeight;
                await loadConversations();
            } catch (error) {
                showError(error);
            } finally {
                loadingMessages = false;
                if (activeConversationId && activeConversationId !== requestedConversationId) {
                    loadMessages();
                }
            }
        };
        const openPanel = async () => {
            if (!panel || !toggle) return;
            panel.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            try {
                await loadConversations();
                if (activeConversationId) await loadMessages();
            } catch (error) {
                showError(error);
            }
            clearInterval(refreshTimer);
            refreshTimer = window.setInterval(async () => {
                try {
                    await loadConversations();
                    if (activeConversationId) await loadMessages();
                } catch (error) {
                    showError(error);
                }
            }, 7000);
        };
        const closePanel = () => {
            if (!panel || !toggle) return;
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            clearInterval(refreshTimer);
        };
        const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
        const finishLauncherDrag = (event) => {
            if (!dragState || dragState.pointerId !== event.pointerId) return;
            const state = dragState;
            dragState = null;
            chatWidget.classList.remove('is-dragging');
            if (!state.moved) return;

            const bounds = chatWidget.getBoundingClientRect();
            const dockLeft = bounds.left + bounds.width / 2 < window.innerWidth / 2;
            const left = dockLeft ? 8 : window.innerWidth - bounds.width - 8;
            const top = clamp(bounds.top, 8, window.innerHeight - bounds.height - 8);
            chatWidget.style.left = `${left}px`;
            chatWidget.style.top = `${top}px`;
            chatWidget.style.right = 'auto';
            chatWidget.style.bottom = 'auto';
            suppressToggleClick = true;
            window.setTimeout(() => {
                suppressToggleClick = false;
            }, 400);
        };
        toggle?.addEventListener('pointerdown', (event) => {
            if (event.button !== 0 || !toggle) return;
            const bounds = chatWidget.getBoundingClientRect();
            dragState = {
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                startLeft: bounds.left,
                startTop: bounds.top,
                width: bounds.width,
                height: bounds.height,
                moved: false,
            };
            chatWidget.classList.add('is-dragging');
            toggle.setPointerCapture(event.pointerId);
        });
        toggle?.addEventListener('pointermove', (event) => {
            if (!dragState || dragState.pointerId !== event.pointerId) return;
            const deltaX = event.clientX - dragState.startX;
            const deltaY = event.clientY - dragState.startY;
            if (!dragState.moved && Math.hypot(deltaX, deltaY) < 6) return;
            dragState.moved = true;
            const left = clamp(dragState.startLeft + deltaX, 8, window.innerWidth - dragState.width - 8);
            const top = clamp(dragState.startTop + deltaY, 8, window.innerHeight - dragState.height - 8);
            chatWidget.style.left = `${left}px`;
            chatWidget.style.top = `${top}px`;
            chatWidget.style.right = 'auto';
            chatWidget.style.bottom = 'auto';
        });
        toggle?.addEventListener('pointerup', finishLauncherDrag);
        toggle?.addEventListener('pointercancel', finishLauncherDrag);
        window.addEventListener('resize', () => {
            if (dragState || !chatWidget.style.left) return;
            const bounds = chatWidget.getBoundingClientRect();
            chatWidget.style.left = `${clamp(bounds.left, 8, window.innerWidth - bounds.width - 8)}px`;
            chatWidget.style.top = `${clamp(bounds.top, 8, window.innerHeight - bounds.height - 8)}px`;
        });
        toggle?.addEventListener('click', (event) => {
            if (suppressToggleClick) {
                event.preventDefault();
                suppressToggleClick = false;
                return;
            }
            panel?.hidden ? openPanel() : closePanel();
        });
        close?.addEventListener('click', closePanel);
        back?.addEventListener('click', () => chatWidget.classList.remove('show-thread'));
        search?.addEventListener('input', renderConversations);
        folderButtons.forEach((button) => {
            button.addEventListener('click', () => {
                if (!(button instanceof HTMLButtonElement)) return;
                currentFolder = button.dataset.chatFolder || 'inbox';
                folderButtons.forEach((folderButton) => {
                    folderButton.setAttribute('aria-pressed', String(folderButton === button));
                });
                loadConversations().catch(showError);
            });
        });
        emptyTrashButton?.addEventListener('click', async () => {
            if (!await confirmAction('Kosongkan Sampah secara permanen? Salinan peserta lain tetap terjaga.')) return;
            try {
                await apiPost({chat_action: 'empty_trash'});
                await loadConversations();
            } catch (error) {
                showError(error);
            }
        });
        messageInput?.addEventListener('input', () => {
            if (activeConversationId && messageInput instanceof HTMLTextAreaElement) {
                conversationDrafts.set(activeConversationId, messageInput.value);
            }
        });
        supportButton?.addEventListener('click', async () => {
            try {
                const payload = await apiPost({chat_action: 'start_support'});
                activeConversationId = Number(payload.conversation_id);
                if (messageInput instanceof HTMLTextAreaElement) {
                    messageInput.value = conversationDrafts.get(activeConversationId) || '';
                }
                chatWidget.classList.add('show-thread');
                await loadConversations();
                await loadMessages();
                messageInput?.focus();
            } catch (error) {
                showError(error);
            }
        });
        compose?.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!(messageInput instanceof HTMLTextAreaElement) || !messageInput.value.trim()) return;
            const sendingConversationId = activeConversationId;
            const messageBody = messageInput.value;
            conversationDrafts.set(sendingConversationId, messageBody);
            const sendButton = compose.querySelector('button[type="submit"]');
            if (sendButton instanceof HTMLButtonElement) sendButton.disabled = true;
            try {
                await apiPost({
                    chat_action: 'send',
                    conversation_id: String(sendingConversationId),
                    message: messageBody,
                });
                conversationDrafts.delete(sendingConversationId);
                if (activeConversationId === sendingConversationId) messageInput.value = '';
                lastMessageId = 0;
                await loadMessages();
            } catch (error) {
                showError(error);
            } finally {
                if (sendButton instanceof HTMLButtonElement) sendButton.disabled = false;
            }
        });
    }

    const photoLightbox = document.getElementById('photo-lightbox');
    const photoPreview = photoLightbox?.querySelector('img');
    if (photoLightbox instanceof HTMLDialogElement && photoPreview instanceof HTMLImageElement) {
        document.querySelectorAll('[data-photo-preview]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                photoPreview.src = trigger.dataset.photoPreview;
                photoPreview.alt = trigger.dataset.photoAlt || '';
                photoLightbox.showModal();
            });
        });
        photoLightbox.querySelectorAll('[data-photo-preview-close]').forEach((trigger) => {
            trigger.addEventListener('click', () => photoLightbox.close());
        });
        photoLightbox.addEventListener('click', (event) => {
            if (event.target === photoLightbox) photoLightbox.close();
        });
        photoLightbox.addEventListener('close', () => {
            photoPreview.removeAttribute('src');
            photoPreview.alt = '';
        });
    }

    initializeSelectDropdowns();
    const addedSelectObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof Element) initializeSelectDropdowns(node);
            });
        });
    });
    addedSelectObserver.observe(document.body, { childList: true, subtree: true });

    const accountMenu = document.querySelector('.account-menu');
    if (accountMenu) {
        document.addEventListener('click', (event) => {
            if (!accountMenu.contains(event.target)) accountMenu.open = false;
        });
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeApp, { once: true });
} else {
    initializeApp();
}
