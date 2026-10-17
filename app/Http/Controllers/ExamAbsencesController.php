<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\ExamAbsence;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExamAbsencesController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'assessment_id' => ['nullable', 'integer', 'exists:assessments,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'is_excused' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['recorded', 'makeup_scheduled', 'completed', 'waived'])],
        ]);
        $absences = ExamAbsence::query()->with('student', 'registration.subjectExemptions', 'assessment.assignment.teacher', 'assessment.assignment.subject', 'assessment.subject')
            ->when($filters['assessment_id'] ?? null, fn ($query, $id) => $query->where('assessment_id', $id))
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when(array_key_exists('is_excused', $filters), fn ($query) => $query->where('is_excused', $filters['is_excused']))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('absence_date')->paginate(25)->withQueryString();
        $absences->setCollection($absences->getCollection()->reject(fn ($entry) => $entry->registration?->subjectExemptions->contains('subject_id', $entry->subject_id ?? $entry->assessment?->subject_id ?? $entry->assessment?->assignment?->subject_id) ?? false));

        return view('exam-absences.index', [
            'absences' => $absences,
            'filters' => $filters,
            'students' => Student::query()->orderBy('first_name')->orderBy('last_name')->get(),
            'registrations' => StudentRegistration::query()->with('student', 'academicYear', 'grade', 'section')->where('status', 'active')->latest('registered_on')->get(),
            'assessments' => Assessment::query()->with('academicYear', 'assignment.teacher', 'assignment.subject', 'assignment.grade', 'assignment.section', 'subject', 'grade', 'section')->orderByDesc('assessment_date')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'student_registration_id' => ['required', 'integer', 'exists:student_registrations,id'],
            'assessment_id' => ['required', 'integer', 'exists:assessments,id'],
            'absence_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'is_excused' => ['nullable', 'boolean'],
            'makeup_required' => ['nullable', 'boolean'],
            'makeup_exam_date' => ['nullable', 'date'],
            'medical_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::in(['recorded', 'makeup_scheduled', 'completed', 'waived'])],
        ]);
        $registration = StudentRegistration::query()->whereKey($data['student_registration_id'])->where('student_id', $data['student_id'])->firstOrFail();
        $assessment = Assessment::query()->findOrFail($data['assessment_id']);
        $validAssessment = $assessment->academic_year_id === $registration->academic_year_id
            && ($assessment->grade_id === null || $assessment->grade_id === $registration->grade_id)
            && ($assessment->section_id === null || $assessment->section_id === $registration->section_id);
        if (! $validAssessment) {
            throw ValidationException::withMessages(['assessment_id' => 'The selected assessment does not apply to this student registration.']);
        }
        $subjectId = $assessment->subject_id ?: $assessment->assignment?->subject_id;
        if ($subjectId && $registration->subjectExemptions()->where('subject_id', $subjectId)->exists()) {
            throw ValidationException::withMessages(['assessment_id' => 'This student is exempt from the assessment subject.']);
        }
        if ($data['makeup_required'] ?? false) {
            if (empty($data['makeup_exam_date'])) {
                throw ValidationException::withMessages(['makeup_exam_date' => 'A makeup exam date is required when a makeup is required.']);
            }
        } else {
            $data['makeup_exam_date'] = null;
        }
        unset($data['medical_attachment']);
        if ($request->hasFile('medical_attachment')) {
            $data['medical_attachment_path'] = $request->file('medical_attachment')->store('exam-absence-medical', 'local');
        }
        $absence = ExamAbsence::updateOrCreate(
            ['student_id' => $registration->student_id, 'assessment_id' => $assessment->id],
            $data + [
                'student_registration_id' => $registration->id,
                'academic_year_id' => $assessment->academic_year_id,
                'academic_term_id' => $assessment->academic_term_id,
                'subject_id' => $assessment->subject_id,
                'grade_id' => $registration->grade_id,
                'section_id' => $registration->section_id,
                'is_excused' => $request->boolean('is_excused'),
                'makeup_required' => $request->boolean('makeup_required'),
                'created_by' => $request->user()->id,
            ],
        );
        $audit->log('exam_absence.saved', $absence, [], $absence->only(['student_id', 'assessment_id', 'is_excused', 'status']));

        return back()->with('success', 'Exam absence recorded in the student archive.');
    }
}
