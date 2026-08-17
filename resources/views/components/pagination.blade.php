@props(['paginator'])

<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <p class="text-xs text-slate-500">
        @if ($paginator->total() > 0)
            Showing {{ number_format($paginator->firstItem()) }}-{{ number_format($paginator->lastItem()) }} of {{ number_format($paginator->total()) }}
        @else
            No records to show
        @endif
    </p>

    @if ($paginator->hasPages())
        @php($startPage = max(1, $paginator->currentPage() - 2))
        @php($endPage = min($paginator->lastPage(), $paginator->currentPage() + 2))
        <nav class="flex flex-wrap items-center gap-1" aria-label="Pagination">
            @if ($paginator->onFirstPage())
                <span class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-400">Previous</span>
            @else
                <a class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif

            @if ($startPage > 1)
                <a class="rounded-md px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100" href="{{ $paginator->url(1) }}">1</a>
                @if ($startPage > 2)<span class="px-1 text-slate-400">...</span>@endif
            @endif

            @foreach (range($startPage, $endPage) as $page)
                @if ($page === $paginator->currentPage())
                    <span class="rounded-md bg-teal-700 px-3 py-1.5 text-xs font-semibold text-white" aria-current="page">{{ $page }}</span>
                @else
                    <a class="rounded-md px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                @endif
            @endforeach

            @if ($endPage < $paginator->lastPage())
                @if ($endPage < $paginator->lastPage() - 1)<span class="px-1 text-slate-400">...</span>@endif
                <a class="rounded-md px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100" href="{{ $paginator->url($paginator->lastPage()) }}">{{ $paginator->lastPage() }}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-400">Next</span>
            @endif
        </nav>
    @endif
</div>
