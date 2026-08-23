@extends('layouts.app', ['title' => 'New transfer'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">New transfer</h1>
        <p class="page-subtitle">Current branch: {{ $branch->name }}. Choose how many of each package to move; every included book is multiplied by that quantity.</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('transfers.index') }}">Back</a>
</div>

<form method="POST" action="{{ route('transfers.store') }}" class="panel" data-submit-lock>
    @csrf
    <div class="space-y-5 p-5">
        <div class="form-grid">
            <div>
                <label class="form-label" for="transfer-type">Transfer type *</label>
                <select name="transfer_type" id="transfer-type">
                    <option value="branch_to_branch" @selected(old('transfer_type', 'branch_to_branch') === 'branch_to_branch')>Books: branch to branch</option>
                    <option value="package_to_branch" @selected(old('transfer_type') === 'package_to_branch')>Packages: transfer stock to another branch</option>
                    <option value="branch_to_user" @selected(old('transfer_type') === 'branch_to_user')>Books: branch stock to user custody</option>
                </select>
            </div>
            <div>
                <label class="form-label">Academic year</label>
                <select name="academic_year_id">
                    <option value="">Not specified</option>
                    @foreach($years as $year)<option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>{{ $year->name }}</option>@endforeach
                </select>
            </div>
        </div>

        <div data-branch-fields>
            <label class="form-label">Destination branch *</label>
            <select name="destination_branch_id">
                <option value="">Select branch</option>
                @foreach($branches as $destination)<option value="{{ $destination->id }}" @selected(old('destination_branch_id') == $destination->id)>{{ $destination->name }} ({{ $destination->code }})</option>@endforeach
            </select>
        </div>

        <div class="form-grid hidden" data-user-fields>
            <div>
                <label class="form-label">To user *</label>
                <select name="destination_user_id"><option value="">Select user</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(old('destination_user_id') == $user->id)>{{ $user->name }}</option>@endforeach</select>
            </div>
        </div>

        <div class="hidden" data-package-fields>
            <label class="form-label">Book packages and quantities *</label>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Transfer</th><th>Package</th><th>Books in each package</th><th>Package quantity</th></tr></thead>
                    <tbody>
                        @foreach($packages as $package)
                            @php($isSelected = in_array($package->id, old('package_ids', [])))
                            <tr data-package-row>
                                <td><label class="flex items-center gap-2"><input type="checkbox" name="package_ids[]" value="{{ $package->id }}" data-package-choice @checked($isSelected)><span class="sr-only">Transfer {{ $package->name }}</span></label></td>
                                <td>{{ $package->name }} <span class="text-xs text-slate-500">{{ $package->academicYear?->name }} · {{ $package->grade?->name }}{{ $package->section ? '. '.$package->section->name : '' }}</span></td>
                                <td>{{ $package->items->count() }}</td>
                                <td><input type="number" name="package_quantities[{{ $package->id }}]" min="1" max="100000" value="{{ old('package_quantities.'.$package->id, 1) }}" data-package-quantity aria-label="Quantity of {{ $package->name }} packages"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-1 text-xs text-slate-500">For example, quantity 10 for Grade 1 sends 10 times every book in the Grade 1 package. The package setup is also mirrored in the destination branch.</p>
            @error('package_ids')<p class="form-error">{{ $message }}</p>@enderror
            @error('package_quantities')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div data-book-fields>
            <div class="mb-2">
                <div><label class="form-label mb-0">Books *</label><p class="mt-1 text-xs text-slate-500">Scan a book barcode and press Enter. The book is added with quantity 1 and the next line opens automatically; scanning the same barcode again increases its quantity.</p></div>
            </div>
            <div class="space-y-2" data-transfer-lines>
                <div class="form-grid transfer-line">
                    <div>
                        <input type="text" data-transfer-barcode autocomplete="off" placeholder="Scan book barcode" autofocus>
                        <input type="hidden" name="items[0][book_id]" value="{{ old('items.0.book_id') }}" data-transfer-book-id>
                        <p class="mt-1 text-xs text-slate-500" data-transfer-book-summary aria-live="polite">Awaiting barcode scan</p>
                    </div>
                    <div class="flex gap-2"><input type="number" name="items[0][quantity]" min="1" required placeholder="Quantity" value="{{ old('items.0.quantity') }}" data-transfer-quantity><button type="button" class="btn btn-secondary" data-remove-line>Remove</button></div>
                </div>
            </div>
        </div>

        <div><label class="form-label">Notes</label><textarea name="notes" rows="2">{{ old('notes') }}</textarea></div>
    </div>
    <div class="flex justify-end border-t border-slate-200 p-5"><button class="btn btn-primary">Complete transfer</button></div>
</form>

<template id="transfer-line-template">
    <div class="form-grid transfer-line">
        <div><input type="text" data-transfer-barcode autocomplete="off" placeholder="Scan book barcode"><input type="hidden" data-transfer-book-id><p class="mt-1 text-xs text-slate-500" data-transfer-book-summary aria-live="polite">Awaiting barcode scan</p></div>
        <div class="flex gap-2"><input type="number" min="1" required placeholder="Quantity" data-transfer-quantity><button type="button" class="btn btn-secondary" data-remove-line>Remove</button></div>
    </div>
</template>

<script>
const kind = document.getElementById('transfer-type');
const branchFields = document.querySelector('[data-branch-fields]');
const userFields = document.querySelector('[data-user-fields]');
const packageFields = document.querySelector('[data-package-fields]');
const bookFields = document.querySelector('[data-book-fields]');
const lines = document.querySelector('[data-transfer-lines]');
const template = document.getElementById('transfer-line-template');
const transferBooks = [
    @foreach ($books as $book)
        { id: @json((string) $book->id), barcode: @json((string) $book->barcode), title: @json($book->title) },
    @endforeach
];

