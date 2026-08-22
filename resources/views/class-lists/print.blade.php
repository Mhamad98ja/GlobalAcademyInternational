@extends('layouts.app', ['title' => 'Print grade book lists'])

@section('content')
@php
    $schoolName = $school['school_name'] ?? 'Global Academy International';
    $schoolContact = collect([$school['school_address'] ?? null, $school['school_phone'] ?? null])->filter()->implode(' - ');
@endphp

@include('components.print-document-styles')

<div class="page-header no-print">
    <div>
        <h1 class="page-title">Print grade book lists</h1>
        <p class="page-subtitle">Packages are organized by academic year and grade for printing.</p>
    </div>
    <div class="flex gap-2">
        <a class="btn btn-secondary" href="{{ route('books.index', ['tab' => 'packages']) }}">Back to packages</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Print lists</button>
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
        <div class="print-document-kind">School operations<br>Grade book lists</div>
    </header>

    <div class="print-document-body">
        <div class="print-title-row">
            <div>
                <h1 class="print-title">Book packages by grade</h1>
                <div class="print-title-note">Prepared {{ now()->format('d M Y, H:i') }} by {{ auth()->user()->name }}</div>
            </div>
            <div class="print-reference">
                <div class="print-reference-row"><span>Packages</span><strong>{{ number_format($lists->count()) }}</strong></div>
                <div class="print-reference-row"><span>Printed on</span><strong>{{ now()->format('d M Y') }}</strong></div>
            </div>
        </div>

        @forelse ($lists->groupBy('academic_year_id') as $yearLists)
            @php($year = $yearLists->first()->academicYear)
            <section class="mb-8">
                <h2 class="mb-4 text-xl font-bold text-slate-900">{{ $year?->name ?? 'Academic year not set' }}</h2>
                @foreach ($yearLists->groupBy('grade_id') as $gradeLists)
                    @php($grade = $gradeLists->first()->grade)
                    <section class="mb-6 break-inside-avoid">
                        <h3 class="mb-3 text-lg font-semibold text-slate-800">{{ $grade?->name ?? 'Grade not set' }} book list</h3>
                        @foreach ($gradeLists as $list)
                            <div class="mb-4">
                                <div class="mb-2 flex items-baseline justify-between gap-3">
                                    <div class="font-semibold text-slate-800">{{ $list->name }}</div>
                                    <div class="text-sm text-slate-600">Section: {{ $list->section?->name ?? 'All sections' }} - {{ $list->is_active ? 'Active' : 'Inactive' }}</div>
                                </div>
                                <table class="data-table w-full">
                                    <thead><tr><th>Book</th><th>ISBN</th><th>Barcode</th><th>Quantity</th><th>Required</th></tr></thead>
                                    <tbody>
                                        @foreach ($list->items as $item)
                                            <tr><td>{{ $item->book?->title }}</td><td>{{ $item->book?->isbn ?: '-' }}</td><td>{{ $item->book?->barcode ?: '-' }}</td><td>{{ $item->quantity }}</td><td>{{ $item->is_mandatory ? 'Yes' : 'Optional' }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    </section>
                @endforeach
            </section>
        @empty
            <div class="empty">No class book packages have been created yet.</div>
        @endforelse

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
