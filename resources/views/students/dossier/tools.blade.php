@extends('layouts.app', ['title' => 'Student '.$section['label']])

@section('content')
<div class="page-header">
    <div><div class="text-sm text-slate-500"><a class="link" href="{{ route('workspaces.show', ['workspace' => 'students']) }}">Student dossier</a> / {{ $section['label'] }}</div><h1 class="page-title mt-1">{{ $section['label'] }}</h1><p class="page-subtitle">{{ $section['description'] }}</p></div>
</div>
<div class="workspace-task-grid">
    @forelse($items as $item)
        <a class="workspace-task" href="{{ route($item['route'], $item['params']).($item['anchor'] ? '#'.$item['anchor'] : '') }}">
            <span class="workspace-task-icon" aria-hidden="true"><x-nav-icon :name="$item['icon']" /></span>
            <span class="min-w-0"><span class="workspace-task-title">{{ $item['label'] }}</span><span class="workspace-task-description">{{ $item['description'] }}</span><span class="workspace-task-action">Open &rarr;</span></span>
        </a>
    @empty
        <div class="panel empty">Your role does not have access to tools in this section.</div>
    @endforelse
</div>
@endsection
