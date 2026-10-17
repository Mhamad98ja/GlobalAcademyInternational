@extends('layouts.app', ['title' => ($mode === 'edit' ? 'Edit ' : 'Add ').$reference['title']])

@section('content')
<section class="dars-reference-page">

    <div class="panel max-w-3xl p-6">
        <div class="mb-6">
            <h1 class="page-title">{{ $categoryOnly ? 'Add New Code Category' : ($mode === 'edit' ? 'Edit ' : 'Add New ').($structureTypes[$selectedCategory] ?? $reference['title']) }}</h1>
        <p class="page-subtitle">{{ isset($structureTypes[$selectedCategory]) ? 'Update the school structure here. Other academic pages show these values read-only.' : 'This reference is managed by administration and is used by student, family, and academic records.' }}</p>
        </div>

        @if($categoryOnly)
            <form method="POST" action="{{ route('students.references.categories.store') }}" class="space-y-4" data-submit-lock>
                @csrf
                <label class="form-label">Category name<input name="category" value="{{ old('category') }}" required placeholder="e.g. Languages"></label>
                <div class="flex justify-end gap-2"><a class="btn btn-secondary" href="{{ route('students.references.show', ['reference' => 'codes']) }}">Cancel</a><button class="btn btn-primary">Add category</button></div>
            </form>
        @else
            <form method="POST" action="{{ $mode === 'edit' ? route('students.references.update', ['reference' => $referenceKey, 'entry' => $entryKey]) : route('students.references.store', ['reference' => $referenceKey]) }}" class="space-y-4" data-submit-lock>
                @csrf
                @if($mode === 'edit') @method('PUT') @endif

                @switch($referenceKey)
                    @case('codes')
                        @if(isset($structureTypes[$selectedCategory]))
                            <input type="hidden" name="category" value="{{ $selectedCategory }}">
                            @if($selectedCategory === 'academic-year')
                                <div class="grid gap-4 md:grid-cols-2">
                                    <label class="form-label">Academic year name<input name="name" value="{{ old('name', $entry['name'] ?? '') }}" required placeholder="e.g. 2028-2029"></label>
                                    <label class="form-label">Status<select name="status" required>@foreach(['draft' => 'Draft', 'open' => 'Open', 'closed' => 'Closed'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $entry['status'] ?? 'draft') === $value)>{{ $label }}</option>@endforeach</select></label>
                                    <label class="form-label">Start date<input type="date" name="starts_on" value="{{ old('starts_on', $entry['starts_on'] ?? '') }}" required></label>
                                    <label class="form-label">End date<input type="date" name="ends_on" value="{{ old('ends_on', $entry['ends_on'] ?? '') }}" required></label>
                                </div>
                            @elseif($selectedCategory === 'term-semester')
                                <div class="grid gap-4 md:grid-cols-2">
                                    <label class="form-label">Academic year<select name="academic_year_id" required><option value="">Select year</option>@foreach($years as $year)<option value="{{ $year->id }}" @selected((string) old('academic_year_id', $entry['academic_year_id'] ?? '') === (string) $year->id)>{{ $year->name }}</option>@endforeach</select></label>
                                    <label class="form-label">Name<input name="name" value="{{ old('name', $entry['name'] ?? '') }}" required placeholder="e.g. Term 1"></label>
                                    <label class="form-label">Type<select name="type" required>@foreach(['term' => 'Term', 'semester' => 'Semester'] as $value => $label)<option value="{{ $value }}" @selected(old('type', $entry['type'] ?? 'term') === $value)>{{ $label }}</option>@endforeach</select></label>
                                    <label class="form-label">Status<select name="status" required>@foreach(['draft' => 'Draft', 'planned' => 'Planned', 'open' => 'Open', 'closed' => 'Closed'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $entry['status'] ?? 'draft') === $value)>{{ $label }}</option>@endforeach</select></label>
                                    <label class="form-label">Start date<input type="date" name="starts_on" value="{{ old('starts_on', $entry['starts_on'] ?? '') }}" required></label>
                                    <label class="form-label">End date<input type="date" name="ends_on" value="{{ old('ends_on', $entry['ends_on'] ?? '') }}" required></label>
                                    <label class="form-label">Order<input type="number" min="0" name="sort_order" value="{{ old('sort_order', $entry['sort_order'] ?? 0) }}" required></label>
                                </div>
                            @elseif($selectedCategory === 'level')
                                <div class="grid gap-4 md:grid-cols-2"><label class="form-label">Level name<input name="name" value="{{ old('name', $entry['name'] ?? '') }}" required></label><label class="form-label">Order<input type="number" min="0" name="sort_order" value="{{ old('sort_order', $entry['sort_order'] ?? 0) }}" required></label></div>
                            @elseif($selectedCategory === 'grade')
                                <div class="grid gap-4 md:grid-cols-2"><label class="form-label">Level<select name="school_level_id" required><option value="">Select level</option>@foreach($levels as $level)<option value="{{ $level->id }}" @selected((string) old('school_level_id', $entry['school_level_id'] ?? '') === (string) $level->id)>{{ $level->name }}</option>@endforeach</select></label><label class="form-label">Grade name<input name="name" value="{{ old('name', $entry['name'] ?? '') }}" required></label><label class="form-label">Order<input type="number" min="0" name="sort_order" value="{{ old('sort_order', $entry['sort_order'] ?? 0) }}" required></label></div>
                            @elseif($selectedCategory === 'section')
                                <div class="grid gap-4 md:grid-cols-2"><label class="form-label">Grade<select name="grade_id" required><option value="">Select grade</option>@foreach($grades as $grade)<option value="{{ $grade->id }}" @selected((string) old('grade_id', $entry['grade_id'] ?? '') === (string) $grade->id)>{{ $grade->level?->name }} · {{ $grade->name }}</option>@endforeach</select></label><label class="form-label">Section name<input name="name" value="{{ old('name', $entry['name'] ?? '') }}" required placeholder="e.g. A"></label><label class="form-label">Room (optional)<input name="room" value="{{ old('room', $entry['room'] ?? '') }}"></label></div>
                            @endif
                        @else
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="form-label">Category<select name="category"><option value="">Select category</option>@foreach($categories as $category)@if(!isset($structureTypes[$category]))<option value="{{ $category }}" @selected(old('category', $entry['category'] ?? $selectedCategory) === $category)>{{ str($category)->headline() }}</option>@endif @endforeach</select></label>
                            <label class="form-label">New category<input name="new_category" value="{{ old('new_category') }}" placeholder="Optional new category"></label>
                            <label class="form-label">Code<input name="code" value="{{ old('code', $entry['code'] ?? '') }}" required></label>
                            <label class="form-label">Value<input name="value" value="{{ old('value', $entry['value'] ?? '') }}" required></label>
                            <label class="form-label">Value EN<input name="value_en" value="{{ old('value_en', $entry['value_en'] ?? '') }}"></label>
                            <label class="form-label">Value AR<input name="value_ar" value="{{ old('value_ar', $entry['value_ar'] ?? '') }}"></label>
                        </div>
                        @endif
                        @break
                    @case('division')
                        <div class="grid gap-4 md:grid-cols-2"><label class="form-label">Division<input name="name" value="{{ old('name', $entry['name'] ?? '') }}" required></label><label class="form-label">Rank<input type="number" name="sort_order" value="{{ old('sort_order', $entry['sort_order'] ?? 0) }}" min="0"></label></div>
                        @break
                    @case('class-definition')
                        <div class="grid gap-4 md:grid-cols-2"><label class="form-label">Division<select name="school_level_id"><option value="">No division</option>@foreach($levels as $level)<option value="{{ $level->id }}" @selected((string) old('school_level_id', $entry['school_level_id'] ?? '') === (string) $level->id)>{{ $level->name }}</option>@endforeach</select></label><label class="form-label">Class name<input name="name" value="{{ old('name', $entry['name'] ?? '') }}" required></label><label class="form-label">Rank<input type="number" name="sort_order" value="{{ old('sort_order', $entry['sort_order'] ?? 0) }}" min="0"></label></div>
                        @break
                    @case('classes')
                        <div class="grid gap-4 md:grid-cols-2"><label class="form-label">Class definition<select name="grade_id" required><option value="">Select class</option>@foreach($grades as $grade)<option value="{{ $grade->id }}" @selected((string) old('grade_id', $entry['grade_id'] ?? '') === (string) $grade->id)>{{ $grade->name }}</option>@endforeach</select></label><label class="form-label">Section<input name="name" value="{{ old('name', $entry['name'] ?? '') }}" required></label><label class="form-label">Location<input name="room" value="{{ old('room', $entry['room'] ?? '') }}"></label></div>
                        @break
                    @case('other-schools')
                        <div class="grid gap-4 md:grid-cols-2"><label class="form-label">School name<input name="name" value="{{ old('name', $entry['name'] ?? '') }}" required></label><label class="form-label">Number<input name="number" value="{{ old('number', $entry['number'] ?? '') }}"></label></div>
                        @break
                    @case('district')
                        <div class="grid gap-4 md:grid-cols-2"><label class="form-label">District<input name="district" value="{{ old('district', $entry['district'] ?? '') }}" required></label><label class="form-label">Governorate<input name="governorate" value="{{ old('governorate', $entry['governorate'] ?? '') }}"></label></div>
                        @break
                    @case('towns')
                        <div class="grid gap-4 md:grid-cols-2"><label class="form-label">District<input name="district" value="{{ old('district', $entry['district'] ?? '') }}" required></label><label class="form-label">Town<input name="name" value="{{ old('name', $entry['name'] ?? '') }}" required></label><label class="form-label">Town Arabic<input name="name_ar" value="{{ old('name_ar', $entry['name_ar'] ?? '') }}"></label></div>
                        @break
                @endswitch

                <div class="flex justify-end gap-2 border-t border-slate-200 pt-5"><a class="btn btn-secondary" href="{{ route('students.references.show', ['reference' => $referenceKey] + ($referenceKey === 'codes' ? ['type' => $selectedCategory] : [])) }}">Cancel</a><button class="btn btn-primary">{{ $mode === 'edit' ? 'Save changes' : 'Add new' }}</button></div>
            </form>
        @endif
    </div>
</section>
@endsection
