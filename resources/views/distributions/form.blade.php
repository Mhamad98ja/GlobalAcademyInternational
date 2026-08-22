@extends('layouts.app', ['title' => 'New distribution'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">New book distribution</h1>
        <p class="page-subtitle">Select the student registration to load its matching book package, then adjust it if needed.</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('distributions.index') }}">Cancel</a>
</div>

<form method="POST" action="{{ route('distributions.store') }}" class="panel" data-submit-lock data-distribution-form data-lines-target="#distribution-lines" data-line-template="#distribution-line-template" data-package-data="#distribution-package-data">
    @csrf

    <div class="space-y-6 p-5">
        <div class="form-grid">
            <div class="md:col-span-2">
                <label class="form-label">Active student registration *</label>
                <select name="student_registration_id" required data-distribution-registration>
                    <option value="">Search/select a registration</option>
                    @foreach ($registrations as $registration)
                        <option value="{{ $registration->id }}" data-academic-year-id="{{ $registration->academic_year_id }}" data-grade-id="{{ $registration->grade_id }}" data-section-id="{{ $registration->section_id ?? '' }}" data-section-label="{{ $registration->section?->name ?? 'No section' }}" @selected(old('student_registration_id', $selectedRegistration) == $registration->id)>
                            {{ $registration->student->full_name }} - {{ $registration->academicYear->name }} - {{ $registration->grade->name }}{{ $registration->section ? '.'.$registration->section->name : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Scanner users can focus this field and type a known registration ID.</p>
            </div>

            <div class="md:col-span-2">
                <label class="form-label">Book package</label>
                <select name="class_book_list_id" disabled data-distribution-package>
                    <option value="">No package - select books manually</option>
                    @foreach ($packages as $package)
                        <option value="{{ $package->id }}" @selected(old('class_book_list_id') == $package->id)>{{ $package->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-teal-700" data-package-hint>Choose a student registration to see its matching book packages.</p>
            </div>
        </div>

        <div>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="panel-title">Books to issue</h2>
                    <p class="mt-1 text-xs text-slate-500">Scan an ISBN or barcode and press Enter to add the book, then continue scanning. Scan the same book again to increase its quantity.@if ($canViewFinancials) Books use their current catalogue sale price. @endif</p>
                </div>
                <button type="button" class="btn btn-secondary btn-small" data-add-row="#distribution-lines" data-template="#distribution-line-template">Add book</button>
            </div>
            <div class="table-wrap">
                <table class="data-table min-w-[650px]">
                    <thead>
                        <tr><th>ISBN, barcode or book name *</th><th>Book</th><th>Quantity</th><th></th></tr>
                    </thead>
                    <tbody id="distribution-lines">
                        <tr data-line>
                            <td>
                            <input name="items[0][barcode]" data-distribution-barcode required autofocus autocomplete="off" placeholder="Scan ISBN, barcode or type book name" list="distribution-book-options">
                            </td>
                            <td class="font-medium text-sm" data-book-summary>Awaiting barcode scan</td>
                            <td><input name="items[0][quantity]" type="number" min="1" value="1" required></td>
                            <td><button type="button" class="link text-xs text-rose-700" data-remove-row>Remove</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @if ($canViewFinancials)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3" aria-live="polite" data-distribution-sale-summary>
                    <div><div class="text-sm font-semibold text-teal-950">Total sale</div><div class="text-xs text-teal-800">Calculated from the current books, quantities, package prices, and tax.</div></div>
                    <div class="text-right"><div class="text-xs font-medium uppercase tracking-wide text-teal-800">Invoice total</div><output class="text-2xl font-bold text-teal-950" data-distribution-sale-total>0.00</output><div class="text-xs text-teal-800">Subtotal <span data-distribution-sale-subtotal>0.00</span> · Tax <span data-distribution-sale-tax>0.00</span></div></div>
                </div>
            @endif
        </div>

        @if ($canViewFinancials)<div>
            <h2 class="panel-title">Payment at distribution</h2>
            <div class="form-grid mt-4">
                <div>
                    <label class="form-label">Payment amount</label>
                    <input type="number" step="0.01" min="0.01" name="payment[amount]" placeholder="Leave blank for unpaid">
                </div>
                <div>
                    <label class="form-label">Method</label>
                    <select name="payment[method]">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="bank_transfer">Bank transfer</option>
                        <option value="check">Check</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Payment date</label>
                    <input type="date" name="payment[payment_date]" value="{{ today()->format('Y-m-d') }}">
                </div>
                <div>
                    <label class="form-label">Reference</label>
                    <input name="payment[reference_number]">
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="payment[deferred]" value="1">Deferred payment pending approval</label>
                </div>
            </div>
        </div>@endif

        <div>
            <label class="form-label">Notes</label>
            <textarea name="notes" rows="3"></textarea>
        </div>
    </div>

    <div class="flex justify-end border-t border-slate-200 p-5">
        <button class="btn btn-primary">Complete distribution</button>
    </div>
</form>

<template id="distribution-line-template">
    <tr data-line>
        <td>
            <input name="items[__INDEX__][barcode]" data-distribution-barcode required autocomplete="off" placeholder="Scan ISBN, barcode or type book name" list="distribution-book-options">
        </td>
        <td class="font-medium text-sm" data-book-summary>Awaiting barcode scan</td>
        <td><input name="items[__INDEX__][quantity]" type="number" min="1" value="1" required></td>
        <td><button type="button" class="link text-xs text-rose-700" data-remove-row>Remove</button></td>
    </tr>
</template>

<script type="application/json" id="distribution-package-data">{!! json_encode($packageData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
<script type="application/json" id="distribution-book-data">{!! json_encode($bookData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
<datalist id="distribution-book-options">@foreach ($bookData as $book)<option value="{{ $book['barcode'] }}">{{ $book['title'] }}</option>@if ($book['isbn'])<option value="{{ $book['isbn'] }}">{{ $book['title'] }}</option>@endif<option value="{{ $book['title'] }}">{{ $book['barcode'] }}</option>@endforeach</datalist>
@endsection
