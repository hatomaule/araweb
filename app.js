const initializeApp = () => {
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

    document.querySelectorAll('.main-area .content table').forEach((table) => {
        if (!(table instanceof HTMLTableElement) || !table.tHead || !table.tBodies.length) return;
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
        searchLabel.append(searchCaption, search);
        tools.append(searchLabel);

        let buildingFilter;
        if (table.hasAttribute('data-building-filter')) {
            const buildings = new Map();
            rows.forEach((row) => {
                const value = row.dataset.buildingFilter;
                if (value !== undefined && !buildings.has(value)) {
                    buildings.set(value, row.dataset.buildingLabel || value);
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
        pageSizeSelect.add(new Option('10', '10'));
        pageSizeSelect.add(new Option('Semua', 'all'));
        pageSizeLabel.append(pageSizeCaption, pageSizeSelect);
        tools.append(pageSizeLabel);

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
        tools.append(navigation);
        const tableContainer = table.closest('.table-scroll') || table;
        tableContainer.before(tools);

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
                const matchesBuilding = !selectedBuilding || row.dataset.buildingFilter === selectedBuilding;
                const matchesRegions = regionFilters.every((filter, index) => {
                    if (!filter.value) return true;
                    const codes = (row.dataset[`${regionLevels[index]}Codes`] || '').split(',');
                    return codes.includes(filter.value);
                });
                return matchesSearch && matchesBuilding && matchesRegions;
            });
            const pageSize = pageSizeSelect.value === 'all' ? matchingRows.length || 1 : 10;
            const pageCount = Math.max(1, Math.ceil(matchingRows.length / pageSize));
            currentPage = Math.min(currentPage, pageCount);
            const start = pageSizeSelect.value === 'all' ? 0 : (currentPage - 1) * pageSize;
            const visibleRows = matchingRows.slice(start, start + pageSize);
            const visibleSet = new Set(visibleRows);
            rows.forEach((row) => { row.hidden = !visibleSet.has(row); });
            emptyRow.hidden = matchingRows.length !== 0;

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
            summary.textContent = `Menampilkan ${first}–${last} dari ${matchingRows.length} data`;
            previous.disabled = currentPage <= 1 || pageSizeSelect.value === 'all';
            next.disabled = currentPage >= pageCount || pageSizeSelect.value === 'all';
        };

        search.addEventListener('input', () => { currentPage = 1; render(); });
        buildingFilter?.addEventListener('change', () => { currentPage = 1; render(); });
        pageSizeSelect.addEventListener('change', () => { currentPage = 1; render(); });
        previous.addEventListener('click', () => { currentPage -= 1; render(); });
        next.addEventListener('click', () => { currentPage += 1; render(); });
        render();
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
