@extends('layouts.app', ['title' => 'Distribution'])

@section('content')
<div class="page-header">
    <div>
        <div class="text-sm text-slate-500"><a class="link" href="{{ route('distributions.index') }}">Distributions</a> / Detail</div>
        <h1 class="page-title mt-1">Distribution to {{ $distribution->student->full_name }}</h1>
        <p class="page-subtitle">{{ $distribution->distributed_on->format('d M Y') }} · {{ $distribution->registration->grade->name }}{{ $distribution->registration->section ? ' '.$distribution->registration->section->name : '' }}</p>
    </div>
    <div class="flex flex-wrap gap-2"><a class="btn btn-secondary" target="_blank" rel="noopener" href="{{ route('distributions.print', $distribution) }}">Print distribution list</a>@if ($canViewFinancials)<a class="btn btn-primary" href="{{ route('invoices.show', $distribution->invoice) }}">View invoice</a>@endif</div>
</div>

<section class="panel">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Book</th><th>Quantity</th>@if ($canViewFinancials)<th>Unit price</th><th>Total</th>@endif</tr></thead>
            <tbody>
                @foreach ($distribution->items as $item)
                    <tr><td>{{ $item->book->title }}</td><td>{{ $item->quantity }}</td>@if ($canViewFinancials)<td>{{ number_format($item->unit_price, 2) }}</td><td>{{ number_format($item->line_total, 2) }}</td>@endif</tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