function swap() {
    const type = kind.value;
    const isPackageTransfer = type === 'package_to_branch';
    const isBranchTransfer = type === 'branch_to_branch' || isPackageTransfer;
    branchFields.classList.toggle('hidden', !isBranchTransfer);
    userFields.classList.toggle('hidden', isBranchTransfer);
    packageFields.classList.toggle('hidden', !isPackageTransfer);
    refreshPackageInputs(isPackageTransfer);
    bookFields.classList.toggle('hidden', isPackageTransfer);
    bookFields.querySelectorAll('input, select').forEach((field) => {
        if (isPackageTransfer) {
            field.disabled = true;
            return;
        }

        field.disabled = !field.matches('[data-transfer-barcode]')
            && field.closest('.transfer-line')?.dataset.transferPending === 'true';
    });
}

function refreshPackageInputs(isPackageTransfer = kind.value === 'package_to_branch') {
    document.querySelectorAll('[data-package-choice]').forEach((choice) => {
        const quantity = choice.closest('[data-package-row]').querySelector('[data-package-quantity]');
        choice.disabled = !isPackageTransfer;
        quantity.disabled = !isPackageTransfer || !choice.checked;
        quantity.required = isPackageTransfer && choice.checked;
    });
}

function names() {
    lines.querySelectorAll('.transfer-line').forEach((line, index) => {
        line.querySelector('[data-transfer-book-id]').name = `items[${index}][book_id]`;
        line.querySelector('[data-transfer-quantity]').name = `items[${index}][quantity]`;
    });
}

function setPendingTransferLine(line, isPending) {
    line.dataset.transferPending = String(isPending);
    line.querySelectorAll('[data-transfer-book-id], [data-transfer-quantity]').forEach((field) => {
        field.disabled = isPending;
    });
}

function appendTransferLine(isPending = true) {
    lines.append(template.content.cloneNode(true));
    names();

    const line = [...lines.querySelectorAll('.transfer-line')].at(-1);
    setPendingTransferLine(line, isPending);

    return line;
}

function matchingTransferBook(line) {
    const barcode = line.querySelector('[data-transfer-barcode]')?.value.trim().toLocaleLowerCase();
    if (!barcode) return null;

    return transferBooks.find((book) => book.barcode.toLocaleLowerCase() === barcode) ?? null;
}

function setTransferBookSummary(line, message) {
    const summary = line.querySelector('[data-transfer-book-summary]');
    if (summary) summary.textContent = message;
}

function advanceTransferScan(line) {
    setPendingTransferLine(line, false);
    const book = matchingTransferBook(line);
    const barcode = line.querySelector('[data-transfer-barcode]');
    const bookId = line.querySelector('[data-transfer-book-id]');
    const quantity = line.querySelector('[data-transfer-quantity]');
    if (!book || !barcode || !bookId || !quantity) {
        setTransferBookSummary(line, barcode?.value.trim() ? 'Barcode not found. Scan a barcode from the catalogue.' : 'Scan a book barcode before pressing Enter.');
        barcode?.focus();
        return;
    }

    const duplicate = [...lines.querySelectorAll('.transfer-line')].find((candidate) => candidate !== line
        && candidate.querySelector('[data-transfer-book-id]')?.value === book.id);
    if (duplicate) {
        const duplicateQuantity = duplicate.querySelector('[data-transfer-quantity]');
        duplicateQuantity.value = String((Number.parseInt(duplicateQuantity.value, 10) || 0) + 1);
        bookId.value = '';
        barcode.value = '';
        setTransferBookSummary(line, 'Awaiting barcode scan');
        setPendingTransferLine(line, true);
        barcode.focus();
        return;
    }

    bookId.value = book.id;
    setTransferBookSummary(line, book.title);
    if (!quantity.value) quantity.value = '1';

    const currentLines = [...lines.querySelectorAll('.transfer-line')];
    const nextLine = currentLines[currentLines.indexOf(line) + 1];
    const nextBarcode = nextLine?.querySelector('[data-transfer-barcode]');
    const target = nextBarcode && !nextBarcode.value.trim() ? nextLine : appendTransferLine();
    requestAnimationFrame(() => target?.querySelector('[data-transfer-barcode]')?.focus());
}

kind.addEventListener('change', swap);
document.querySelectorAll('[data-package-choice]').forEach((choice) => choice.addEventListener('change', () => refreshPackageInputs()));
lines.addEventListener('click', (event) => {
    if (event.target.matches('[data-remove-line]') && lines.children.length > 1) {
        event.target.closest('.transfer-line').remove();
        names();
    }
});
lines.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || !event.target.matches('[data-transfer-barcode]')) return;
    event.preventDefault();
    advanceTransferScan(event.target.closest('.transfer-line'));
});
lines.addEventListener('input', (event) => {
    if (event.target.matches('[data-transfer-barcode]')) {
        const line = event.target.closest('.transfer-line');
        line.querySelector('[data-transfer-book-id]').value = '';
        setTransferBookSummary(line, 'Ready to scan barcode');
        setPendingTransferLine(line, false);
    }
});
names();
lines.querySelectorAll('.transfer-line').forEach((line) => {
    const bookId = line.querySelector('[data-transfer-book-id]');
    const book = transferBooks.find((candidate) => candidate.id === bookId.value);
    if (book) {
        line.querySelector('[data-transfer-barcode]').value = book.barcode;
        setTransferBookSummary(line, book.title);
        setPendingTransferLine(line, false);
    } else {
        setPendingTransferLine(line, true);
    }
});
swap();
</script>
@endsection
