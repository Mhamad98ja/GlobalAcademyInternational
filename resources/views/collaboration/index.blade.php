@extends('layouts.app', ['title' => 'Collaboration & class life'])

@section('content')
@if(request()->filled('section') || request()->route('section'))
    @include('collaboration.dars-page')
@else
<div class="page-header">
    <div>
        <h1 class="page-title">Collaboration & class life</h1>
        <p class="page-subtitle">Connect school notices, course work, calendar events, and student performance follow-up to the existing Global Academy International records.</p>
    </div>
    @if ($activeAcademicYear)
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><span class="font-semibold">Active academic year:</span> {{ $activeAcademicYear->name }}</div>
    @endif
</div>

<div class="grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
<section id="announcements" class="panel">
        <div class="panel-header"><div><h2 class="panel-title">School announcements</h2><p class="mt-0.5 text-xs text-slate-500">Published notices for the school, teachers, families, grades, and sections.</p></div></div>
        <div class="divide-y divide-slate-100">
            @forelse ($announcements as $announcement)
                <article class="p-5">
                    <div class="flex items-start justify-between gap-3"><div><h3 class="font-semibold text-slate-900">{{ $announcement->title }}</h3><p class="mt-1 text-xs text-slate-500">{{ str($announcement->audience)->headline() }}@if($announcement->grade) · {{ $announcement->grade->name }}@endif @if($announcement->section) · {{ $announcement->section->name }}@endif</p></div><span class="badge {{ $announcement->published_at ? 'badge-green' : 'badge-amber' }}">{{ $announcement->published_at ? 'Published' : 'Draft' }}</span></div>
                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $announcement->body }}</p>
                    <div class="mt-3 text-xs text-slate-500">{{ $announcement->published_at?->format('d M Y H:i') ?? 'Not yet published' }} · {{ $announcement->createdBy?->name ?? 'System' }}</div>
                </article>
            @empty
                <div class="empty">No announcements are available yet.</div>
            @endforelse
        </div>
    </section>

    <section id="calendar" class="panel">
        <div class="panel-header"><div><h2 class="panel-title">School calendar</h2><p class="mt-0.5 text-xs text-slate-500">Upcoming activities, deadlines, and class events.</p></div></div>
        <div class="divide-y divide-slate-100">
            @forelse ($events as $event)
                <article class="flex gap-3 p-4"><div class="grid h-11 w-11 shrink-0 place-items-center rounded-md bg-sky-50 text-center text-xs font-bold leading-4 text-sky-800"><span>{{ $event->starts_at->format('d') }}</span><span>{{ $event->starts_at->format('M') }}</span></div><div class="min-w-0"><h3 class="font-semibold text-slate-900">{{ $event->title }}</h3><p class="mt-1 text-xs text-slate-500">{{ $event->starts_at->format('D, d M Y H:i') }}@if($event->ends_at) – {{ $event->ends_at->format('H:i') }}@endif · {{ str($event->audience)->headline() }}</p>@if($event->description)<p class="mt-2 text-sm text-slate-600">{{ $event->description }}</p>@endif</div></article>
            @empty
                <div class="empty">No upcoming school events.</div>
            @endforelse
        </div>
    </section>
</div>

<section id="course-content" class="panel mt-6">
    <div class="panel-header"><div><h2 class="panel-title">Subject agenda & classroom work</h2><p class="mt-0.5 text-xs text-slate-500">Lessons, homework, resources, links, and due dates attached to the subject a teacher teaches in a class.</p></div></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Course work</th><th>Teacher & class</th><th>Type</th><th>Due</th><th>Status</th></tr></thead><tbody>
        @forelse ($contents as $content)
            <tr><td><div class="font-medium text-slate-900">{{ $content->title }}</div>@if($content->content)<div class="mt-1 max-w-xl whitespace-pre-line text-xs text-slate-500">{{ str($content->content)->limit(180) }}</div>@endif @if($content->resource_url)<a class="link mt-1 inline-block text-xs" href="{{ $content->resource_url }}" target="_blank" rel="noopener">Open learning resource</a>@endif</td><td>{{ $content->assignment?->teacher?->full_name }}<div class="text-xs text-slate-500">{{ $content->assignment?->subject?->name }} · {{ $content->assignment?->grade?->name }}{{ $content->assignment?->section ? ' '.$content->assignment->section->name : '' }}</div></td><td><span class="badge badge-blue">{{ str($content->content_type)->headline() }}</span></td><td>{{ $content->due_on?->format('d M Y') ?? '—' }}</td><td><span class="badge {{ $content->published_at ? 'badge-green' : 'badge-amber' }}">{{ $content->published_at ? 'Published' : 'Draft' }}</span></td></tr>
        @empty
            <tr><td colspan="5" class="empty">No subject agenda items have been published.</td></tr>
        @endforelse
    </tbody></table></div>
</section>

