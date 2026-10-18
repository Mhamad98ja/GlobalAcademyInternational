@extends('layouts.app', ['title' => 'Reports'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Reports</h1>
        <p class="page-subtitle">Choose a report type to open its dedicated report and filters.</p>
    </div>
</div>

<div class="space-y-6">
    @foreach ($reportGroups as $group)
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">{{ $group['title'] }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $group['description'] }}</p>
                </div>
                <span class="text-sm text-slate-500">{{ count($group['reports']) }} report types</span>
            </div>
            <div class="grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($group['reports'] as $item)
                    <a class="rounded-xl border border-slate-200 bg-white p-4 transition hover:border-sky-300 hover:bg-sky-50" href="{{ route($item['route'], $item['params'] ?? []) }}">
                        <span class="block font-semibold text-slate-900">{{ $item['title'] }}</span>
                        <span class="mt-1 block text-sm leading-5 text-slate-500">{{ $item['description'] }}</span>
                        <span class="mt-3 block text-sm font-medium text-sky-700">Open report &rarr;</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
@endsection
