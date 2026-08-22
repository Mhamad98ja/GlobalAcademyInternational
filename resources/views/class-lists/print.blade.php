@extends('layouts.app', ['title' => 'Print '.$list->name])

@section('content')
@php
    $schoolName = $school['school_name'] ?? 'Global Academy International';
    $schoolContact = collect([$school['school_address'] ?? null, $school['school_phone'] ?? null])->filter()->implode(' - ');
@endphp

@include('components.print-document-styles')

<div class="page-header no-print">
    <div>
        <h1 class="page-title">Print grade book list</h1>
        <p class="page-subtitle">Only the selected package and grade are included in this printout.</p>
    </div>
    <div class="flex gap-2">
        <a class="btn btn-secondary" href="{{ route('books.index', ['tab' => 'packages']) }}">Back to packages</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Print this list</button>
    </div>
</div>

<article class="print-document">
    <header class="print-masthead">
        <div class="print-brand">
            @include('components.print-school-mark')
            <div>
                <div class="print-school-name">{{ $schoolName }}</div>
                @if ($schoolContact)<div class="print-school-contact">{{ $schoolContact }}</div>@endif
            </div>
        </div>
        <div class="print-document-kind">School operations<br>Grade book list</div>
    </header>

    <div class="print-document-body">
        <div class="print-title-row">
            <div>
                <h1 class="print-title">{{ $list->grade?->name ?? 'Grade' }} book list</h1>
                <div class="print-title-note">{{ $list->name }}{{ $list->section ? ' · Section '.$list->section->name : ' · All sections' }}</div>
                <div class="print-title-note">Prepared {{ now()->format('d M Y, H:i') }} by {{ auth()->user()->name }}</div>
            </div>
            <div class="print-reference">
                <div class="print-reference-row"><span>Academic year</span><strong>{{ $list->academicYear?->name ?? '-' }}</strong></div>
                <div class="print-reference-row"><span>Books</span><strong>{{ number_format($list->items->count()) }}</strong></div>
                <div class="print-reference-row"><span>Printed on</span><strong>{{ now()->format('d M Y') }}</strong></div>
            </div>
        </div>

        <table class="data-table w-full">
            <thead><tr><th>Book</th><th>ISBN</th><th>Barcode</th><th>Quantity</th><th>Required</th></tr></thead>
            <tbody>
                @forelse ($list->items as $item)
                    <tr><td>{{ $item->book?->title }}</td><td>{{ $item->book?->isbn ?: '-' }}</td><td>{{ $item->book?->barcode ?: '-' }}</td><td>{{ $item->quantity }}</td><td>{{ $item->is_mandatory ? 'Yes' : 'Optional' }}</td></tr>
                @empty
                    <tr><td colspan="5" class="empty">This package does not contain books yet.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="print-signatures">
            <div class="print-signature">Prepared by: {{ auth()->user()->name }}</div>
            <div class="print-signature">Checked by</div>
            <div class="print-signature">Approved by</div>
        </div>

        <footer class="print-document-footer">
            <span>{{ $schoolContact ?: 'School book inventory and distribution office' }}</span>
            <span>Internal school record</span>
        </footer>
    </div>
</article>
@endsection
