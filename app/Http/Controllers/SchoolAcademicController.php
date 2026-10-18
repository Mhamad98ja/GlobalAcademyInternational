<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\TeacherSubstitution;
use App\Models\TimetableEntry;
use App\Models\StudentRegistration;
use App\Services\AuditService;
use App\Notifications\StudentPortalNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SchoolAcademicController extends Controller
{
    public function index(): View
    {
        return $this->operationsPage('overview');
    }

    public function subjects(): View
    {
        return $this->operationsPage('subjects');
    }

    public function terms(): RedirectResponse
    {
        return redirect()->to(route('academic.index').'#terms-semesters');
    }

    public function assignments(): View
    {
        return $this->operationsPage('assignments');
    }

    public function teacherAssignments(Teacher $teacher): View
    {
        return $this->operationsPage('assignments', $teacher->id, $teacher);
    }

    public function createAssignment(Request $request): View
    {
        $teachers = Teacher::query()->where('is_active', true)->orderBy('first_name')->orderBy('last_name')->get();
        $selectedTeacherId = $request->integer('teacher_id') ?: null;

        return view('school-academic.assignment-create', [
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'grades' => Grade::query()->with('sections')->orderBy('sort_order')->get(),
            'teachers' => $teachers,
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedTeacher' => $teachers->firstWhere('id', $selectedTeacherId),
        ]);
    }

    private function operationsPage(string $activeSection, ?int $selectedTeacherId = null, ?Teacher $selectedTeacher = null): View
    {
        $teachers = Teacher::query()->where('is_active', true)->orderBy('first_name')->orderBy('last_name')->get();
        $assignmentQuery = TeacherAssignment::query()->with('academicYear', 'teacher', 'subject', 'grade', 'section')->latest();
        if ($selectedTeacherId !== null) {
            $assignmentQuery->where('teacher_id', $selectedTeacherId);
        }

        return view('school-academic.index', [
            'activeSection' => $activeSection,
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'grades' => Grade::query()->with('sections')->orderBy('sort_order')->get(),
            'teachers' => $teachers,
            'subjects' => Subject::query()->orderBy('name')->get(),
            'terms' => AcademicTerm::query()->with('academicYear')->orderByDesc('starts_on')->orderBy('sort_order')->get(),
            'assessments' => Assessment::query()->with('academicYear', 'term', 'assignment.teacher', 'assignment.subject', 'assignment.grade', 'assignment.section', 'subject', 'grade', 'section')->latest('assessment_date')->get(),
            'assignments' => $assignmentQuery->get(),
            'selectedTeacherId' => $selectedTeacherId,
            'selectedTeacher' => $selectedTeacher ?? $teachers->firstWhere('id', $selectedTeacherId),
            'timetableCounts' => TimetableEntry::query()
                ->selectRaw('academic_year_id, teacher_id, COUNT(*) as total')
                ->groupBy('academic_year_id', 'teacher_id')
                ->get()
                ->mapWithKeys(fn (TimetableEntry $entry): array => [$entry->teacher_id.'-'.$entry->academic_year_id => (int) $entry->total]),
            'substitutionCounts' => TeacherSubstitution::query()
                ->with('entry:id,academic_year_id')
                ->whereIn('status', ['assigned', 'completed'])
                ->whereBetween('substitution_date', [today()->startOfWeek()->toDateString(), today()->endOfWeek()->toDateString()])
                ->whereNotNull('substitute_teacher_id')
                ->get()
                ->filter(fn (TeacherSubstitution $substitution): bool => $substitution->entry !== null)
                ->groupBy(fn (TeacherSubstitution $substitution): string => $substitution->substitute_teacher_id.'-'.$substitution->entry->academic_year_id)
                ->map->count(),
        ]);
    }

    public function storeSubject(Request $request, AuditService $audit): RedirectResponse
    {
        $subject = Subject::create($request->validate([
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)]);
        $audit->log('subject.created', $subject, [], $subject->only(['code', 'name']));

        return back()->with('success', 'Subject created.');
    }

    public function updateSubject(Request $request, Subject $subject, AuditService $audit): RedirectResponse
    {
        $old = $subject->only(['code', 'name', 'description', 'is_active']);
        $subject->update($request->validate([
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:120', Rule::unique('subjects', 'name')->ignore($subject)->where('branch_id', $subject->branch_id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')]);
        $audit->log('subject.updated', $subject, $old, $subject->only(array_keys($old)));

        return back()->with('success', 'Subject updated.');
    }

    public function storeAssessment(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $this->assessmentData($request);
        $assessment = Assessment::create($data + ['review_status' => $data['status'] === 'open' ? 'approved' : 'draft', 'created_by' => $request->user()->id]);
        $audit->log('assessment.created', $assessment, [], $assessment->only(['academic_year_id', 'academic_term_id', 'teacher_assignment_id', 'name', 'status']));

        return back()->with('success', 'Assessment created.');
    }

    public function updateAssessment(Request $request, Assessment $assessment, AuditService $audit): RedirectResponse
    {
        $old = $assessment->only(['academic_year_id', 'academic_term_id', 'teacher_assignment_id', 'subject_id', 'grade_id', 'section_id', 'name', 'type', 'maximum_score', 'assessment_date', 'status']);
        $data = $this->assessmentData($request);
        $assessment->update($data + ['review_status' => in_array($data['status'], ['open', 'closed'], true) ? 'approved' : 'draft']);
        $audit->log('assessment.updated', $assessment, $old, $assessment->only(array_keys($old)));

        return back()->with('success', 'Assessment updated.');
    }

    public function approveAssessment(Assessment $assessment, AuditService $audit): RedirectResponse
    {
        abort_unless($assessment->review_status === 'submitted', 422, 'Only submitted assessments can be approved.');
        $assessment->update(['review_status' => 'approved', 'status' => 'open', 'reviewed_by' => request()->user()->id, 'reviewed_at' => now(), 'rejection_reason' => null]);
        $assessment->grades()
            ->whereNotNull('score')
            ->whereIn('status', ['draft', 'published'])
            ->update(['status' => 'published', 'published_at' => now(), 'published_by' => request()->user()->id]);
        $audit->log('assessment.approved', $assessment, [], ['review_status' => 'approved', 'status' => 'open']);
        $this->notifyAssessmentStudents($assessment);

        return back()->with('success', 'Assessment approved and published to students.');
    }

    public function rejectAssessment(Request $request, Assessment $assessment, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:2000']]);
        abort_unless($assessment->review_status === 'submitted', 422, 'Only submitted assessments can be rejected.');
        $assessment->update(['review_status' => 'rejected', 'status' => 'draft', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'rejection_reason' => $data['rejection_reason']]);
        $audit->log('assessment.rejected', $assessment, [], ['review_status' => 'rejected', 'rejection_reason' => $data['rejection_reason']]);

        return back()->with('success', 'Assessment returned to the teacher with feedback.');
    }

    private function notifyAssessmentStudents(Assessment $assessment): void
    {
        $assignment = $assessment->assignment;
        if (! $assignment) {
            return;
        }
        StudentRegistration::query()->with('student')->where('status', 'active')
            ->where('academic_year_id', $assignment->academic_year_id)
            ->where('grade_id', $assignment->grade_id)
            ->when($assignment->section_id, fn ($query) => $query->where('section_id', $assignment->section_id))
            ->whereDoesntHave('subjectExemptions', fn ($query) => $query->where('subject_id', $assignment->subject_id))
            ->get()->each(function (StudentRegistration $registration) use ($assessment, $assignment): void {
            $registration->student?->notify(new StudentPortalNotification(
                'New '.$assessment->type.' scheduled',
                $assessment->name.' is scheduled for '.($assessment->assessment_date?->format('d M Y') ?? 'a future date').'.',
                route('student-portal.index'),
                $assignment->subject_id,
                $registration->id,
            ));
        });
    }

    public function storeAssignment(Request $request, AuditService $audit): RedirectResponse
    {
        $assignment = TeacherAssignment::create($this->assignmentData($request));
        $audit->log('teacher_assignment.created', $assignment, [], $assignment->only(['teacher_id', 'subject_id', 'grade_id', 'section_id']));

        return redirect()->route('school-academic.assignments', ['teacher_id' => $assignment->teacher_id])->with('success', 'Teacher subject assignment created.');
    }

    public function updateAssignment(Request $request, TeacherAssignment $assignment, AuditService $audit): RedirectResponse
    {
        $old = $assignment->only(['academic_year_id', 'teacher_id', 'subject_id', 'grade_id', 'section_id', 'required_periods_per_week', 'weekly_period_limit', 'is_active']);
        $assignment->update($this->assignmentCorrectionData($request, $assignment));
        $audit->log('teacher_assignment.updated', $assignment, $old, $assignment->only(array_keys($old)));

        return back()->with('success', 'Teacher subject assignment updated.');
    }

    public function destroyAssignment(TeacherAssignment $assignment, AuditService $audit): RedirectResponse
    {
        $linkedRecords = collect([
            'timetable entries' => $assignment->timetableEntries()->exists(),
            'grade entries' => $assignment->gradeEntries()->exists(),
            'course content' => $assignment->courseContents()->exists(),
            'performance evaluations' => $assignment->performanceEvaluations()->exists(),
            'assessments' => Assessment::query()->where('teacher_assignment_id', $assignment->id)->exists(),
        ])->filter()->keys()->implode(', ');

        if ($linkedRecords !== '') {
            return back()->with('error', "This assignment cannot be deleted because it has {$linkedRecords}. Deactivate it instead.");
        }

        $old = $assignment->only(['academic_year_id', 'teacher_id', 'subject_id', 'grade_id', 'section_id', 'weekly_period_limit', 'is_active']);
        $assignment->delete();
        $audit->log('teacher_assignment.deleted', $assignment, $old);

        return back()->with('success', 'Teacher subject assignment deleted.');
    }

    /** @return array<string, mixed> */
    private function assessmentData(Request $request): array
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
            'teacher_assignment_id' => ['nullable', 'integer', 'exists:teacher_assignments,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', 'max:50'],
            'maximum_score' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'weight' => ['nullable', 'numeric', 'min:0.001', 'max:10'],
            'assessment_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['draft', 'open', 'closed'])],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        if (! $request->has('required_periods_per_week')) {
            unset($data['required_periods_per_week']);
        }

        if (! empty($data['teacher_assignment_id'])) {
            $assignment = TeacherAssignment::query()->findOrFail($data['teacher_assignment_id']);
            $data['academic_year_id'] = $assignment->academic_year_id;
            $data['subject_id'] = $assignment->subject_id;
            $data['grade_id'] = $assignment->grade_id;
            $data['section_id'] = $assignment->section_id;
        }
        if ($data['academic_term_id'] && ! AcademicTerm::query()->whereKey($data['academic_term_id'])->where('academic_year_id', $data['academic_year_id'])->exists()) {
            throw ValidationException::withMessages(['academic_term_id' => 'The selected term does not belong to this academic year.']);
        }
        $this->ensureSectionBelongsToGrade($data['section_id'] ?? null, $data['grade_id'] ?? null);
        $data['weight'] = $data['weight'] ?? 1;

        return $data;
    }

    /** @return array<string, mixed> */
    private function assignmentCorrectionData(Request $request, TeacherAssignment $assignment): array
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'grade_id' => ['required', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! Subject::query()->whereKey($data['subject_id'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'Choose an active subject for this teacher assignment.']);
        }

        $this->ensureSectionBelongsToGrade($data['section_id'] ?? null, $data['grade_id']);

        $duplicate = TeacherAssignment::query()
            ->where('academic_year_id', $assignment->academic_year_id)
            ->where('teacher_id', $assignment->teacher_id)
            ->where('subject_id', $data['subject_id'])
            ->where('grade_id', $data['grade_id'])
            ->where('section_id', $data['section_id'] ?? null)
            ->whereKeyNot($assignment->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['subject_id' => 'This teacher assignment already exists.']);
        }

        return [
            'subject_id' => $data['subject_id'],
            'grade_id' => $data['grade_id'],
            'section_id' => $data['section_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /** @return array<string, mixed> */
    private function assignmentData(Request $request, ?TeacherAssignment $assignment = null): array
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'grade_id' => ['required', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'required_periods_per_week' => ['nullable', 'integer', 'min:0', 'max:50'],
            'weekly_period_limit' => ['nullable', 'integer', 'min:1', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        if (! Subject::query()->whereKey($data['subject_id'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'Choose an active subject for this teacher assignment.']);
        }
        $this->ensureSectionBelongsToGrade($data['section_id'] ?? null, $data['grade_id']);
        $data['is_active'] = $request->boolean('is_active', true);
        $existingAssignments = TeacherAssignment::query()
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('teacher_id', $data['teacher_id'])
            ->when($assignment, fn ($query) => $query->whereKeyNot($assignment->id));
        $existingLimit = $existingAssignments->where('is_active', true)->whereNotNull('weekly_period_limit')->min('weekly_period_limit');
        if ($existingLimit !== null) {
            if ($data['weekly_period_limit'] === null) {
                $data['weekly_period_limit'] = (int) $existingLimit;
            } elseif ((int) $data['weekly_period_limit'] !== (int) $existingLimit) {
                throw ValidationException::withMessages(['weekly_period_limit' => 'Use the same weekly maximum for all assignments of this teacher.']);
            }
        }
        if ($data['is_active'] && array_key_exists('required_periods_per_week', $data) && $data['weekly_period_limit'] !== null) {
            $existingRequiredPeriods = (clone $existingAssignments)->where('is_active', true)->sum('required_periods_per_week');
            $totalRequiredPeriods = $existingRequiredPeriods + (int) ($data['required_periods_per_week'] ?? 0);
            if ($totalRequiredPeriods > (int) $data['weekly_period_limit']) {
                throw ValidationException::withMessages([
                    'required_periods_per_week' => "This teacher would have {$totalRequiredPeriods} required periods per week, exceeding the maximum of {$data['weekly_period_limit']}.",
                ]);
            }
        }
        $duplicate = TeacherAssignment::query()
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('teacher_id', $data['teacher_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('grade_id', $data['grade_id'])
            ->where('section_id', $data['section_id'] ?? null)
            ->when($assignment, fn ($query) => $query->whereKeyNot($assignment->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['teacher_id' => 'This teacher assignment already exists.']);
        }

        return $data;
    }

    private function ensureSectionBelongsToGrade(?int $sectionId, ?int $gradeId): void
    {
        if ($sectionId && (! $gradeId || ! Section::query()->whereKey($sectionId)->where('grade_id', $gradeId)->exists())) {
            throw ValidationException::withMessages(['section_id' => 'The selected section does not belong to the selected grade.']);
        }
    }
}
