@props([
    'name',
    'selectedId' => null,
    'selectedLabel' => null,
    'valueKey' => 'student',
    'academicYearId' => null,
    'gradeId' => null,
    'sectionId' => null,
    'studentId' => null,
    'placeholder' => 'Type a student name or number',
    'required' => false,
])

<div class="relative" data-student-picker data-search-url="{{ route('students.search') }}" data-value-key="{{ $valueKey }}" data-academic-year="{{ $academicYearId ?? '' }}" data-grade="{{ $gradeId ?? '' }}" data-section="{{ $sectionId ?? '' }}" data-student-id="{{ $studentId ?? '' }}">
    <input type="search" value="{{ $selectedLabel }}" placeholder="{{ $placeholder }}" autocomplete="off" data-student-picker-input @required($required)>
    <input type="hidden" name="{{ $name }}" value="{{ $selectedId }}" data-student-picker-value @required($required && filled($selectedId))>
    <div class="student-picker-results" data-student-picker-results hidden aria-live="polite"></div>
    <p class="mt-1 text-xs text-slate-500" data-student-picker-help>Type at least two characters. Results follow the selected academic year, class, and section.</p>
</div>
