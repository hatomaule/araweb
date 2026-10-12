const initializeApp = () => {
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
