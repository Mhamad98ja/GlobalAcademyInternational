import './bootstrap';

const appShell = document.querySelector('[data-app-shell]');
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');
const desktopSidebar = window.matchMedia('(min-width: 1024px)');

if (appShell && sidebarToggle) {
    const savedCollapsedState = () => {
        try {
            return localStorage.getItem('sbm.sidebar.collapsed') === 'true';
        } catch {
            return false;
        }
    };

    const updateToggleState = () => {
        const expanded = desktopSidebar.matches
            ? !appShell.classList.contains('sidebar-collapsed')
            : appShell.classList.contains('sidebar-open');
        sidebarToggle.setAttribute('aria-expanded', String(expanded));
    };

    const setMobileMenu = (isOpen) => {
        appShell.classList.toggle('sidebar-open', isOpen);
        updateToggleState();
    };

    const setDesktopCollapsed = (isCollapsed) => {
        appShell.classList.toggle('sidebar-collapsed', isCollapsed);
        try {
            localStorage.setItem('sbm.sidebar.collapsed', String(isCollapsed));
        } catch {
            // The navigation still works when browser storage is unavailable.
        }
        updateToggleState();
    };

    const syncSidebarMode = () => {
        if (desktopSidebar.matches) {
            appShell.classList.remove('sidebar-open');
            appShell.classList.toggle('sidebar-collapsed', savedCollapsedState());
        } else {
            appShell.classList.remove('sidebar-collapsed', 'sidebar-open');
        }
        updateToggleState();
    };

    sidebarToggle.addEventListener('click', () => {
        if (desktopSidebar.matches) {
            setDesktopCollapsed(!appShell.classList.contains('sidebar-collapsed'));
        } else {
            setMobileMenu(!appShell.classList.contains('sidebar-open'));
        }
    });

    sidebarBackdrop?.addEventListener('click', () => setMobileMenu(false));
    document.querySelectorAll('[data-sidebar-close]').forEach((button) => button.addEventListener('click', () => setMobileMenu(false)));
    document.querySelectorAll('#primary-sidebar a').forEach((link) => link.addEventListener('click', () => {
        if (!desktopSidebar.matches) setMobileMenu(false);
    }));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !desktopSidebar.matches) setMobileMenu(false);
    });
    desktopSidebar.addEventListener('change', syncSidebarMode);
    syncSidebarMode();
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-add-row]');
    if (!button) return;
    const container = document.querySelector(button.dataset.addRow);
    const template = document.querySelector(button.dataset.template);
    if (!container || !template) return;
    const index = container.querySelectorAll('[data-line]').length;
    const fragment = template.content.cloneNode(true);
    fragment.querySelectorAll('[name]').forEach((field) => {
        field.name = field.name.replaceAll('__INDEX__', String(index));
    });
    container.append(fragment);
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-row]');
    if (button) button.closest('[data-line]')?.remove();
});

document.querySelectorAll('[data-grade-select]').forEach((gradeSelect) => {
    const sectionSelect = document.querySelector(gradeSelect.dataset.sectionTarget);
    if (!sectionSelect) return;

    const filterSections = () => {
        const gradeId = gradeSelect.value;

        [...sectionSelect.options].forEach((option) => {
            if (!option.dataset.gradeId) return;
            const isRelated = option.dataset.gradeId === gradeId;
            option.hidden = !isRelated;
            option.disabled = !isRelated;
        });

        const chosenOption = sectionSelect.options[sectionSelect.selectedIndex];
        if (chosenOption?.disabled) sectionSelect.selectedIndex = 0;
        sectionSelect.disabled = !gradeId;
    };

    gradeSelect.addEventListener('change', filterSections);
    filterSections();
});

const normaliseBookSearch = (value) => String(value ?? '').trim().toLocaleLowerCase();

const refreshBookPicker = (searchInput) => {
    const picker = searchInput.closest('[data-book-picker]');
    const select = picker?.querySelector('[data-book-select]');
    if (!select) return;

    const query = normaliseBookSearch(searchInput.value);
    const options = [...select.options].filter((option) => option.value);
    const exactMatch = options.find((option) => (
        normaliseBookSearch(option.dataset.bookBarcode) === query
        || normaliseBookSearch(option.dataset.bookTitle) === query
    ));

    options.forEach((option) => {
        const searchable = `${option.dataset.bookBarcode ?? ''} ${option.dataset.bookTitle ?? ''}`;
        const matches = !query || normaliseBookSearch(searchable).includes(query);
        option.hidden = !matches;
        option.disabled = !matches;
    });

    if (exactMatch && select.value !== exactMatch.value) {
        select.value = exactMatch.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }
};

document.addEventListener('input', (event) => {
    if (event.target.matches('[data-book-search]')) refreshBookPicker(event.target);
});

