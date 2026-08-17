@extends('layouts.app', ['title' => 'New transfer'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">New transfer</h1>
        <p class="page-subtitle">Current branch: {{ $branch->name }}. Book transfers move physical stock; package transfers copy the package configuration.</p>
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
                    <option value="package_to_branch" @selected(old('transfer_type') === 'package_to_branch')>Packages: copy to another branch</option>
                    <option value="branch_to_user" @selected(old('transfer_type') === 'branch_to_user')>Books: branch stock to user custody</option>
                    <option value="user_to_user" @selected(old('transfer_type') === 'user_to_user')>Books: user to user custody</option>
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
            <div data-source-user>
                <label class="form-label">From user *</label>
                <select name="source_user_id"><option value="">Select user</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(old('source_user_id') == $user->id)>{{ $user->name }}</option>@endforeach</select>
            </div>
            <div>
                <label class="form-label">To user *</label>
                <select name="destination_user_id"><option value="">Select user</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(old('destination_user_id') == $user->id)>{{ $user->name }}</option>@endforeach</select>
            </div>
        </div>

        <div class="hidden" data-package-fields>
            <label class="form-label" for="transfer-packages">Book packages *</label>
            <select id="transfer-packages" name="package_ids[]" multiple size="6" data-package-select>
                @foreach($packages as $package)
                    <option value="{{ $package->id }}" @selected(in_array($package->id, old('package_ids', [])))>{{ $package->name }} - {{ $package->academicYear?->name }} - {{ $package->grade?->name }}{{ $package->section ? '. '.$package->section->name : '' }} ({{ $package->items->count() }} books)</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Select one or more packages. The destination receives the same package setup and prices; book stock is not moved by this action.</p>
            @error('package_ids')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div data-book-fields>
            <div class="mb-2 flex items-center justify-between">
                <div><label class="form-label mb-0">Books *</label><p class="mt-1 text-xs text-slate-500">Choose the physical stock to move.</p></div>
                <button type="button" class="btn btn-secondary text-xs" data-add-line>Add book</button>
            </div>
            <div class="space-y-2" data-transfer-lines>
                <div class="form-grid transfer-line">
                    <div data-book-picker>
                        <input type="search" list="transfer-book-options" data-book-search autocomplete="off" placeholder="Search barcode or book name">
                        <select name="items[0][book_id]" required data-book-select><option value="">Select book</option>@foreach($books as $book)<option value="{{ $book->id }}" data-book-barcode="{{ $book->barcode }}" data-book-title="{{ $book->title }}" @selected(old('items.0.book_id') == $book->id)>{{ $book->barcode }} - {{ $book->title }} - available: {{ $book->available_quantity }}</option>@endforeach</select>
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
        <div data-book-picker><input type="search" list="transfer-book-options" data-book-search autocomplete="off" placeholder="Search barcode or book name"><select required data-book-select><option value="">Select book</option>@foreach($books as $book)<option value="{{ $book->id }}" data-book-barcode="{{ $book->barcode }}" data-book-title="{{ $book->title }}">{{ $book->barcode }} - {{ $book->title }} - available: {{ $book->available_quantity }}</option>@endforeach</select></div>
        <div class="flex gap-2"><input type="number" min="1" required placeholder="Quantity" data-transfer-quantity><button type="button" class="btn btn-secondary" data-remove-line>Remove</button></div>
    </div>
</template>

<x-book-search-options id="transfer-book-options" :books="$books" />

<script>
const kind = document.getElementById('transfer-type');
const branchFields = document.querySelector('[data-branch-fields]');
const userFields = document.querySelector('[data-user-fields]');
const sourceUser = document.querySelector('[data-source-user]');
const packageFields = document.querySelector('[data-package-fields]');
const packageSelect = document.querySelector('[data-package-select]');
const bookFields = document.querySelector('[data-book-fields]');
const lines = document.querySelector('[data-transfer-lines]');
const template = document.getElementById('transfer-line-template');

function swap() {
    const type = kind.value;
    const isPackageTransfer = type === 'package_to_branch';
    const isBranchTransfer = type === 'branch_to_branch' || isPackageTransfer;
    branchFields.classList.toggle('hidden', !isBranchTransfer);
    userFields.classList.toggle('hidden', isBranchTransfer);
    sourceUser.classList.toggle('hidden', type === 'branch_to_user');
    packageFields.classList.toggle('hidden', !isPackageTransfer);
    packageSelect.disabled = !isPackageTransfer;
    packageSelect.required = isPackageTransfer;
    bookFields.classList.toggle('hidden', isPackageTransfer);
    bookFields.querySelectorAll('input, select').forEach((field) => { field.disabled = isPackageTransfer; });
}

function names() {
    lines.querySelectorAll('.transfer-line').forEach((line, index) => {
        line.querySelector('[data-book-select]').name = `items[${index}][book_id]`;
        line.querySelector('[data-transfer-quantity]').name = `items[${index}][quantity]`;
    });
}

kind.addEventListener('change', swap);
document.querySelector('[data-add-line]').addEventListener('click', () => { lines.append(template.content.cloneNode(true)); names(); });
lines.addEventListener('click', (event) => {
    if (event.target.matches('[data-remove-line]') && lines.children.length > 1) {
        event.target.closest('.transfer-line').remove();
        names();
    }
});
swap();
</script>
@endsection
