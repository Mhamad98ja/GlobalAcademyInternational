@extends('layouts.app', ['title' => isset($editingAssessment) && $editingAssessment ? 'Edit Evaluation' : 'New Evaluation'])
@section('content')
<section class="dars-evaluation-page">
    <div class="dars-evaluation-actions">
        <div><strong class="dars-evaluation-page-title">{{ $editingAssessment ? 'Edit evaluation' : 'New evaluation' }}</strong><span class="dars-evaluation-page-subtitle">Choose the class subject, define the evaluation, then enter each student grade.</span></div>
        <a class="dars-evaluation-secondary" href="{{ route('grades.index', ['view' => 'evaluations']) }}">Back to evaluations</a>
    </div>
    <section class="dars-assessment-create dars-assessment-create-page">
        <form method="GET" action="{{ route('grades.assessments.create') }}" class="dars-assessment-assignment-picker" data-evaluation-assignment-picker>
            <label>Class / section
                <select id="evaluation-grade-picker" aria-label="Choose class">
                    @foreach($assignments->unique(fn ($item) => $item->grade_id.'-'.$item->section_id) as $item)
                        <option value="{{ $item->grade_id.'-'.$item->section_id }}" @selected($assignment && $assignment->grade_id === $item->grade_id && $assignment->section_id === $item->section_id)>{{ $item->grade?->name }}{{ $item->section ? ' / '.$item->section->name : '' }}</option>
                    @endforeach
                </select>
            </label>
            <label>Subject
                <select name="teacher_assignment_id" id="evaluation-subject-picker" aria-label="Choose subject" onchange="this.form.submit()">
                    @foreach($assignments as $item)
                        <option value="{{ $item->id }}" data-class-key="{{ $item->grade_id.'-'.$item->section_id }}" @selected($assignment?->id === $item->id)>{{ $item->subject?->name }}</option>
                    @endforeach
                </select>
            </label>
        </form>
        @if($assignment)
            <form method="POST" action="{{ $editingAssessment ? route('grades.assessments.update', $editingAssessment) : route('grades.assessments.store') }}" class="dars-assessment-form" data-submit-lock>
                @csrf
                @if($editingAssessment) @method('PUT') @endif
                <input type="hidden" name="teacher_assignment_id" value="{{ $assignment->id }}">
                <label>Class<select disabled><option>{{ $assignment->grade?->name }}{{ $assignment->section ? ' / '.$assignment->section->name : '' }}</option></select></label>
                <label>Subject<select disabled><option>{{ $assignment->subject?->name }}</option></select></label>
                <label>Academic term<select name="academic_term_id" required>@forelse($terms as $term)<option value="{{ $term->id }}" @selected((string) old('academic_term_id', $editingAssessment?->academic_term_id) === (string) $term->id)>{{ $term->name }}</option>@empty<option value="">No active term configured</option>@endforelse</select></label>
                <label>Evaluation name<input name="name" value="{{ old('name', $editingAssessment?->name) }}" required placeholder="Example: October monthly test"></label>
                <label>Type<select name="type" required>@foreach(['test' => 'Test', 'quiz' => 'Quiz', 'exam' => 'Exam', 'monthly_test' => 'Monthly test', 'project' => 'Project', 'oral' => 'Oral assessment'] as $type => $label)<option value="{{ $type }}" @selected(old('type', $editingAssessment?->type ?? 'test') === $type)>{{ $label }}</option>@endforeach</select></label>
                <label>Grade over / maximum<input type="number" name="maximum_score" min="0.1" step="0.1" value="{{ old('maximum_score', $editingAssessment?->maximum_score ?? 20) }}" required></label>
                <label>Average / weight<input type="number" name="weight" min="0.001" max="10" step="0.001" value="{{ old('weight', $editingAssessment?->weight ?? 1) }}" required></label>
                <label>Evaluation date<input type="date" name="assessment_date" value="{{ old('assessment_date', $editingAssessment?->assessment_date?->toDateString() ?? today()->toDateString()) }}"></label>
                <label class="dars-assessment-wide">Description<textarea name="notes" rows="4" required placeholder="Describe the evaluation and what students were assessed on.">{{ old('notes', $editingAssessment?->notes) }}</textarea></label>
                <div class="dars-assessment-create-actions"><button class="dars-evaluation-primary" type="submit">{{ $editingAssessment ? 'Save evaluation details' : 'Add evaluation and enter grades' }}</button></div>
            </form>
        @else
            <div class="dars-evaluation-empty"><strong>No assigned class subjects</strong><span>Ask administration to assign a subject before creating an evaluation.</span></div>
        @endif
    </section>
</section>
<script>
    (() => {
        const classPicker = document.getElementById('evaluation-grade-picker');
        const subjectPicker = document.getElementById('evaluation-subject-picker');
        if (!classPicker || !subjectPicker) return;
        const filterSubjects = () => {
            let firstVisible = null;
            [...subjectPicker.options].forEach((option) => {
                const visible = option.dataset.classKey === classPicker.value;
                option.hidden = !visible;
                if (visible && !firstVisible) firstVisible = option;
            });
            if (firstVisible && (!subjectPicker.value || subjectPicker.options[subjectPicker.selectedIndex]?.hidden)) subjectPicker.value = firstVisible.value;
        };
        classPicker.addEventListener('change', filterSubjects);
        filterSubjects();
    })();
</script>
@endsection