document.addEventListener('change', (event) => {
    if (!event.target.matches('[data-book-select]')) return;

    const picker = event.target.closest('[data-book-picker]');
    const searchInput = picker?.querySelector('[data-book-search]');
    const option = event.target.options[event.target.selectedIndex];
    if (searchInput && option?.value) {
        searchInput.value = `${option.dataset.bookBarcode ?? ''} — ${option.dataset.bookTitle ?? ''}`.trim();
    }
});

document.querySelectorAll('[data-book-search]').forEach(refreshBookPicker);

document.querySelectorAll('[data-supplier-purchase-form]').forEach((form) => {
    const updatePurchaseLine = (line) => {
        const bookSelect = line.querySelector('[data-purchase-book]');
        const purchasePriceInput = line.querySelector('[data-unit-purchase-price]');
        const sellingPrice = line.querySelector('[data-catalogue-sale-price]');
        const selectedBook = bookSelect?.options[bookSelect.selectedIndex];

        if (!bookSelect || !purchasePriceInput || !sellingPrice || !selectedBook?.value) {
            if (sellingPrice) sellingPrice.textContent = '—';
            return;
        }

        const suggestedPurchasePrice = selectedBook.dataset.purchasePrice ?? '0.00';
        sellingPrice.textContent = selectedBook.dataset.sellingPrice ?? '0.00';
        if (!purchasePriceInput.value || purchasePriceInput.value === purchasePriceInput.dataset.suggestedPrice) {
            purchasePriceInput.value = suggestedPurchasePrice;
        }
        purchasePriceInput.dataset.suggestedPrice = suggestedPurchasePrice;
    };

    form.querySelectorAll('[data-line]').forEach(updatePurchaseLine);
    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-purchase-book]')) updatePurchaseLine(event.target.closest('[data-line]'));
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-add-row]');
        if (button?.dataset.addRow === '#purchase-lines') {
            requestAnimationFrame(() => form.querySelectorAll('[data-line]').forEach(updatePurchaseLine));
        }
    });
});

