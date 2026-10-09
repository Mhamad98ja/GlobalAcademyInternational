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
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SchoolAcademicController extends Controller
{
    public function index(): View
    {
        return view('school-academic.index', [
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'grades' => Grade::query()->with('sections')->orderBy('sort_order')->get(),
            'teachers' => Teacher::query()->where('is_active', true)->orderBy('first_name')->orderBy('last_name')->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
            'terms' => AcademicTerm::query()->with('academicYear')->orderByDesc('starts_on')->orderBy('sort_order')->get(),
            'assessments' => Assessment::query()->with('academicYear', 'term', 'subject', 'grade', 'section')->latest('assessment_date')->get(),
            'assignments' => TeacherAssignment::query()->with('academicYear', 'teacher', 'subject', 'grade', 'section')->latest()->get(),
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

    public function storeTerm(Request $request, AuditService $audit): RedirectResponse
    {
        $term = AcademicTerm::create($this->termData($request));
        $audit->log('academic_term.created', $term, [], $term->only(['academic_year_id', 'name', 'status']));

        return back()->with('success', 'Academic term created.');
    }

    public function updateTerm(Request $request, AcademicTerm $term, AuditService $audit): RedirectResponse
    {
        $old = $term->only(['academic_year_id', 'name', 'type', 'starts_on', 'ends_on', 'status', 'sort_order']);
        $term->update($this->termData($request, $term));
        $audit->log('academic_term.updated', $term, $old, $term->only(array_keys($old)));

        return back()->with('success', 'Academic term updated.');
    }

    public function storeAssessment(Request $request, AuditService $audit): RedirectResponse
    {
        $assessment = Assessment::create($this->assessmentData($request) + ['created_by' => $request->user()->id]);
        $audit->log('assessment.created', $assessment, [], $assessment->only(['academic_year_id', 'academic_term_id', 'name', 'status']));

        return back()->with('success', 'Assessment created.');
    }

    public function updateAssessment(Request $request, Assessment $assessment, AuditService $audit): RedirectResponse
    {
        $old = $assessment->only(['academic_year_id', 'academic_term_id', 'subject_id', 'grade_id', 'section_id', 'name', 'type', 'maximum_score', 'assessment_date', 'status']);
        $assessment->update($this->assessmentData($request));
        $audit->log('assessment.updated', $assessment, $old, $assessment->only(array_keys($old)));

        return back()->with('success', 'Assessment updated.');
    }

    public function storeAssignment(Request $request, AuditService $audit): RedirectResponse
    {
        $assignment = TeacherAssignment::create($this->assignmentData($request));
        $audit->log('teacher_assignment.created', $assignment, [], $assignment->only(['teacher_id', 'subject_id', 'grade_id', 'section_id']));

        return back()->with('success', 'Teacher course assignment created.');
    }

    public function updateAssignment(Request $request, TeacherAssignment $assignment, AuditService $audit): RedirectResponse
    {
        $old = $assignment->only(['academic_year_id', 'teacher_id', 'subject_id', 'grade_id', 'section_id', 'required_periods_per_week', 'weekly_period_limit', 'is_active']);
        $assignment->update($this->assignmentData($request, $assignment));
        $audit->log('teacher_assignment.updated', $assignment, $old, $assignment->only(array_keys($old)));

        return back()->with('success', 'Teacher course assignment updated.');
    }

    /** @return array<string, mixed> */
    private function termData(Request $request, ?AcademicTerm $term = null): array
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'max:50'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'status' => ['required', Rule::in(['draft', 'open', 'closed'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        $year = AcademicYear::findOrFail($data['academic_year_id']);
        if ($data['starts_on'] < $year->starts_on->toDateString() || $data['ends_on'] > $year->ends_on->toDateString()) {
            throw ValidationException::withMessages(['starts_on' => 'Term dates must fall within the selected academic year.']);
        }
        $duplicate = AcademicTerm::query()->where('academic_year_id', $data['academic_year_id'])->where('name', $data['name'])->when($term, fn ($query) => $query->whereKeyNot($term->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'This academic year already has a term with that name.']);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function assessmentData(Request $request): array
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
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
        if ($data['academic_term_id'] && ! AcademicTerm::query()->whereKey($data['academic_term_id'])->where('academic_year_id', $data['academic_year_id'])->exists()) {
            throw ValidationException::withMessages(['academic_term_id' => 'The selected term does not belong to this academic year.']);
        }
        $this->ensureSectionBelongsToGrade($data['section_id'] ?? null, $data['grade_id'] ?? null);
        $data['weight'] = $data['weight'] ?? 1;

        return $data;
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
        $this->ensureSectionBelongsToGrade($data['section_id'] ?? null, $data['grade_id']);
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
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function ensureSectionBelongsToGrade(?int $sectionId, ?int $gradeId): void
    {
        if ($sectionId && (! $gradeId || ! Section::query()->whereKey($sectionId)->where('grade_id', $gradeId)->exists())) {
            throw ValidationException::withMessages(['section_id' => 'The selected section does not belong to the selected grade.']);
        }
    }
}