@if ($canViewPerformance)
    <section id="performance" class="panel mt-6">
        <div class="panel-header"><div><h2 class="panel-title">Student performance follow-up</h2><p class="mt-0.5 text-xs text-slate-500">Behaviour, participation, effort, homework, and engagement observations stay connected to each student record.</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Student</th><th>Subject / teacher</th><th>Category</th><th>Rating</th><th>Observation</th><th>Status</th></tr></thead><tbody>
            @forelse ($evaluations as $evaluation)
                <tr><td>{{ $evaluation->student?->full_name }}<div class="text-xs text-slate-500">{{ $evaluation->registration?->grade?->name }}{{ $evaluation->registration?->section ? ' '.$evaluation->registration->section->name : '' }}</div></td><td>{{ $evaluation->assignment?->subject?->name ?? 'General follow-up' }}<div class="text-xs text-slate-500">{{ $evaluation->teacher?->full_name ?? 'School staff' }}</div></td><td>{{ str($evaluation->category)->headline() }}</td><td>{{ $evaluation->rating ? $evaluation->rating.' / 5' : '—' }}</td><td><div class="max-w-md text-sm text-slate-600">{{ $evaluation->note }}</div><div class="mt-1 text-xs text-slate-500">{{ $evaluation->observed_on->format('d M Y') }}</div></td><td><span class="badge {{ $evaluation->status === 'published' ? 'badge-green' : 'badge-amber' }}">{{ str($evaluation->status)->headline() }}</span></td></tr>
            @empty
                <tr><td colspan="6" class="empty">No performance evaluations have been recorded.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
@endif

