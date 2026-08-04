<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $title ?? config('app.name', 'SBM') }}</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body>
<div class="app-shell" data-app-shell>
    <div class="sidebar-backdrop no-print" data-sidebar-backdrop></div>
    <aside id="primary-sidebar" class="sidebar no-print" aria-label="Main navigation">
        <div class="sidebar-brand">
            <div class="sidebar-brand-mark">S</div>
            <div class="sidebar-brand-copy"><div class="font-semibold text-white">School Book Manager</div><div class="text-[11px] text-slate-400">Inventory & distribution</div></div>
            <button type="button" class="sidebar-close" data-sidebar-close aria-label="Close navigation">&times;</button>
        </div>
        <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-4">
@php($nav=[['dashboard','Dashboard','dashboard.view'],['operations.index','Operations center','dashboard.view'],['students.index','Students','students.view'],['books.index','Books & packages','books.view'],['suppliers.index','Suppliers','suppliers.view'],['distributions.index','Distribution','distributions.create'],['payments.index','Payments','payments.view'],['inventory.ledger','Inventory ledger','inventory.view'],['deferred.index','Deferred approvals','deferred.view'],['academic.index','Academic structure','academic.manage'],['teachers.index','Teachers','people.manage'],['responsible.index','Responsible persons','people.manage'],['reports.index','Reports','reports.view'],['admin.users','Administration','admin.manage']])
@foreach($nav as [$route,$label,$permission])
    @if(auth()->user()->hasPermission($permission))
        <a href="{{ route($route) }}" class="sidebar-link {{ request()->routeIs(str_replace('.index','.*',$route)) || request()->routeIs($route) || ($route === 'books.index' && request()->routeIs('class-lists.*')) ? 'active' : '' }}" aria-label="{{ __($label) }}" title="{{ __($label) }}">
            <span class="sidebar-link-icon" aria-hidden="true">{{ strtoupper($label[0]) }}</span><span class="sidebar-link-label">{{ __($label) }}</span>
        </a>
    @endif
@endforeach
        </nav>
        <div class="shrink-0 border-t border-white/10 p-3"><form method="POST" action="{{ route('logout') }}">@csrf<button class="sidebar-link w-full text-left" aria-label="Sign out" title="Sign out"><span class="sidebar-link-icon" aria-hidden="true">&rarr;</span><span class="sidebar-signout-label">Sign out</span></button></form></div>
    </aside>
    <main class="main">
        <header class="topbar no-print">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" class="nav-toggle" data-sidebar-toggle aria-controls="primary-sidebar" aria-expanded="true"><span class="sr-only">Toggle navigation</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg></button>
                <div class="min-w-0 text-sm text-slate-500"><span class="hidden sm:inline">{{ config('app.name', 'School Book Manager') }}</span><span class="sm:hidden">SBM</span></div>
            </div>
            <div class="flex shrink-0 items-center gap-3"><a href="{{ route('reports.index') }}" class="hidden text-sm text-slate-500 hover:text-teal-700 sm:block">Reports</a><div class="h-8 w-px bg-slate-200"></div><div class="max-w-40 text-right"><div class="truncate text-sm font-medium text-slate-800">{{ auth()->user()->name }}</div><div class="truncate text-xs text-slate-500">{{ auth()->user()->roles->first()?->label ?? 'User' }}</div></div><div class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-teal-100 font-semibold text-teal-800">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</div></div>
        </header>
        <div class="page">@include('components.flash')@yield('content')</div>
    </main>
</div>
<script>document.querySelectorAll('[data-modal]').forEach(b=>b.addEventListener('click',()=>document.getElementById(b.dataset.modal).classList.add('is-open')));document.querySelectorAll('[data-close-modal]').forEach(b=>b.addEventListener('click',()=>b.closest('.modal').classList.remove('is-open')));document.querySelectorAll('form[data-submit-lock]').forEach(f=>f.addEventListener('submit',()=>{const b=f.querySelector('[type=submit]');if(b){b.disabled=true;b.dataset.original=b.textContent;b.textContent='Saving…'}}));</script>
</body></html>