document.querySelectorAll('[data-distribution-form]').forEach((form) => {
    const registrationSelect = form.querySelector('[data-distribution-registration]');
    const packageSelect = form.querySelector('[data-distribution-package]');
    const lines = document.querySelector(form.dataset.linesTarget);
    const lineTemplate = document.querySelector(form.dataset.lineTemplate);
    const packageData = document.querySelector(form.dataset.packageData);
    const bookData = document.querySelector('#distribution-book-data');
    const packageHint = form.querySelector('[data-package-hint]');

    if (!registrationSelect || !packageSelect || !lines || !lineTemplate || !packageData || !bookData) return;

    const packages = JSON.parse(packageData.textContent || '[]');
    const books = JSON.parse(bookData.textContent || '[]');
    const booksById = new Map(books.map((book) => [String(book.id), book]));
    const booksByBarcode = new Map([...booksById.values()].map((book) => [String(book.barcode).toLocaleLowerCase(), book]));
    const booksByTitle = new Map(books.map((book) => [String(book.title).toLocaleLowerCase(), book]));
    const sameId = (left, right) => String(left) === String(right);
    const refreshBookSummary = (line) => {
        const barcodeInput = line.querySelector('[data-distribution-barcode]');
        const summary = line.querySelector('[data-book-summary]');
        if (!barcodeInput || !summary) return;
        const search = barcodeInput.value.trim().toLocaleLowerCase();
        const book = booksByBarcode.get(search) ?? booksByTitle.get(search);
        summary.textContent = book
            ? `${book.title} — ${book.available_quantity} available${Object.hasOwn(book, 'selling_price') ? `, sale ${Number(book.selling_price).toFixed(2)}` : ''}`
            : (barcodeInput.value.trim() ? 'Barcode not found' : 'Awaiting barcode scan');
    };
    const renderLines = (items = []) => {
        const fragment = document.createDocumentFragment();
        const rows = items.length ? items : [{ book_id: '', quantity: 1 }];

        rows.forEach((item, index) => {
            const row = lineTemplate.content.cloneNode(true);
            row.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replaceAll('__INDEX__', String(index));
            });
            const barcodeInput = row.querySelector('[data-distribution-barcode]');
            const quantityInput = row.querySelector('input[name$="[quantity]"]');
            if (barcodeInput && item.book_id) barcodeInput.value = booksById.get(String(item.book_id))?.barcode ?? '';
            if (quantityInput) quantityInput.value = String(item.quantity ?? 1);
            refreshBookSummary(row);
            fragment.append(row);
        });

        lines.replaceChildren(fragment);
    };

    const updateHint = (bookPackage, sameGradePackages = []) => {
        if (!packageHint) return;
        if (bookPackage) {
            packageHint.textContent = `${bookPackage.name} loaded. You can adjust quantities, remove books, or add extra books.`;
            return;
        }

        if (!registrationSelect.value) {
            packageHint.textContent = 'Choose a student registration to see its matching book packages.';
            return;
        }

        if (sameGradePackages.length) {
            const sections = [...new Set(sameGradePackages.map((bookPackage) => bookPackage.section_label))].join(', ');
            packageHint.textContent = `No package matches Section ${registrationSelect.options[registrationSelect.selectedIndex].dataset.sectionLabel}. Available package sections: ${sections}. Edit a package and choose “All sections” to share it across the grade.`;
            return;
        }

        packageHint.textContent = 'No book package exists for this academic year and grade. Add one in Book packages, or add books manually.';
    };

    const refreshPackages = () => {
        const registration = registrationSelect.options[registrationSelect.selectedIndex];
        const previousPackageId = packageSelect.value;

        if (!registration?.value) {
            [...packageSelect.options].forEach((option) => {
                if (option.value) {
                    option.hidden = true;
                    option.disabled = true;
                }
            });
            packageSelect.value = '';
            packageSelect.disabled = true;
            updateHint(null);
            return;
        }

        const sameGradePackages = packages.filter((bookPackage) => (
            sameId(bookPackage.academic_year_id, registration.dataset.academicYearId)
            && sameId(bookPackage.grade_id, registration.dataset.gradeId)
        ));
        const matchingPackages = sameGradePackages.filter((bookPackage) => (
            bookPackage.section_id === null || sameId(bookPackage.section_id, registration.dataset.sectionId)
        ));

        [...packageSelect.options].forEach((option) => {
            if (!option.value) return;
            const isMatching = matchingPackages.some((bookPackage) => sameId(bookPackage.id, option.value));
            option.hidden = !isMatching;
            option.disabled = !isMatching;
        });

        packageSelect.disabled = matchingPackages.length === 0;
        if (matchingPackages.length === 0) {
            packageSelect.value = '';
            if (previousPackageId) renderLines();
            updateHint(null, sameGradePackages);
            return;
        }

        const selectedPackage = matchingPackages.find((bookPackage) => sameId(bookPackage.id, previousPackageId))
            ?? matchingPackages.find((bookPackage) => sameId(bookPackage.section_id, registration.dataset.sectionId))
            ?? matchingPackages.find((bookPackage) => bookPackage.section_id === null)
            ?? matchingPackages[0];

        packageSelect.value = String(selectedPackage.id);
        renderLines(selectedPackage.items);
        updateHint(selectedPackage);
    };

    registrationSelect.addEventListener('change', refreshPackages);
    form.addEventListener('input', (event) => {
        if (event.target.matches('[data-distribution-barcode]')) refreshBookSummary(event.target.closest('[data-line]'));
    });
    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-distribution-barcode]')) refreshBookSummary(event.target.closest('[data-line]'));
    });
    packageSelect.addEventListener('change', () => {
        const selectedPackage = packages.find((bookPackage) => sameId(bookPackage.id, packageSelect.value));
        if (!selectedPackage) {
            renderLines();
            updateHint(null);
            return;
        }
        renderLines(selectedPackage.items);
        updateHint(selectedPackage);
    });

    refreshPackages();
    lines.querySelectorAll('[data-line]').forEach(refreshBookSummary);
});

document.querySelectorAll('[data-package-form]').forEach((form) => {
    const bookData = document.querySelector(form.dataset.packageBookData);
    if (!bookData) return;

    const books = JSON.parse(bookData.textContent || '[]');
    const booksByBarcode = new Map(books.map((book) => [String(book.barcode).toLocaleLowerCase(), book]));
    const booksByTitle = new Map(books.map((book) => [String(book.title).toLocaleLowerCase(), book]));
    const refreshBookSummary = (line) => {
        const barcodeInput = line.querySelector('[data-package-barcode]');
        const summary = line.querySelector('[data-package-book-summary]');
        if (!barcodeInput || !summary) return;

        const search = barcodeInput.value.trim().toLocaleLowerCase();
        const book = booksByBarcode.get(search) ?? booksByTitle.get(search);
        summary.textContent = book
            ? `${book.title} — sale ${Number(book.selling_price).toFixed(2)}`
            : (barcodeInput.value.trim() ? 'Barcode not found' : 'Awaiting barcode scan');
    };

    form.addEventListener('input', (event) => {
        if (event.target.matches('[data-package-barcode]')) refreshBookSummary(event.target.closest('[data-line]'));
    });
    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-package-barcode]')) refreshBookSummary(event.target.closest('[data-line]'));
    });
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-add-row]');
        if (button?.dataset.addRow === '#package-lines') {
            requestAnimationFrame(() => form.querySelectorAll('[data-line]').forEach(refreshBookSummary));
        }
    });

    form.querySelectorAll('[data-line]').forEach(refreshBookSummary);
});
