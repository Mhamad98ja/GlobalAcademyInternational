<!doctype html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Student Portal - Global Academy International</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="student-portal-shell bg-slate-50 text-slate-900">
<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4">
        <a href="{{ route('student-portal.index') }}" class="flex items-center gap-3" aria-label="Student portal home">
            <img src="{{ asset('images/global-academy-international-logo.png') }}" alt="" class="h-10 w-10 object-contain">
            <span><strong class="block text-sm text-slate-900">Global Academy International</strong><small class="text-xs text-slate-500">Student Portal</small></span>
        </a>
        <div class="flex items-center gap-3">
            <span class="hidden text-sm font-semibold text-slate-600 sm:block">{{ $student->full_name }}</span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-secondary">Sign out</button></form>
        </div>
    </div>
</header>

<main class="mx-auto max-w-7xl space-y-6 px-5 py-8">
    <section class="student-portal-hero overflow-hidden rounded-2xl bg-slate-900 px-6 py-7 text-white shadow-lg sm:px-8">
        <div class="flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-xs font-bold uppercase tracking-[.18em] text-cyan-300">My school day</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Welcome, {{ $student->first_name }}.</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300">Your messages, class agenda, events, assessments, and published results are organized here.</p>
            </div>
            <div class="grid grid-cols-3 gap-2 sm:gap-3">
                <a href="#messages" class="student-portal-stat"><strong>{{ $unreadMessages }}</strong><span>Unread messages</span></a>
                <a href="#agenda" class="student-portal-stat"><strong>{{ $contents->count() }}</strong><span>Agenda items</span></a>
                <a href="#events" class="student-portal-stat"><strong>{{ $events->count() }}</strong><span>Upcoming events</span></a>
            </div>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1.15fr_.85fr]">
        <article id="messages" class="student-portal-card overflow-hidden">
            <div class="student-portal-card-header">
                <div><p class="student-portal-kicker text-sky-700">Communication</p><h2 class="student-portal-card-title">Messages</h2><p class="student-portal-card-description">Private messages sent to your student account.</p></div>
                @if($unreadMessages)<span class="badge badge-blue">{{ $unreadMessages }} new</span>@endif
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($messages as $receipt)
                    <article class="p-5 {{ $receipt->read_at ? '' : 'bg-sky-50/70' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><p class="text-xs font-semibold text-slate-500">{{ $receipt->message?->sender?->name ?? 'School' }}</p><h3 class="mt-1 truncate text-sm font-bold text-slate-900">{{ $receipt->message?->subject }}</h3></div>
                            @if(!$receipt->read_at)<span class="badge badge-blue shrink-0">New</span>@endif
                        </div>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ str($receipt->message?->body)->limit(180) }}</p>
                        <time class="mt-3 block text-xs text-slate-400">{{ $receipt->message?->sent_at?->format('d M Y H:i') }}</time>
                    </article>
                @empty
                    <div class="empty">No messages have been sent to you.</div>
                @endforelse
            </div>
        </article>

        <article id="events" class="student-portal-card overflow-hidden">
            <div class="student-portal-card-header">
                <div><p class="student-portal-kicker text-violet-700">School calendar</p><h2 class="student-portal-card-title">Events</h2><p class="student-portal-card-description">Upcoming meetings, workshops, and online sessions.</p></div>
                <span class="student-portal-section-icon bg-violet-100 text-violet-700">◎</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($events as $event)
                    <article class="flex gap-4 p-5">
                        <div class="student-portal-date-tile"><strong>{{ $event->starts_at->format('d') }}</strong><span>{{ $event->starts_at->format('M') }}</span></div>
                        <div class="min-w-0 flex-1"><h3 class="font-semibold text-slate-900">{{ $event->title }}</h3><p class="mt-1 text-xs text-slate-500">{{ $event->starts_at->format('D, d M Y H:i') }}@if($event->ends_at) - {{ $event->ends_at->format('H:i') }}@endif</p>@if($event->description)<p class="mt-2 text-sm leading-5 text-slate-600">{{ $event->description }}</p>@endif @if($event->meeting_url)<a class="link mt-3 inline-block text-sm" href="{{ $event->meeting_url }}" target="_blank" rel="noopener">Join {{ str($event->meeting_platform ?: 'online meeting')->headline() }}</a>@endif</div>
                    </article>
                @empty
                    <div class="empty">No upcoming events are scheduled.</div>
                @endforelse
            </div>
        </article>
    </section>

    <section id="agenda" class="student-portal-card overflow-hidden">
        <div class="student-portal-card-header">
            <div><p class="student-portal-kicker text-emerald-700">Classwork</p><h2 class="student-portal-card-title">My agenda</h2><p class="student-portal-card-description">Published lessons, homework, resources, and deadlines for your current classes.</p></div>
            <span class="student-portal-section-icon bg-emerald-100 text-emerald-700">✓</span>
        </div>
        <div class="grid gap-4 p-5 md:grid-cols-2">
            @forelse($contents as $content)
                <article class="student-portal-agenda-item">
                    <div class="flex items-start justify-between gap-3"><span class="badge badge-green">{{ str($content->content_type)->headline() }}</span>@if($content->due_on)<span class="text-right text-xs font-semibold text-amber-700"><span class="block text-[10px] uppercase tracking-wide text-slate-400">Due</span>{{ $content->due_on->format('d M Y') }}</span>@endif</div>
                    <h3 class="mt-3 text-base font-bold text-slate-900">{{ $content->title }}</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ $content->assignment?->subject?->name }} - {{ $content->assignment?->teacher?->full_name }} - {{ $content->assignment?->grade?->name }}{{ $content->assignment?->section ? ' / '.$content->assignment->section->name : '' }}</p>
                    @if($content->content)<p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ str($content->content)->limit(260) }}</p>@endif
                    <div class="mt-4 flex flex-wrap gap-3">@if($content->resource_url)<a class="link text-sm" href="{{ $content->resource_url }}" target="_blank" rel="noopener">Open resource</a>@endif @if($content->attachment_path)<a class="link text-sm" href="{{ route('student-portal.course-content.download', $content) }}">Download {{ $content->attachment_name ?: 'file' }}</a>@endif</div>
                </article>
            @empty
                <div class="empty md:col-span-2">No published agenda items are available for your current classes.</div>
            @endforelse
        </div>
        <div class="border-t border-slate-100 bg-emerald-50/50 px-5 py-3 text-xs text-emerald-800">New published agenda items send a notification to this portal.</div>
    </section>

    <section id="announcements" class="student-portal-card overflow-hidden">
        <div class="student-portal-card-header"><div><p class="student-portal-kicker text-orange-700">School updates</p><h2 class="student-portal-card-title">Announcements</h2><p class="student-portal-card-description">Important notices published for students and your classes.</p></div></div>
        <div class="grid gap-4 p-5 md:grid-cols-2">
            @forelse($announcements as $announcement)
                <article class="rounded-xl border border-orange-100 bg-orange-50/40 p-4"><h3 class="font-semibold text-slate-900">{{ $announcement->title }}</h3><p class="mt-1 text-xs text-slate-500">{{ $announcement->published_at?->format('d M Y H:i') }} - {{ str($announcement->audience)->headline() }}</p><p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $announcement->body }}</p></article>
            @empty
                <div class="empty md:col-span-2">No school announcements are available.</div>
            @endforelse
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1.05fr_.95fr]">
        <article class="student-portal-card overflow-hidden">
            <div class="student-portal-card-header"><div><p class="student-portal-kicker text-indigo-700">Academic record</p><h2 class="student-portal-card-title">Registration history</h2><p class="student-portal-card-description">Your active registrations and their academic years.</p></div></div>
            <div class="divide-y divide-slate-100">
                @forelse($registrationHistory as $registration)
                    <div class="flex items-center justify-between gap-4 p-5">
                        <div><strong class="block text-sm text-slate-900">{{ $registration->grade?->name }}{{ $registration->section ? ' / Section '.$registration->section->name : '' }}</strong><span class="mt-1 block text-xs text-slate-500">Registration year: {{ $registration->academicYear?->name ?? 'Not assigned' }}</span></div>
                        @if($registration->academicYear?->status === 'open')<span class="badge badge-green">Current</span>@else<span class="badge">Recorded</span>@endif
                    </div>
                @empty
                    <div class="empty">No registration history is linked to this account.</div>
                @endforelse
            </div>
        </article>

        <article class="student-portal-card overflow-hidden">
            <div class="student-portal-card-header"><div><p class="student-portal-kicker text-amber-700">Stay informed</p><h2 class="student-portal-card-title">Notifications</h2><p class="student-portal-card-description">Agenda, assessment, and school updates.</p></div><a class="link text-xs" href="{{ route('notifications.index') }}">View all</a></div>
            <div class="divide-y divide-slate-100">
                @forelse($notifications->take(6) as $notification)
                    @php($notificationData = $notification->data)
                    <article class="p-5 {{ !$notification->read_at ? 'bg-amber-50/60' : '' }}"><div class="flex items-start justify-between gap-3"><div><h3 class="text-sm font-bold text-slate-900">{{ $notificationData['title'] ?? 'School update' }}</h3><p class="mt-1 text-sm leading-5 text-slate-600">{{ $notificationData['message'] ?? '' }}</p></div>@if(!$notification->read_at)<span class="badge badge-amber">New</span>@endif</div><div class="mt-3 flex items-center justify-between gap-3 text-xs text-slate-400"><time>{{ $notification->created_at->format('d M Y H:i') }}</time>@if(!$notification->read_at)<form method="POST" action="{{ route('notifications.read', $notification) }}">@csrf<input type="hidden" name="redirect_to" value="{{ route('student-portal.index') }}"><button class="link">Mark read</button></form>@endif</div></article>
                @empty
                    <div class="empty">No notifications yet.</div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <article class="student-portal-card overflow-hidden"><div class="student-portal-card-header"><div><p class="student-portal-kicker text-rose-700">Assessment calendar</p><h2 class="student-portal-card-title">Upcoming assessments</h2><p class="student-portal-card-description">Tests, quizzes, exams, and monthly assessments.</p></div></div><div class="divide-y divide-slate-100">@forelse($assessments as $assessment)<div class="flex items-center justify-between gap-3 p-5"><div><strong class="block text-sm text-slate-900">{{ $assessment->name }}</strong><span class="mt-1 block text-xs text-slate-500">{{ str($assessment->type)->headline() }} - {{ $assessment->subject?->name }} - {{ $assessment->grade?->name }}{{ $assessment->section ? ' / '.$assessment->section->name : '' }}</span></div><strong class="shrink-0 text-right text-xs text-slate-700">{{ $assessment->assessment_date?->format('d M Y') ?? 'Date pending' }}</strong></div>@empty<div class="empty">No upcoming assessments have been published.</div>@endforelse</div></article>
        <article class="student-portal-card overflow-hidden"><div class="student-portal-card-header"><div><p class="student-portal-kicker text-blue-700">Results</p><h2 class="student-portal-card-title">Published grades</h2><p class="student-portal-card-description">Only grades published by a teacher or authorized administrator are shown.</p></div></div><div class="divide-y divide-slate-100">@forelse($publishedGrades as $entry)<div class="flex items-center justify-between gap-3 p-5"><div><strong class="block text-sm text-slate-900">{{ $entry->assessment?->name }}</strong><span class="mt-1 block text-xs text-slate-500">{{ $entry->assessment?->subject?->name }} - {{ $entry->assignment?->teacher?->full_name }}</span></div><strong class="shrink-0 text-lg text-blue-700">{{ number_format((float) $entry->score, 2) }} / {{ number_format((float) $entry->assessment?->maximum_score, 2) }}</strong></div>@empty<div class="empty">No published grades are available yet.</div>@endforelse</div></article>
    </section>
</main>
</body>
</html>
