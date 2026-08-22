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
    const index = Math.max(-1, ...[...container.querySelectorAll('[name]')]
        .map((field) => Number.parseInt(field.name.match(/^items\[(\d+)]/)?.[1] ?? '-1', 10))) + 1;
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

document.querySelectorAll('[data-report-type-select]').forEach((reportSelect) => {
    reportSelect.addEventListener('change', () => {
        const form = reportSelect.form;
        if (!form) return;

        if (reportSelect.value !== 'student_information') {
            form.querySelectorAll('[name="student_fields_selection"], [name="student_fields[]"]').forEach((field) => {
                field.disabled = true;
            });
        }

        form.submit();
    });
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
    const saleTotal = form.querySelector('[data-distribution-sale-total]');
    const saleSubtotal = form.querySelector('[data-distribution-sale-subtotal]');
    const saleTax = form.querySelector('[data-distribution-sale-tax]');

    if (!registrationSelect || !packageSelect || !lines || !lineTemplate || !packageData || !bookData) return;

    const packages = JSON.parse(packageData.textContent || '[]');
    const books = JSON.parse(bookData.textContent || '[]');
    const booksById = new Map(books.map((book) => [String(book.id), book]));
    const booksByCode = new Map();
    books.forEach((book) => {
        [book.barcode, book.isbn].filter(Boolean).forEach((code) => booksByCode.set(String(code).toLocaleLowerCase(), book));
    });
    const booksByTitle = new Map(books.map((book) => [String(book.title).toLocaleLowerCase(), book]));
    const sameId = (left, right) => String(left) === String(right);
    const findBook = (value) => booksByCode.get(String(value ?? '').trim().toLocaleLowerCase())
        ?? booksByTitle.get(String(value ?? '').trim().toLocaleLowerCase());
    const cents = (value) => Math.round(Number(value || 0) * 100);
    const money = (value) => (value / 100).toFixed(2);
    const selectedPackage = () => packages.find((bookPackage) => sameId(bookPackage.id, packageSelect.value));
    const priceForLine = (book, packageItem) => {
        const cataloguePrice = cents(book.selling_price);
        if (!packageItem || packageItem.price_type === 'paid') return cataloguePrice;
        if (packageItem.price_type === 'free') return 0;

        return Math.min(cataloguePrice, Math.max(0, cents(packageItem.unit_price)));
    };
    const refreshSaleTotal = () => {
        if (!saleTotal || !saleSubtotal || !saleTax) return;

        const packageItems = new Map((selectedPackage()?.items ?? []).map((item) => [String(item.book_id), item]));
        let subtotal = 0;
        let tax = 0;
        lines.querySelectorAll('[data-line]').forEach((line) => {
            const barcodeInput = line.querySelector('[data-distribution-barcode]');
            const quantityInput = line.querySelector('input[name$="[quantity]"]');
            const book = findBook(barcodeInput?.value);
            const quantity = Math.max(0, Number.parseInt(quantityInput?.value || '0', 10) || 0);
            if (!book || quantity === 0 || !Object.hasOwn(book, 'selling_price')) return;

            const base = priceForLine(book, packageItems.get(String(book.id))) * quantity;
            const taxRate = cents(book.tax_rate);
            subtotal += base;
            tax += Math.floor((base * taxRate + 5000) / 10000);
        });

        saleSubtotal.textContent = money(subtotal);
        saleTax.textContent = money(tax);
        saleTotal.textContent = money(subtotal + tax);
    };
    const refreshBookSummary = (line) => {
        const barcodeInput = line.querySelector('[data-distribution-barcode]');
        const summary = line.querySelector('[data-book-summary]');
        if (!barcodeInput || !summary) return;
        const book = findBook(barcodeInput.value);
        summary.textContent = book
            ? `${book.title} — ${book.available_quantity} available${Object.hasOwn(book, 'selling_price') ? `, sale ${Number(book.selling_price).toFixed(2)}` : ''}`
            : (barcodeInput.value.trim() ? 'Barcode not found' : 'Awaiting barcode scan');
        refreshSaleTotal();
    };
    const nextDistributionLineIndex = () => Math.max(-1, ...[...lines.querySelectorAll('[data-distribution-barcode]')]
        .map((input) => Number.parseInt(input.name.match(/^items\[(\d+)]/)?.[1] ?? '-1', 10))) + 1;
    const appendDistributionLine = () => {
        const row = lineTemplate.content.cloneNode(true);
        row.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replaceAll('__INDEX__', String(nextDistributionLineIndex()));
        });
        lines.append(row);
        const line = [...lines.querySelectorAll('[data-line]')].at(-1);
        refreshBookSummary(line);

        return line;
    };
    const advanceDistributionScan = (line) => {
        const barcodeInput = line.querySelector('[data-distribution-barcode]');
        const book = findBook(barcodeInput?.value);
        if (!barcodeInput || !book) {
            refreshBookSummary(line);
            return;
        }

        const existingLine = [...lines.querySelectorAll('[data-line]')].find((candidate) => candidate !== line
            && sameId(findBook(candidate.querySelector('[data-distribution-barcode]')?.value)?.id, book.id));
        if (existingLine) {
            const quantityInput = existingLine.querySelector('input[name$="[quantity]"]');
            quantityInput.value = String((Number.parseInt(quantityInput.value, 10) || 0) + 1);
            barcodeInput.value = '';
            refreshBookSummary(line);
            refreshSaleTotal();
            barcodeInput.focus();
            return;
        }

        const currentLines = [...lines.querySelectorAll('[data-line]')];
        const nextLine = currentLines[currentLines.indexOf(line) + 1];
        const nextBarcodeInput = nextLine?.querySelector('[data-distribution-barcode]');
        const target = nextBarcodeInput && !nextBarcodeInput.value.trim() ? nextLine : appendDistributionLine();
        refreshSaleTotal();
        requestAnimationFrame(() => target.querySelector('[data-distribution-barcode]')?.focus());
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
        refreshSaleTotal();
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
            refreshSaleTotal();
            return;
        }

        const selectedPackage = matchingPackages.find((bookPackage) => sameId(bookPackage.id, previousPackageId))
            ?? matchingPackages.find((bookPackage) => sameId(bookPackage.section_id, registration.dataset.sectionId))
            ?? matchingPackages.find((bookPackage) => bookPackage.section_id === null)
            ?? matchingPackages[0];

        packageSelect.value = String(selectedPackage.id);
        renderLines(selectedPackage.items);
        updateHint(selectedPackage);
        refreshSaleTotal();
    };

    registrationSelect.addEventListener('change', refreshPackages);
    form.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || !event.target.matches('[data-distribution-barcode]')) return;
        event.preventDefault();
        advanceDistributionScan(event.target.closest('[data-line]'));
    });
    form.addEventListener('input', (event) => {
        if (event.target.matches('[data-distribution-barcode]')) refreshBookSummary(event.target.closest('[data-line]'));
        if (event.target.matches('input[name$="[quantity]"]')) refreshSaleTotal();
    });
    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-distribution-barcode]')) refreshBookSummary(event.target.closest('[data-line]'));
        if (event.target.matches('input[name$="[quantity]"]')) refreshSaleTotal();
    });
    packageSelect.addEventListener('change', () => {
        const selectedPackage = packages.find((bookPackage) => sameId(bookPackage.id, packageSelect.value));
        if (!selectedPackage) {
            renderLines();
            updateHint(null);
            refreshSaleTotal();
            return;
        }
        renderLines(selectedPackage.items);
        updateHint(selectedPackage);
        refreshSaleTotal();
    });

    refreshPackages();
    lines.querySelectorAll('[data-line]').forEach(refreshBookSummary);
    refreshSaleTotal();

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-add-row]');
        if (button?.dataset.addRow === '#distribution-lines') {
            requestAnimationFrame(refreshSaleTotal);
        }
    });
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-remove-row]')) {
            requestAnimationFrame(refreshSaleTotal);
        }
    });
});

