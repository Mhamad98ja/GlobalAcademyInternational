@extends('layouts.app', ['title' => $list->exists ? 'Edit book package' : 'New book package'])

@section('content')
@php
    $packageItems = old('items', $list->exists
        ? $list->items->map(fn ($item) => [
            'barcode' => $item->book?->barcode,
            'quantity' => $item->quantity,
            'price_type' => $item->price_type,
            'unit_price' => $item->price_type === 'discounted' ? $item->unit_price : 0,
            'is_mandatory' => $item->is_mandatory,
        ])->values()->all()
        : [['barcode' => '', 'quantity' => 1, 'price_type' => 'paid', 'unit_price' => 0, 'is_mandatory' => true]]);
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">{{ $list->exists ? 'Edit book package' : 'New book package' }}</h1>
        <p class="page-subtitle">A package can apply to all sections in a grade or to one specific section.</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('class-lists.index') }}">Cancel</a>
</div>

<form method="POST" action="{{ $list->exists ? route('class-lists.update', $list) : route('class-lists.store') }}" class="panel" data-submit-lock data-package-form data-package-book-data="#package-book-data">
    @csrf
    @if ($list->exists)
        @method('PUT')
    @endif

    <div class="space-y-6 p-5">
        <div class="form-grid">
            <div>
                <label class="form-label">Academic year *</label>
                <select name="academic_year_id" required>
                    <option value="">Select year</option>
                    @foreach ($years as $year)
                        <option value="{{ $year->id }}" @selected(old('academic_year_id', $list->academic_year_id) == $year->id)>{{ $year->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Grade *</label>
                <select name="grade_id" required data-grade-select data-section-target="#class-list-section">
                    <option value="">Select grade</option>
                    @foreach ($grades as $grade)
                        <option value="{{ $grade->id }}" @selected(old('grade_id', $list->grade_id) == $grade->id)>{{ $grade->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Section</label>
                <select id="class-list-section" name="section_id" disabled>
                    <option value="">All sections</option>
                    @foreach ($grades as $grade)
                        @foreach ($grade->sections as $section)
                            <option value="{{ $section->id }}" data-grade-id="{{ $grade->id }}" @selected(old('section_id', $list->section_id) == $section->id)>{{ $grade->name }}.{{ $section->name }}</option>
                        @endforeach
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Choose “All sections” to make this package available for every section in the selected grade.</p>
            </div>
            <div>
                <label class="form-label">Package name *</label>
                <input name="name" required value="{{ old('name', $list->name) }}" placeholder="e.g. Grade 4 full package">
            </div>
            <div class="md:col-span-2">
                <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $list->exists ? $list->is_active : true))>Active package</label>
            </div>
        </div>

        <div>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="panel-title">Package books</h2>
                    <p class="mt-1 text-xs text-slate-500">Scan an ISBN or barcode and press Enter to add it, then continue scanning. Scanning a book already listed increases its quantity. Paid books always use their current catalogue sale price. Use Discounted only when this package needs a lower unit price; Free issues the book at zero.</p>
                </div>
                <button type="button" class="btn btn-secondary btn-small" data-add-row="#package-lines" data-template="#package-line-template">Add book</button>
            </div>
            <div class="table-wrap">
                <table class="data-table min-w-[760px]">
                    <thead>
                        <tr><th>ISBN, barcode or book name *</th><th>Book</th><th>Qty</th><th>Price type</th><th>Discounted unit price</th><th>Mandatory</th><th></th></tr>
                    </thead>
                    <tbody id="package-lines" data-scanner-managed-lines>
                        @foreach ($packageItems as $index => $item)
                            <tr data-line>
                                <td>
                                    <input name="items[{{ $index }}][barcode]" data-package-barcode required autocomplete="off" placeholder="Scan ISBN, barcode or type book name" list="package-book-options" value="{{ data_get($item, 'barcode') }}" @if ($loop->first) autofocus @endif>
                                </td>
                                <td class="font-medium text-sm" data-package-book-summary>Awaiting barcode scan</td>
                                <td><input type="number" min="1" value="{{ data_get($item, 'quantity', 1) }}" name="items[{{ $index }}][quantity]" required></td>
                                <td>
                                    <select name="items[{{ $index }}][price_type]">
                                        @foreach (['paid' => 'Paid', 'free' => 'Free', 'discounted' => 'Discounted'] as $value => $label)
                                            <option value="{{ $value }}" @selected(data_get($item, 'price_type', 'paid') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" min="0" step="0.01" value="{{ data_get($item, 'unit_price', 0) }}" name="items[{{ $index }}][unit_price]" required><p class="mt-1 text-xs text-slate-500">Used only for Discounted.</p></td>
                                <td><input type="checkbox" name="items[{{ $index }}][is_mandatory]" value="1" @checked(data_get($item, 'is_mandatory', true))></td>
                                <td><button type="button" class="link text-xs text-rose-700" data-remove-row>Remove</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="flex justify-end border-t border-slate-200 p-5">
        <button class="btn btn-primary">{{ $list->exists ? 'Save changes' : 'Save package' }}</button>
    </div>
</form>

<template id="package-line-template">
    <tr data-line>
        <td>
            <input name="items[__INDEX__][barcode]" data-package-barcode required autocomplete="off" placeholder="Scan ISBN, barcode or type book name" list="package-book-options">
        </td>
        <td class="font-medium text-sm" data-package-book-summary>Awaiting barcode scan</td>
        <td><input type="number" min="1" value="1" name="items[__INDEX__][quantity]" required></td>
        <td>
            <select name="items[__INDEX__][price_type]">
                <option value="paid">Paid</option>
                <option value="free">Free</option>
                <option value="discounted">Discounted</option>
            </select>
        </td>
        <td><input type="number" min="0" step="0.01" value="0" name="items[__INDEX__][unit_price]" required><p class="mt-1 text-xs text-slate-500">Used only for Discounted.</p></td>
        <td><input type="checkbox" name="items[__INDEX__][is_mandatory]" value="1" checked></td>
        <td><button type="button" class="link text-xs text-rose-700" data-remove-row>Remove</button></td>
    </tr>
</template>
<script type="application/json" id="package-book-data">{!! json_encode($bookData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
<datalist id="package-book-options">@foreach ($bookData as $book)<option value="{{ $book['barcode'] }}">{{ $book['title'] }}</option>@if ($book['isbn'])<option value="{{ $book['isbn'] }}">{{ $book['title'] }}</option>@endif<option value="{{ $book['title'] }}">{{ $book['barcode'] }}</option>@endforeach</datalist>
@endsection
