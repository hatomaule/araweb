const initializeApp = () => {
    let generatedPasswordId = 0;
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
            if (option && option.dataset.rate) rate.value = option.dataset.rate;
        });
    }

    const rental = document.getElementById('invoice-rental');
    const invoiceAmount = document.getElementById('invoice-amount');
    if (rental && invoiceAmount) {
        rental.addEventListener('change', () => {
            const option = rental.options[rental.selectedIndex];
            if (option && option.dataset.rate) invoiceAmount.value = option.dataset.rate;
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
            track.scrollBy({ left: direction * Math.max(track.clientWidth * 0.8, 220), behavior: 'smooth' });
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