document.querySelectorAll('[data-package-form]').forEach((form) => {
    const bookData = document.querySelector(form.dataset.packageBookData);
    if (!bookData) return;

    const books = JSON.parse(bookData.textContent || '[]');
    const booksByCode = new Map();
    books.forEach((book) => {
        [book.barcode, book.isbn].filter(Boolean).forEach((code) => booksByCode.set(String(code).toLocaleLowerCase(), book));
    });
    const booksByTitle = new Map(books.map((book) => [String(book.title).toLocaleLowerCase(), book]));
    const findBook = (value) => booksByCode.get(String(value ?? '').trim().toLocaleLowerCase())
        ?? booksByTitle.get(String(value ?? '').trim().toLocaleLowerCase());
    const refreshBookSummary = (line) => {
        const barcodeInput = line.querySelector('[data-package-barcode]');
        const summary = line.querySelector('[data-package-book-summary]');
        if (!barcodeInput || !summary) return;

        const book = findBook(barcodeInput.value);
        summary.textContent = book
            ? `${book.title} — sale ${Number(book.selling_price).toFixed(2)}`
            : (barcodeInput.value.trim() ? 'Barcode not found' : 'Awaiting barcode scan');
    };

    const nextPackageLineIndex = () => Math.max(-1, ...[...form.querySelectorAll('[data-package-barcode]')]
        .map((input) => Number.parseInt(input.name.match(/^items\[(\d+)]/)?.[1] ?? '-1', 10))) + 1;
    const appendPackageLine = () => {
        const container = form.querySelector('#package-lines');
        const template = document.querySelector('#package-line-template');
        if (!container || !template) return null;

        const row = template.content.cloneNode(true);
        row.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replaceAll('__INDEX__', String(nextPackageLineIndex()));
        });
        container.append(row);
        const line = [...container.querySelectorAll('[data-line]')].at(-1);
        refreshBookSummary(line);

        return line;
    };
    const advancePackageScan = (line) => {
        const barcodeInput = line.querySelector('[data-package-barcode]');
        const book = findBook(barcodeInput?.value);
        if (!barcodeInput || !book) {
            refreshBookSummary(line);
            return;
        }

        const existingLine = [...form.querySelectorAll('[data-line]')].find((candidate) => candidate !== line
            && String(findBook(candidate.querySelector('[data-package-barcode]')?.value)?.id) === String(book.id));
        if (existingLine) {
            const quantityInput = existingLine.querySelector('input[name$="[quantity]"]');
            quantityInput.value = String((Number.parseInt(quantityInput.value, 10) || 0) + 1);
            barcodeInput.value = '';
            refreshBookSummary(line);
            barcodeInput.focus();
            return;
        }

        const packageLines = [...form.querySelectorAll('[data-line]')];
        const nextLine = packageLines[packageLines.indexOf(line) + 1];
        const nextBarcodeInput = nextLine?.querySelector('[data-package-barcode]');
        const target = nextBarcodeInput && !nextBarcodeInput.value.trim() ? nextLine : appendPackageLine();
        requestAnimationFrame(() => target?.querySelector('[data-package-barcode]')?.focus());
    };

    form.addEventListener('input', (event) => {
        if (event.target.matches('[data-package-barcode]')) refreshBookSummary(event.target.closest('[data-line]'));
    });
    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-package-barcode]')) refreshBookSummary(event.target.closest('[data-line]'));
    });
    form.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || !event.target.matches('[data-package-barcode]')) return;
        event.preventDefault();
        advancePackageScan(event.target.closest('[data-line]'));
    });
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-add-row]');
        if (button?.dataset.addRow === '#package-lines') {
            requestAnimationFrame(() => form.querySelectorAll('[data-line]').forEach(refreshBookSummary));
        }
    });

    form.querySelectorAll('[data-line]').forEach(refreshBookSummary);
});
