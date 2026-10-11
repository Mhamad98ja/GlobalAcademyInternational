<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ExamAbsence;
use App\Models\Grade;
use App\Models\GradeEntry;
use App\Models\MedicalRecord;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentIncident;
use App\Models\StudentRegistration;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherSubstitution;
use App\Models\TimetableEntry;
use App\Services\WorkloadService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolReportsController extends Controller
{
    private const REPORTS = [
        'student_attendance' => 'Student attendance',
        'teacher_attendance' => 'Teacher attendance',
        'incidents' => 'Student incidents and actions',
        'medical_records' => 'Medical records',
        'exam_absences' => 'Exam absences',
        'substitutions' => 'Teacher substitutions',
        'teacher_workload' => 'Teacher workload',
        'timetable' => 'Timetable',
        'grades' => 'Grades',
        'academic_years' => 'Academic years and terms',
        'class_sections' => 'Classes and sections',
    ];

    public function index(Request $request, WorkloadService $workload): View
    {
        [$report, $filters, $headings, $rows] = $this->data($request, $workload);

        return view('school-reports.index', $this->viewData($report, $filters, $headings, $rows));
    }

    public function print(Request $request, WorkloadService $workload): View
    {
        [$report, $filters, $headings, $rows] = $this->data($request, $workload);

        return view('school-reports.print', $this->viewData($report, $filters, $headings, $rows));
    }

    public function csv(Request $request, WorkloadService $workload): StreamedResponse
    {
        [$report, , $headings, $rows] = $this->data($request, $workload);

        return response()->streamDownload(function () use ($headings, $rows): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, $headings);
            foreach ($rows as $row) {
                fputcsv($stream, $row);
            }
            fclose($stream);
        }, $report.'-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0:string,1:array<string,mixed>,2:list<string>,3:Collection<int,array<int,string|int|float|null>>} */
    private function data(Request $request, WorkloadService $workload): array
    {
        $filters = $request->validate([
            'report' => ['nullable', Rule::in(array_keys(self::REPORTS))],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['required', 'integer', 'distinct', 'exists:students,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'action_type_id' => ['nullable', 'integer', 'exists:action_types,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
        $report = $filters['report'] ?? 'student_attendance';
        if ($report === 'medical_records') {
            abort_unless($request->user()->hasPermission('medical.view'), 403);
        }
        if ($report === 'incidents') {
            abort_unless($request->user()->hasPermission('discipline.view'), 403);
        }

        [$headings, $rows] = match ($report) {
            'student_attendance' => $this->studentAttendance($filters),
            'teacher_attendance' => $this->teacherAttendance($filters),
            'incidents' => $this->incidents($filters),
            'medical_records' => $this->medicalRecords($filters),
            'exam_absences' => $this->examAbsences($filters),
            'substitutions' => $this->substitutions($filters),
            'teacher_workload' => $this->teacherWorkload($filters, $workload),
            'timetable' => $this->timetable($filters),
            'grades' => $this->grades($filters),
            'academic_years' => $this->academicYears($filters),
            default => $this->classSections($filters),
        };

        return [$report, $filters, $headings, $rows];
    }

    /** @return array<string,mixed> */
    private function viewData(string $report, array $filters, array $headings, Collection $rows): array
    {
        return [
            'report' => $report,
            'reports' => array_filter(self::REPORTS, fn ($key) => match ($key) {
                'medical_records' => auth()->user()->hasPermission('medical.view'),
                'incidents' => auth()->user()->hasPermission('discipline.view'),
                default => true,
            }, ARRAY_FILTER_USE_KEY),
            'reportFilters' => match ($report) {
                'student_attendance' => ['academic_year_id', 'student_id', 'student_ids', 'grade_id', 'section_id', 'date_from', 'date_to', 'status'],
                'incidents' => ['academic_year_id', 'student_id', 'grade_id', 'section_id', 'date_from', 'date_to', 'status'],
                'exam_absences' => ['academic_year_id', 'academic_term_id', 'student_id', 'grade_id', 'section_id', 'subject_id', 'date_from', 'date_to', 'status'],
                'grades' => ['academic_year_id', 'academic_term_id', 'student_id', 'teacher_id', 'grade_id', 'section_id', 'subject_id', 'date_from', 'date_to', 'status'],
                'class_sections' => ['academic_year_id', 'student_id', 'grade_id', 'section_id', 'status'],
                'academic_years' => ['academic_year_id'],
                'medical_records' => ['student_id', 'teacher_id', 'date_from', 'date_to', 'status'],
                'teacher_workload' => ['academic_year_id', 'teacher_id'],
                'timetable' => ['academic_year_id', 'teacher_id', 'grade_id', 'section_id', 'subject_id'],
                default => ['teacher_id', 'date_from', 'date_to', 'status'],
            },
            'filters' => $filters,
            'headings' => $headings,
            'rows' => $rows,
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'terms' => AcademicTerm::query()->with('academicYear')->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))->orderByDesc('starts_on')->get(),
            'students' => Student::query()
                ->when($report === 'student_attendance', fn ($query) => $query->whereHas('registrations', fn ($registrations) => $registrations
                    ->where('status', 'active')
                    ->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
                    ->when($filters['grade_id'] ?? null, fn ($query, $id) => $query->where('grade_id', $id))
                    ->when($filters['section_id'] ?? null, fn ($query, $id) => $query->where('section_id', $id))))
                ->orderBy('first_name')->orderBy('last_name')->get(),
            'teachers' => Teacher::query()->where('is_active', true)->orderBy('first_name')->get(),
            'grades' => Grade::query()->with('sections')->orderBy('sort_order')->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
        ];
    }

    /** @return array{0:list<string>,1:Collection<int,array<int,string|int|float|null>>} */
    private function studentAttendance(array $filters): array
    {
        $records = StudentAttendance::query()->with('student', 'grade', 'section')
            ->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($filters['student_ids'] ?? [], fn ($query, array $ids) => $query->whereIn('student_id', $ids))
            ->when($filters['grade_id'] ?? null, fn ($query, $id) => $query->where('grade_id', $id))
            ->when($filters['section_id'] ?? null, fn ($query, $id) => $query->where('section_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('attendance_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('attendance_date', '<=', $date))
            ->latest('attendance_date')->limit(1000)->get();

        return [['Date', 'Student', 'Student number', 'Grade', 'Section', 'Period', 'Status', 'Notes'], $records->map(fn ($record) => [$record->attendance_date?->format('Y-m-d'), $record->student?->full_name, $record->student?->student_number, $record->grade?->name, $record->section?->name, $record->period_number ?: 'Daily', str($record->status)->headline(), $record->notes])];
    }

    /** @return array{0:list<string>,1:Collection<int,array<int,string|int|float|null>>} */
    private function teacherAttendance(array $filters): array
    {
        $records = TeacherAttendance::query()->with('teacher')
            ->when($filters['teacher_id'] ?? null, fn ($query, $id) => $query->where('teacher_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('attendance_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('attendance_date', '<=', $date))
            ->latest('attendance_date')->limit(1000)->get();

        return [['Date', 'Teacher', 'Status', 'Check in', 'Check out', 'Late minutes', 'Early leave minutes', 'Notes'], $records->map(fn ($record) => [$record->attendance_date?->format('Y-m-d'), $record->teacher?->full_name, str($record->status)->headline(), $record->checked_in_at, $record->checked_out_at, $record->late_minutes, $record->early_leave_minutes, $record->notes])];
    }

    /** @return array{0:list<string>,1:Collection<int,array<int,string|int|float|null>>} */
    private function incidents(array $filters): array
    {
        $records = StudentIncident::query()->with('student', 'category', 'type', 'grade', 'section')
            ->when($filters['grade_id'] ?? null, fn ($query, $id) => $query->where('grade_id', $id))
            ->when($filters['section_id'] ?? null, fn ($query, $id) => $query->where('section_id', $id))
            ->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($filters['action_type_id'] ?? null, fn ($query, $id) => $query->where('action_type_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('incident_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('incident_date', '<=', $date))
            ->latest('incident_date')->limit(1000)->get();

        return [['Date', 'Student', 'Category', 'Action type', 'Class', 'Location', 'Status', 'Action taken'], $records->map(fn ($record) => [$record->incident_date?->format('Y-m-d'), $record->student?->full_name, $record->category?->name, $record->type?->name, trim(($record->grade?->name ?? '').' '.($record->section?->name ?? '')), $record->location, str($record->status)->headline(), $record->action_taken])];
    }

    /** @return array{0:list<string>,1:Collection<int,array<int,string|int|float|null>>} */
    private function medicalRecords(array $filters): array
    {
        $records = MedicalRecord::query()->with('student', 'teacher')
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($filters['teacher_id'] ?? null, fn ($query, $id) => $query->where('teacher_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('recorded_on', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('recorded_on', '<=', $date))
            ->latest('recorded_on')->limit(1000)->get();

        return [['Date', 'Person', 'Type', 'Title', 'Restrictions', 'Status'], $records->map(fn ($record) => [$record->recorded_on?->format('Y-m-d'), $record->student?->full_name ?? $record->teacher?->full_name, str($record->type)->headline(), $record->title, $record->restrictions, str($record->status)->headline()])];
    }

    /** @return array{0:list<string>,1:Collection<int,array<int,string|int|float|null>>} */
    private function examAbsences(array $filters): array
    {
        $records = ExamAbsence::query()->with('student', 'assessment.assignment.teacher', 'assessment.assignment.subject', 'assessment.subject')
            ->when($filters['academic_term_id'] ?? null, fn ($query, $id) => $query->where('academic_term_id', $id))
            ->when($filters['grade_id'] ?? null, fn ($query, $id) => $query->where('grade_id', $id))
            ->when($filters['section_id'] ?? null, fn ($query, $id) => $query->where('section_id', $id))
            ->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($filters['subject_id'] ?? null, fn ($query, $id) => $query->where('subject_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('absence_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('absence_date', '<=', $date))
            ->latest('absence_date')->limit(1000)->get();

        return [['Date', 'Student', 'Assessment', 'Subject', 'Excused', 'Makeup required', 'Makeup date', 'Status'], $records->map(fn ($record) => [$record->absence_date?->format('Y-m-d'), $record->student?->full_name, $record->assessment?->name, $record->assessment?->subject?->name, $record->is_excused ? 'Yes' : 'No', $record->makeup_required ? 'Yes' : 'No', $record->makeup_exam_date?->format('Y-m-d'), str($record->status)->headline()])];
    }

    /** @return array{0:list<string>,1:Collection<int,array<int,string|int|float|null>>} */
    private function substitutions(array $filters): array
    {
        $records = TeacherSubstitution::query()->with('entry.subject', 'entry.grade', 'entry.section', 'absentTeacher', 'substituteTeacher')
            ->when($filters['teacher_id'] ?? null, fn ($query, $id) => $query->where(fn ($substitutions) => $substitutions->where('absent_teacher_id', $id)->orWhere('substitute_teacher_id', $id)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('substitution_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('substitution_date', '<=', $date))
            ->latest('substitution_date')->limit(1000)->get();

        return [['Date', 'Class', 'Period', 'Subject', 'Absent teacher', 'Substitute', 'Status'], $records->map(fn ($record) => [$record->substitution_date?->format('Y-m-d'), trim(($record->entry?->grade?->name ?? '').' '.($record->entry?->section?->name ?? '')), $record->entry?->period_number, $record->entry?->subject?->name, $record->absentTeacher?->full_name, $record->substituteTeacher?->full_name, str($record->status)->headline()])];
    }

    /** @return array{0:list<string>,1:Collection<int,array<int,string|int|float|null>>} */
    private function teacherWorkload(array $filters, WorkloadService $workload): array
    {
        $yearId = (int) ($filters['academic_year_id'] ?? AcademicYear::query()->where('status', 'open')->value('id'));
        $teachers = Teacher::query()->where('is_active', true)->when($filters['teacher_id'] ?? null, fn ($query, $id) => $query->whereKey($id))->orderBy('first_name')->get();
        $rows = $yearId ? $teachers->map(function (Teacher $teacher) use ($workload, $yearId): array {
            $summary = $workload->summary($teacher, $yearId);

            return [$teacher->full_name, $summary['weekly_periods'], $summary['daily_periods'], $summary['free_periods'], $summary['substitution_count']];
        }) : collect();

        return [['Teacher', 'Teaching periods / week', 'Teaching periods today', 'Free periods today', 'Substitutions this week'], $rows];
    }

    /** @return array{0:list<string>,1:Collection<int,array<int,string|int|float|null>>} */
    private function timetable(array $filters): array
    {
        $records = TimetableEntry::query()->with('teacher', 'subject', 'grade', 'section')
            ->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when($filters['teacher_id'] ?? null, fn ($query, $id) => $query->where('teacher_id', $id))
            ->when($filters['grade_id'] ?? null, fn ($query, $id) => $query->where('grade_id', $id))
            ->when($filters['section_id'] ?? null, fn ($query, $id) => $query->where('section_id', $id))
            ->when($filters['subject_id'] ?? null, fn ($query, $id) => $query->where('subject_id', $id))
            ->orderBy('day_of_week')->orderBy('period_number')->get();

        return [['Day', 'Period', 'Class', 'Subject', 'Teacher', 'Room'], $records->map(fn ($entry) => [str($entry->day_of_week)->headline(), $entry->period_number, trim(($entry->grade?->name ?? '').' '.($entry->section?->name ?? '')), $entry->subject?->name, $entry->teacher?->full_name, $entry->room])];
    }

    /** @return array{0:list<string>,1:Collection<int,array<int,string|int|float|null>>} */
    private function grades(array $filters): array
    {
        $records = GradeEntry::query()->with('student', 'assessment.assignment.teacher', 'assessment.assignment.subject', 'assessment.subject', 'teacher')
            ->when($filters['academic_term_id'] ?? null, fn ($query, $id) => $query->whereHas('assessment', fn ($assessment) => $assessment->where('academic_term_id', $id)))
            ->when($filters['grade_id'] ?? null, fn ($query, $id) => $query->whereHas('registration', fn ($registration) => $registration->where('grade_id', $id)))
            ->when($filters['section_id'] ?? null, fn ($query, $id) => $query->whereHas('registration', fn ($registration) => $registration->where('section_id', $id)))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereHas('assessment', fn ($assessment) => $assessment->whereDate('assessment_date', '>=', $date)))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereHas('assessment', fn ($assessment) => $assessment->whereDate('assessment_date', '<=', $date)))
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($filters['teacher_id'] ?? null, fn ($query, $id) => $query->where('teacher_id', $id))
            ->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->whereHas('assessment', fn ($assessments) => $assessments->where('academic_year_id', $id)))
            ->when($filters['subject_id'] ?? null, fn ($query, $id) => $query->whereHas('assessment', fn ($assessments) => $assessments->where('subject_id', $id)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()->limit(1000)->get();

        return [['Student', 'Assessment', 'Subject', 'Teacher', 'Score', 'Maximum score', 'Status', 'Locked'], $records->map(fn ($entry) => [$entry->student?->full_name, $entry->assessment?->name, $entry->assessment?->subject?->name, $entry->teacher?->full_name, $entry->score, $entry->assessment?->maximum_score, str($entry->status)->headline(), $entry->is_locked ? 'Yes' : 'No'])];
    }

    /** @return array{0:list<string>,1:Collection<int,array<int,string|int|float|null>>} */
    private function academicYears(array $filters): array
    {
        $records = AcademicYear::query()->with('terms')->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->whereKey($id))->orderByDesc('starts_on')->get();

        return [['Academic year', 'Starts', 'Ends', 'Status', 'Terms'], $records->map(fn ($year) => [$year->name, $year->starts_on?->format('Y-m-d'), $year->ends_on?->format('Y-m-d'), str($year->status)->headline(), $year->terms->map(fn ($term) => $term->name.' ('.str($term->status)->headline().')')->implode(', ')])];
    }

    /** @return array{0:list<string>,1:Collection<int,array<int,string|int|float|null>>} */
    private function classSections(array $filters): array
    {
        $records = StudentRegistration::query()->with('student', 'academicYear', 'grade', 'section', 'teacher')
            ->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when($filters['grade_id'] ?? null, fn ($query, $id) => $query->where('grade_id', $id))
            ->when($filters['section_id'] ?? null, fn ($query, $id) => $query->where('section_id', $id))
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('grade_id')->orderBy('section_id')->limit(1000)->get();

        return [['Academic year', 'Class', 'Student', 'Student number', 'Homeroom teacher', 'Registration status'], $records->map(fn ($registration) => [$registration->academicYear?->name, trim(($registration->grade?->name ?? '').' '.($registration->section?->name ?? '')), $registration->student?->full_name, $registration->student?->student_number, $registration->teacher?->full_name, str($registration->status)->headline()])];
    }
}