@if ($canManage || $canManagePerformance)
    <section class="mt-6 grid gap-6 xl:grid-cols-2">
        @if ($canManage)
            <details class="panel" open>
                <summary class="panel-header cursor-pointer"><div><h2 class="panel-title">Publish an announcement</h2><p class="mt-0.5 text-xs text-slate-500">Send a school or class notice to the selected audience.</p></div></summary>
                <form method="POST" action="{{ route('collaboration.announcements.store') }}" class="space-y-4 p-5" data-submit-lock>@csrf
                    <div><label class="form-label">Title *</label><input name="title" required value="{{ old('title') }}"></div>
                    <div><label class="form-label">Message *</label><textarea name="body" rows="4" required>{{ old('body') }}</textarea></div>
                    <div class="form-grid"><div><label class="form-label">Audience *</label><select name="audience" required><option value="all">Everyone</option><option value="teachers">Teachers</option><option value="students">Students</option><option value="guardians">Guardians</option><option value="grade">One grade</option><option value="section">One section</option></select></div><div><label class="form-label">Academic year</label><select name="academic_year_id"><option value="">All / not specific</option>@if($activeAcademicYear)<option value="{{ $activeAcademicYear->id }}" selected>{{ $activeAcademicYear->name }}</option>@endif</select></div><div><label class="form-label">Grade</label><select name="grade_id"><option value="">Not specific</option>@foreach($grades as $grade)<option value="{{ $grade->id }}">{{ $grade->name }}</option>@endforeach</select></div><div><label class="form-label">Section</label><select name="section_id"><option value="">Not specific</option>@foreach($grades as $grade) @foreach($grade->sections as $section)<option value="{{ $section->id }}">{{ $grade->name }} {{ $section->name }}</option>@endforeach @endforeach</select></div><div><label class="form-label">Expires on</label><input type="date" name="expires_on"></div></div>
                    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="publish_now" value="1" checked>Publish now</label><div class="flex justify-end"><button class="btn btn-primary">Save announcement</button></div>
                </form>
            </details>

            <details class="panel">
                <summary class="panel-header cursor-pointer"><div><h2 class="panel-title">Add subject agenda item</h2><p class="mt-0.5 text-xs text-slate-500">Publish classroom work against an active teacher subject assignment.</p></div></summary>
                <form method="POST" action="{{ route('collaboration.course-content.store') }}" enctype="multipart/form-data" class="space-y-4 p-5" data-submit-lock>@csrf
                    <div><label class="form-label">Teacher subject / class *</label><select name="teacher_assignment_id" required><option value="">Select subject and class</option>@foreach($assignments as $assignment)<option value="{{ $assignment->id }}">{{ $assignment->subject?->name }} · {{ $assignment->grade?->name }}{{ $assignment->section ? ' '.$assignment->section->name : '' }} · {{ $assignment->teacher?->full_name }}</option>@endforeach</select></div>
                    <div class="form-grid"><div><label class="form-label">Title *</label><input name="title" required></div><div><label class="form-label">Type *</label><select name="content_type" required>@foreach(['lesson','homework','document','link','video','other'] as $type)<option value="{{ $type }}">{{ str($type)->headline() }}</option>@endforeach</select></div><div><label class="form-label">Available from</label><input type="datetime-local" name="available_from"></div><div><label class="form-label">Due date</label><input type="date" name="due_on"></div></div>
                    <div><label class="form-label">Instructions or description</label><textarea name="content" rows="3"></textarea></div><div class="form-grid"><div><label class="form-label">Learning resource URL</label><input type="url" name="resource_url" placeholder="https://..."></div><div><label class="form-label">Upload resource (PDF or document)</label><input type="file" name="attachment" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.jpg,.jpeg,.png"></div></div>
                    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="publish_now" value="1" checked>Publish now</label><div class="flex justify-end"><button class="btn btn-primary">Save agenda item</button></div>
                </form>
            </details>

            <details class="panel">
                <summary class="panel-header cursor-pointer"><div><h2 class="panel-title">Create calendar event</h2><p class="mt-0.5 text-xs text-slate-500">Add an event, activity, deadline, or meeting to the school calendar.</p></div></summary>
                <form method="POST" action="{{ route('collaboration.events.store') }}" class="space-y-4 p-5" data-submit-lock>@csrf
                    <div><label class="form-label">Event title *</label><input name="title" required></div><div><label class="form-label">Description</label><textarea name="description" rows="3"></textarea></div><div class="form-grid"><div><label class="form-label">Starts *</label><input type="datetime-local" name="starts_at" required></div><div><label class="form-label">Ends</label><input type="datetime-local" name="ends_at"></div><div><label class="form-label">Meeting platform</label><select name="meeting_platform"><option value="">Not an online meeting</option><option value="zoom">Zoom</option><option value="google_meet">Google Meet</option><option value="teams">Microsoft Teams</option><option value="other">Other</option></select></div><div><label class="form-label">Meeting link</label><input type="url" name="meeting_url" placeholder="https://zoom.us/j/..." ></div><div><label class="form-label">Audience *</label><select name="audience" required><option value="all">Everyone</option><option value="teachers">Teachers</option><option value="students">Students</option><option value="guardians">Guardians</option><option value="grade">One grade</option><option value="section">One section</option></select></div><div><label class="form-label">Academic year</label><select name="academic_year_id"><option value="">All / not specific</option>@if($activeAcademicYear)<option value="{{ $activeAcademicYear->id }}" selected>{{ $activeAcademicYear->name }}</option>@endif</select></div><div><label class="form-label">Grade</label><select name="grade_id"><option value="">Not specific</option>@foreach($grades as $grade)<option value="{{ $grade->id }}">{{ $grade->name }}</option>@endforeach</select></div><div><label class="form-label">Section</label><select name="section_id"><option value="">Not specific</option>@foreach($grades as $grade) @foreach($grade->sections as $section)<option value="{{ $section->id }}">{{ $grade->name }} {{ $section->name }}</option>@endforeach @endforeach</select></div></div>
                    <div class="flex justify-end"><button class="btn btn-primary">Save calendar event</button></div>
                </form>
            </details>
        @endif

        @if ($canManagePerformance)
            <details class="panel" @if(! $canManage) open @endif>
                <summary class="panel-header cursor-pointer"><div><h2 class="panel-title">Record student note</h2><p class="mt-0.5 text-xs text-slate-500">Add a classroom observation such as participation, behaviour, or leaving class without permission.</p></div></summary>
                <form method="POST" action="{{ route('collaboration.performance.store') }}" class="space-y-4 p-5" data-submit-lock>@csrf
                    <div><label class="form-label">Student *</label><select name="student_registration_id" required><option value="">Select active student</option>@foreach($registrations as $registration)<option value="{{ $registration->id }}">{{ $registration->student?->full_name }} · {{ $registration->grade?->name }}{{ $registration->section ? ' '.$registration->section->name : '' }}</option>@endforeach</select></div>
                    <div class="form-grid"><div><label class="form-label">Teacher subject / class</label><select name="teacher_assignment_id"><option value="">General follow-up</option>@foreach($assignments as $assignment)<option value="{{ $assignment->id }}">{{ $assignment->subject?->name }} · {{ $assignment->grade?->name }}{{ $assignment->section ? ' '.$assignment->section->name : '' }}</option>@endforeach</select></div><div><label class="form-label">Category *</label><select name="category" required>@foreach(['participation','behavior','effort','homework','engagement','other'] as $category)<option value="{{ $category }}">{{ str($category)->headline() }}</option>@endforeach</select></div><div><label class="form-label">Rating</label><select name="rating"><option value="">No numeric rating</option>@foreach(range(1, 5) as $rating)<option value="{{ $rating }}">{{ $rating }} / 5</option>@endforeach</select></div><div><label class="form-label">Observed on *</label><input type="date" name="observed_on" value="{{ today()->toDateString() }}" required></div><div><label class="form-label">Status *</label><select name="status" required><option value="published">Published</option><option value="draft">Draft</option></select></div></div>
                    <div><label class="form-label">Student note *</label><textarea name="note" rows="4" required placeholder="Describe what happened and any support or action needed."></textarea></div><div class="flex justify-end"><button class="btn btn-primary">Save student note</button></div>
                </form>
            </details>
        @endif
    </section>
@endif
@endif
@endsection
