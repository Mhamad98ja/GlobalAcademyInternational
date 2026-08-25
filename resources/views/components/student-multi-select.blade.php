@props(['students'])

<div data-student-multi-select data-search-url="{{ route('responsible.students.search') }}">
    <input type="search" class="w-full" data-student-picker-search autocomplete="off" placeholder="Search by student number or name" aria-label="Search students to link" aria-describedby="student-picker-help">
    <p id="student-picker-help" class="mt-1 text-xs text-slate-500">Type at least two characters, then select a student. You can select more than one.</p>
    <div class="mt-2 rounded-md border border-slate-200 bg-white p-1 shadow-sm" data-student-picker-results hidden aria-live="polite"></div>
    <div class="mt-2 flex flex-wrap gap-2" data-student-picker-selected>
        @foreach ($students as $student)
            <span class="inline-flex items-center gap-2 rounded-full bg-sky-50 px-3 py-1 text-sm text-sky-900" data-student-picker-chip data-student-id="{{ $student->id }}">
                <input type="hidden" name="student_ids[]" value="{{ $student->id }}">
                <span>{{ $student->student_number }} - {{ $student->full_name }}</span>
                <button type="button" class="font-semibold text-sky-700 hover:text-sky-950" data-student-picker-remove aria-label="Remove {{ $student->full_name }}">&times;</button>
            </span>
        @endforeach
    </div>
    @error('student_ids')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
</div>
