<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $period === 'annual' ? 'Annual' : 'Partial' }} Report Cards · {{ $school['school_name'] ?? 'Global Academy International' }}</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="report-card-print-document">
    <div class="report-card-print-toolbar no-print"><a class="btn btn-secondary" href="{{ route('admin.report-cards', ['period' => $period, 'academic_year_id' => $year?->id, 'academic_term_id' => $termId, 'grade_id' => $filters['grade_id'] ?? null, 'section_id' => $filters['section_id'] ?? null, 'student_id' => $filters['student_id'] ?? null, 'generate' => 1]) }}">Back to generator</a><button class="btn btn-primary" type="button" onclick="window.print()">Print</button></div>
    @if($cards->isNotEmpty())
        @include('report-card.cards')
    @else
        <div class="report-card-print-empty">No published or locked grades match the selected period.</div>
    @endif
</body>
</html>
