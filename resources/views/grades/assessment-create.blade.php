@php($editing = isset($editingAssessment) && $editingAssessment)
<section id="assessment-create" class="dars-assessment-create">
    <div class="dars-assessment-create-toolbar">
        <a href="{{ route('grades.index', ['view' => 'evaluations', 'teacher_assignment_id' => $assignment->id]) }}">← Back to list</a>
        <button type="submit" name="workflow_action" value="draft" form="assessment-create-form">Save draft</button>
        <button class="dars-evaluation-primary" type="submit" name="workflow_action" value="submit" form="assessment-create-form">Submit for review</button>
    </div>
    <form id="assessment-create-form" method="POST" action="{{ $editing ? route('grades.assessments.update', $editingAssessment) : route('grades.assessments.store') }}" class="dars-assessment-form" data-submit-lock>
        @csrf
        @if($editing) @method('PUT') @endif
        <input type="hidden" name="teacher_assignment_id" value="{{ $assignment->id }}">
        <label>Class subject<select disabled><option>{{ $assignment->grade?->name }}{{ $assignment->section ? ' / '.$assignment->section->name : '' }} · {{ $assignment->subject?->name }}</option></select></label>
        <label>Column<select name="assessment_column"><option value="">Choose column</option>@foreach(['1 - Cntr1', '2 - Cntr2', '3 - Cntr3', 'Final'] as $column)<option value="{{ $column }}" @selected(old('assessment_column', $editingAssessment?->assessment_column) === $column)>{{ $column }}</option>@endforeach</select></label>
        <label>Grade over<input type="number" name="maximum_score" min="0.01" step="0.01" value="{{ old('maximum_score', $editingAssessment?->maximum_score ?? 10) }}" required></label>
        <label>Weight<input type="number" name="weight" min="0.001" max="10" step="0.001" value="{{ old('weight', $editingAssessment?->weight ?? 1) }}" required></label>
        <label>Type<select name="type" required>@foreach(['test' => 'Test', 'quiz' => 'Quiz', 'exam' => 'Exam', 'monthly_test' => 'Monthly test', 'project' => 'Project', 'oral' => 'Oral assessment'] as $type => $label)<option value="{{ $type }}" @selected(old('type', $editingAssessment?->type ?? 'test') === $type)>{{ $label }}</option>@endforeach</select></label>
        <label>Evaluation done on<input type="date" name="assessment_date" value="{{ old('assessment_date', $editingAssessment?->assessment_date?->toDateString() ?? today()->toDateString()) }}"></label>
        <label>Section<select disabled><option>{{ $assignment->section?->name ?: 'All sections' }}</option></select></label>
        <label class="dars-assessment-wide">Remarks<textarea name="notes" rows="2" placeholder="Add a note for this evaluation">{{ old('notes', $editingAssessment?->notes) }}</textarea></label>
        <label class="dars-assessment-check"><input type="checkbox" name="online_quiz" value="1" @checked(old('online_quiz', $editingAssessment?->online_quiz))> Online quiz</label>
        <label class="dars-assessment-check"><input type="checkbox" name="per_question" value="1" @checked(old('per_question', $editingAssessment?->per_question))> Per question</label>
        <div class="dars-assessment-threshold"><div></div><strong>Below<br>&lt;</strong><strong>Above<br>&gt;=</strong><span>Grade Min - Max</span><input name="below_threshold" type="number" step="0.01" placeholder="—"><input name="above_threshold" type="number" step="0.01" placeholder="—"></div>
        <input type="hidden" name="name" value="{{ old('name', $editingAssessment?->name ?? '') }}">
    </form>
</section>
