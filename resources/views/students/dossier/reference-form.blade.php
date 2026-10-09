@extends('layouts.app', ['title' => ($mode === 'edit' ? 'Edit ' : 'Add ').$reference['title']])

@section('content')
<section class="dars-reference-page">
    <div class="dars-breadcrumb"><a href="{{ route('students.tools', ['section' => 'references']) }}">References</a><span>&gt;</span><a href="{{ route('students.references.show', ['reference' => $referenceKey]) }}">{{ $reference['title'] }}</a><span>&gt;</span><strong>{{ $mode === 'edit' ? 'Edit' : 'Add New' }}</strong></div>

    <div class="panel max-w-3xl p-6">
        <div class="mb-6">
            <h1 class="page-title">{{ $categoryOnly ? 'Add New Code Category' : ($mode === 'edit' ? 'Edit '.$reference['title'] : 'Add New '.$reference['title']) }}</h1>
            <p class="page-subtitle">This form stays inside Student Profile References and saves back to the selected reference list.</p>
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
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="form-label">Category<select name="category"><option value="">Select category</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(old('category', $entry['category'] ?? '') === $category)>{{ str($category)->headline() }}</option>@endforeach</select></label>
                            <label class="form-label">New category<input name="new_category" value="{{ old('new_category') }}" placeholder="Optional new category"></label>
                            <label class="form-label">Code<input name="code" value="{{ old('code', $entry['code'] ?? '') }}" required></label>
                            <label class="form-label">Value<input name="value" value="{{ old('value', $entry['value'] ?? '') }}" required></label>
                            <label class="form-label">Value EN<input name="value_en" value="{{ old('value_en', $entry['value_en'] ?? '') }}"></label>
                            <label class="form-label">Value AR<input name="value_ar" value="{{ old('value_ar', $entry['value_ar'] ?? '') }}"></label>
                        </div>
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

                <div class="flex justify-end gap-2 border-t border-slate-200 pt-5"><a class="btn btn-secondary" href="{{ route('students.references.show', ['reference' => $referenceKey]) }}">Cancel</a><button class="btn btn-primary">{{ $mode === 'edit' ? 'Save changes' : 'Add new' }}</button></div>
            </form>
        @endif
    </div>
</section>
@endsection
