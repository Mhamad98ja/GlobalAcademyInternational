<section id="assessment-create" class="dars-assessment-create">
    <div class="dars-assessment-create-toolbar"><a href="{{ route('grades.index', ['view' => 'evaluations', 'teacher_assignment_id' => $assignment->id]) }}">← Back to list</a><button type="submit" form="assessment-create-form">Save</button></div>
    <form id="assessment-create-form" method="POST" action="{{ route('grades.assessments.store') }}" class="dars-assessment-form" data-submit-lock>
        @csrf
        <input type="hidden" name="teacher_assignment_id" value="{{ $assignment->id }}">
        <label>Class subject<select disabled><option>{{ $assignment->grade?->name }}{{ $assignment->section ? ' / '.$assignment->section->name : '' }} · {{ $assignment->subject?->name }}</option></select></label>
        <label>Column<select name="assessment_column"><option value="">Choose column</option><option>1 - Cntr1</option><option>2 - Cntr2</option><option>3 - Cntr3</option><option>Final</option></select></label>
        <label>Grade over<input type="number" name="maximum_score" min="0.01" step="0.01" value="10" required></label>
        <label>Weight<input type="number" name="weight" min="0.001" max="10" step="0.001" value="1" required></label>
        <label>Type<select name="type" required>@foreach(['test' => 'Test', 'quiz' => 'Quiz', 'exam' => 'Exam', 'monthly_test' => 'Monthly test', 'project' => 'Project', 'oral' => 'Oral assessment'] as $type => $label)<option value="{{ $type }}">{{ $label }}</option>@endforeach</select></label>
        <label>Evaluation done on<input type="date" name="assessment_date" value="{{ today()->toDateString() }}"></label>
        <label>Section<select disabled><option>{{ $assignment->section?->name ?: 'All sections' }}</option></select></label>
        <label class="dars-assessment-wide">Remarks<textarea name="notes" rows="2" placeholder="Add a note for this evaluation"></textarea></label>
        <label class="dars-assessment-check"><input type="checkbox" name="online_quiz" value="1"> Online quiz</label>
        <label class="dars-assessment-check"><input type="checkbox" name="per_question" value="1"> Per question</label>
        <div class="dars-assessment-threshold"><div></div><strong>Below<br>&lt;</strong><strong>Above<br>&gt;=</strong><span>Grade Min - Max</span><input name="below_threshold" type="number" step="0.01" placeholder="—"><input name="above_threshold" type="number" step="0.01" placeholder="—"></div>
        <input type="hidden" name="name" value="{{ old('name', '') }}">
    </form>
</section>
