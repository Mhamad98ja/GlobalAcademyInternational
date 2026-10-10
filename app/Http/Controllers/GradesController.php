<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\GradeChangeAudit;
use App\Models\GradeEntry;
use App\Models\StudentRegistration;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GradesController extends Controller
{
    public function index(Request $request): View
    {
        $assignments = $this->assignmentsFor($request->user());
        $assignmentId = $request->integer('teacher_assignment_id') ?: $assignments->first()?->id;
        $assignment = $assignmentId ? $assignments->firstWhere('id', $assignmentId) : null;
        $assessmentId = $request->integer('assessment_id');
        $assessments = $assignment ? Assessment::query()
            ->where(function ($query) use ($assignment): void {
                $query->where('teacher_assignment_id', $assignment->id)
                    ->orWhere(function ($legacy) use ($assignment): void {
                        $legacy->whereNull('teacher_assignment_id')
                            ->where('academic_year_id', $assignment->academic_year_id)
                            ->where('subject_id', $assignment->subject_id)
                            ->where('grade_id', $assignment->grade_id)
                            ->where(fn ($section) => $section->whereNull('section_id')->when($assignment->section_id, fn ($sectionQuery) => $sectionQuery->orWhere('section_id', $assignment->section_id)));
                    });
            })
            ->with('assignment.teacher', 'assignment.subject', 'assignment.grade', 'assignment.section')
            ->orderByDesc('assessment_date')->get() : collect();
        $assessment = $assessmentId ? $assessments->firstWhere('id', $assessmentId) : $assessments->first();
        $editingAssessment = null;
        if ($request->boolean('edit') && $assessmentId) {
            $editingAssessment = $assessments->firstWhere('id', $assessmentId);
            if ($editingAssessment && ! in_array($editingAssessment->review_status ?? 'draft', ['draft', 'rejected'], true) && ! $request->user()->hasPermission('academic_year.manage')) {
                $editingAssessment = null;
            }
        }
        $registrations = $assignment && $assessment ? StudentRegistration::query()
            ->with('student')
            ->where('academic_year_id', $assignment->academic_year_id)
            ->where('grade_id', $assignment->grade_id)
            ->when($assignment->section_id, fn ($query) => $query->where('section_id', $assignment->section_id))
            ->where('status', 'active')
            ->orderBy('student_id')->get() : collect();
        $grades = $assessment ? GradeEntry::query()->where('assessment_id', $assessment->id)->whereIn('student_registration_id', $registrations->modelKeys())->get()->keyBy('student_registration_id') : collect();

        return view('grades.index', compact('assignments', 'assignment', 'assessments', 'assessment', 'editingAssessment', 'registrations', 'grades'));
    }

    public function storeAssessment(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'teacher_assignment_id' => ['required', 'integer', 'exists:teacher_assignments,id'],
            'name' => ['nullable', 'string', 'max:120'],
            'assessment_column' => ['nullable', 'string', 'max:80'],
            'type' => ['required', 'string', 'max:50'],
            'maximum_score' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'weight' => ['nullable', 'numeric', 'min:0.001', 'max:10'],
            'assessment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'online_quiz' => ['nullable', 'boolean'],
            'per_question' => ['nullable', 'boolean'],
            'academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
        ]);
        $assignment = TeacherAssignment::query()->findOrFail($data['teacher_assignment_id']);
        $this->ensureCanManageAssignment($request->user(), $assignment);
        if (! $assignment->is_active) {
            throw ValidationException::withMessages(['teacher_assignment_id' => 'Select an active subject assignment. Inactive assignments cannot receive new assessments.']);
        }
        if (! empty($data['academic_term_id']) && ! \App\Models\AcademicTerm::query()->whereKey($data['academic_term_id'])->where('academic_year_id', $assignment->academic_year_id)->exists()) {
            throw ValidationException::withMessages(['academic_term_id' => 'The selected term does not belong to this academic year.']);
        }
        $workflowAction = $request->input('workflow_action', 'draft');
        if (! in_array($workflowAction, ['draft', 'submit'], true)) {
            $workflowAction = 'draft';
        }
        $assessment = Assessment::create([
            'academic_year_id' => $assignment->academic_year_id,
            'academic_term_id' => $data['academic_term_id'] ?? null,
            'teacher_assignment_id' => $assignment->id,
            'subject_id' => $assignment->subject_id,
            'grade_id' => $assignment->grade_id,
            'section_id' => $assignment->section_id,
            'name' => $data['name'] ?? ((($data['assessment_column'] ?? null) ?: str($data['type'])->headline()).' '.today()->format('d/m/Y')),
            'assessment_column' => $data['assessment_column'] ?? null,
            'type' => $data['type'],
            'maximum_score' => $data['maximum_score'],
            'weight' => $data['weight'] ?? 1,
            'assessment_date' => $data['assessment_date'] ?? null,
            'status' => 'draft',
            'review_status' => $workflowAction === 'submit' ? 'submitted' : 'draft',
            'notes' => $data['notes'] ?? null,
            'online_quiz' => $request->boolean('online_quiz'),
            'per_question' => $request->boolean('per_question'),
            'created_by' => $request->user()->id,
        ]);
        $audit->log($workflowAction === 'submit' ? 'assessment.submitted_for_review' : 'assessment.created_by_teacher', $assessment, [], $assessment->only(['name', 'type', 'maximum_score', 'weight', 'teacher_assignment_id', 'review_status']));

        return redirect()->route('grades.index', ['teacher_assignment_id' => $assignment->id, 'assessment_id' => $assessment->id])->with('success', $workflowAction === 'submit' ? 'Assessment submitted to administration for review.' : 'Assessment saved as a draft.');
    }

    public function updateAssessment(Request $request, Assessment $assessment, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'teacher_assignment_id' => ['required', 'integer', 'exists:teacher_assignments,id'],
            'name' => ['required', 'string', 'max:120'],
            'assessment_column' => ['nullable', 'string', 'max:80'],
            'type' => ['required', 'string', 'max:50'],
            'maximum_score' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'weight' => ['nullable', 'numeric', 'min:0.001', 'max:10'],
            'assessment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'online_quiz' => ['nullable', 'boolean'],
            'per_question' => ['nullable', 'boolean'],
            'workflow_action' => ['nullable', Rule::in(['draft', 'submit'])],
        ]);
        $assignment = TeacherAssignment::query()->findOrFail($data['teacher_assignment_id']);
        $this->ensureCanManageAssignment($request->user(), $assignment);
        $this->ensureAssessmentMatchesAssignment($assessment, $assignment);
        if (! $request->user()->hasPermission('academic_year.manage') && ! in_array($assessment->review_status ?? 'draft', ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages(['assessment_id' => 'Only draft or rejected assessments can be edited.']);
        }
        $workflowAction = $request->input('workflow_action', 'draft');
        $assessment->update(collect($data)->except(['teacher_assignment_id', 'workflow_action'])->put('teacher_assignment_id', $assignment->id)->put('status', 'draft')->put('review_status', $workflowAction === 'submit' ? 'submitted' : 'draft')->put('reviewed_by', null)->put('reviewed_at', null)->put('rejection_reason', $workflowAction === 'submit' ? null : $assessment->rejection_reason)->put('weight', $data['weight'] ?? 1)->put('online_quiz', $request->boolean('online_quiz'))->put('per_question', $request->boolean('per_question'))->all());
        $audit->log($workflowAction === 'submit' ? 'assessment.submitted_for_review' : 'assessment.updated_by_teacher', $assessment, [], $assessment->only(['name', 'type', 'maximum_score', 'weight', 'status', 'review_status']));

        return back()->with('success', $workflowAction === 'submit' ? 'Assessment submitted to administration for review.' : 'Assessment draft saved.');
    }

    public function save(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'teacher_assignment_id' => ['required', 'integer', 'exists:teacher_assignments,id'],
            'assessment_id' => ['required', 'integer', 'exists:assessments,id'],
            'save_as' => ['required', Rule::in(['draft', 'published'])],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.registration_id' => ['required', 'integer', 'distinct', 'exists:student_registrations,id'],
            'entries.*.score' => ['nullable', 'numeric', 'min:0'],
            'entries.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $assignment = TeacherAssignment::query()->findOrFail($data['teacher_assignment_id']);
        $this->ensureCanManageAssignment($request->user(), $assignment);
        $assessment = Assessment::query()->findOrFail($data['assessment_id']);
        $this->ensureAssessmentMatchesAssignment($assessment, $assignment);
        if ($assessment->status !== 'open' && ! $request->user()->hasPermission('academic_year.manage')) {
            throw ValidationException::withMessages(['assessment_id' => 'This assessment is waiting for administration approval before grades can be entered.']);
        }
        if ($data['save_as'] === 'published' && ! $request->user()->hasPermission('grades.publish')) {
            abort(403, 'You do not have permission to publish grades.');
        }
        $registrationIds = collect($data['entries'])->pluck('registration_id')->map(fn ($id) => (int) $id)->all();
        $registrations = StudentRegistration::query()
            ->whereIn('id', $registrationIds)
            ->where('academic_year_id', $assignment->academic_year_id)
            ->where('grade_id', $assignment->grade_id)
            ->when($assignment->section_id, fn ($query) => $query->where('section_id', $assignment->section_id))
            ->where('status', 'active')
            ->get()->keyBy('id');
        if ($registrations->count() !== count($registrationIds)) {
            throw ValidationException::withMessages(['entries' => 'Only active students in this assigned class section can receive marks.']);
        }
        foreach ($data['entries'] as $entry) {
            if ($entry['score'] !== null && (float) $entry['score'] > (float) $assessment->maximum_score) {
                throw ValidationException::withMessages(['entries' => "A score cannot exceed the maximum score of {$assessment->maximum_score}."]);
            }
        }

        DB::transaction(function () use ($data, $assignment, $assessment, $registrations, $request): void {
            foreach ($data['entries'] as $line) {
                $registration = $registrations[(int) $line['registration_id']];
                $existing = GradeEntry::query()->where('assessment_id', $assessment->id)->where('student_id', $registration->student_id)->first();
                if ($existing?->is_locked && ! $request->user()->hasPermission('academic_year.manage')) {
                    throw ValidationException::withMessages(['entries' => 'One or more marks are locked. An authorized administrator must unlock them first.']);
                }
                $newValues = [
                    'student_registration_id' => $registration->id,
                    'teacher_assignment_id' => $assignment->id,
                    'teacher_id' => $assignment->teacher_id,
                    'score' => $line['score'],
                    'notes' => $line['notes'] ?? null,
                    'status' => $data['save_as'],
                    'recorded_by' => $request->user()->id,
                    'published_at' => $data['save_as'] === 'published' ? now() : null,
                    'published_by' => $data['save_as'] === 'published' ? $request->user()->id : null,
                ];
                $entry = $existing ?: new GradeEntry(['student_id' => $registration->student_id, 'assessment_id' => $assessment->id]);
                $oldValues = $existing?->only(['score', 'notes', 'status', 'is_locked']) ?? [];
                $entry->fill($newValues)->save();
                if ($oldValues !== [] && $oldValues !== $entry->only(array_keys($oldValues))) {
                    GradeChangeAudit::create([
                        'grade_entry_id' => $entry->id,
                        'changed_by' => $request->user()->id,
                        'old_values' => $oldValues,
                        'new_values' => $entry->only(['score', 'notes', 'status', 'is_locked']),
                    ]);
                }
            }
        });
        $audit->log('grades.bulk_saved', $assessment, [], ['teacher_assignment_id' => $assignment->id, 'save_as' => $data['save_as'], 'entries' => count($data['entries'])]);

        return redirect()->route('grades.index', ['teacher_assignment_id' => $assignment->id, 'assessment_id' => $assessment->id])->with('success', 'Grades saved.');
    }

    public function lock(Request $request, GradeEntry $gradeEntry, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('grades.publish'), 403);
        $locked = $request->boolean('is_locked');
        if (! $locked && ! $request->user()->hasPermission('academic_year.manage')) {
            abort(403, 'Only an authorized administrator can unlock a grade.');
        }
        $old = $gradeEntry->only(['is_locked', 'status']);
        $gradeEntry->update(['is_locked' => $locked, 'status' => $locked ? 'locked' : 'draft']);
        GradeChangeAudit::create(['grade_entry_id' => $gradeEntry->id, 'changed_by' => $request->user()->id, 'old_values' => $old, 'new_values' => $gradeEntry->only(array_keys($old)), 'reason' => $locked ? 'Grade locked' : 'Grade unlocked']);
        $audit->log($locked ? 'grade.locked' : 'grade.unlocked', $gradeEntry, $old, $gradeEntry->only(array_keys($old)));

        return back()->with('success', $locked ? 'Grade locked.' : 'Grade unlocked.');
    }

    /** @return Collection<int, TeacherAssignment> */
    private function assignmentsFor(User $user)
    {
        return TeacherAssignment::query()
            ->with('teacher', 'subject', 'grade', 'section', 'academicYear')
            ->where('is_active', true)
            ->when(! $user->hasPermission('academic_year.manage'), function ($query) use ($user): void {
                abort_unless($user->hasPermission('grades.enter') && $user->teacher_id, 403, 'Link this account to a teacher profile before entering grades.');
                $query->where('teacher_id', $user->teacher_id);
            })
            ->orderByDesc('academic_year_id')->get();
    }

    private function ensureCanManageAssignment(User $user, TeacherAssignment $assignment): void
    {
        if ($user->hasPermission('academic_year.manage')) {
            return;
        }
        abort_unless($user->hasPermission('grades.enter') && (int) $user->teacher_id === $assignment->teacher_id, 403);
    }

    private function ensureAssessmentMatchesAssignment(Assessment $assessment, TeacherAssignment $assignment): void
    {
        if ($assessment->teacher_assignment_id !== null) {
            if ((int) $assessment->teacher_assignment_id !== (int) $assignment->id) {
                throw ValidationException::withMessages(['assessment_id' => 'This assessment belongs to a different teacher subject assignment.']);
            }

            return;
        }

        $matches = $assessment->academic_year_id === $assignment->academic_year_id
            && $assessment->subject_id === $assignment->subject_id
            && $assessment->grade_id === $assignment->grade_id
            && ($assessment->section_id === null || $assignment->section_id === null || $assessment->section_id === $assignment->section_id);
        if (! $matches) {
            throw ValidationException::withMessages(['assessment_id' => 'This assessment belongs to a different teacher subject assignment.']);
        }
    }

}
