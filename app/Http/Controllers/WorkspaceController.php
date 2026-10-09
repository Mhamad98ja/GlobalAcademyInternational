<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Book;
use App\Models\BookDistribution;
use App\Models\ExamAbsence;
use App\Models\Grade;
use App\Models\GradeEntry;
use App\Models\ResponsiblePerson;
use App\Models\StockMovement;
use App\Models\StudentAttendance;
use App\Models\StudentIncident;
use App\Models\StudentRegistration;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\TeacherAttendance;
use App\Models\TeacherSubstitution;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Entry points for the four operational areas shown on the dashboard.
 *
 * These deliberately compose the existing SBM modules rather than creating
 * parallel student, teacher, academic, or book workflows.
 */
class WorkspaceController extends Controller
{
    public function __invoke(Request $request, string $workspace): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $activeAcademicYear = AcademicYear::query()
            ->where('status', 'open')
            ->orderByDesc('starts_on')
            ->first();
        if ($workspace === 'students' && $request->filled('academic_year_id')) {
            $selection = $request->validate(['academic_year_id' => ['required', 'integer', 'exists:academic_years,id']]);
            $activeAcademicYear = AcademicYear::query()->findOrFail($selection['academic_year_id']);
        }

        $page = match ($workspace) {
            'students' => $this->students($user, $activeAcademicYear),
            'teachers' => $this->teachers($user, $activeAcademicYear),
            'academic' => $this->academic($user, $activeAcademicYear),
            'operations' => $this->operations($user, $activeAcademicYear),
        };

        if ($workspace === 'students' && $user->hasPermission('students.view')) {
            $page['studentOverview'] = $this->studentOverview($user, $activeAcademicYear);
        }

        return view('workspaces.show', $page + compact('activeAcademicYear', 'workspace'));
    }

    /** @return array<string, mixed> */
    private function studentOverview(User $user, ?AcademicYear $year): array
    {
        $registrations = StudentRegistration::query()->with('student')->whereHas('student')->where('academic_year_id', $year?->id ?? 0)->get();
        $active = $registrations->where('status', 'active');
        $families = ResponsiblePerson::query()->withCount(['students' => fn ($query) => $query->whereIn('students.id', $active->pluck('student_id'))])
            ->get()->where('students_count', '>', 0);
        $familySizes = $families->groupBy('students_count')->map->count();
        $gender = ['female' => 0, 'male' => 0, 'unspecified' => 0];
        foreach ($active as $registration) {
            $gender[$registration->student->gender ?: 'unspecified']++;
        }
        $remarks = null;
        $absences = null;
        if ($user->hasPermission('attendance.student.manage')) {
            $remarkStatuses = [
                'Absence' => 'absent',
                'Authorised leave' => 'excused',
                'Late' => 'late',
            ];
            $remarks = collect($remarkStatuses)->map(function (string $status) use ($year): array {
                $base = StudentAttendance::query()
                    ->where('academic_year_id', $year?->id ?? 0)
                    ->where('status', $status)
                    ->where('period_number', 0);

                return [
                    'Today' => (clone $base)->whereDate('attendance_date', today())->count(),
                    'Week' => (clone $base)->whereBetween('attendance_date', [today()->copy()->startOfWeek()->toDateString(), today()->toDateString()])->count(),
                    'Month' => (clone $base)->whereBetween('attendance_date', [today()->copy()->startOfMonth()->toDateString(), today()->toDateString()])->count(),
                ];
            });
            $absences = $remarks->get('Absence');
        }

        return [
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'familySizes' => $familySizes,
            'totalFamilies' => $families->count(),
            'totalStudents' => $active->count(),
            'gender' => $gender,
            'absences' => $absences,
            'remarks' => $remarks,
            'classes' => Grade::query()->orderBy('sort_order')->get()->map(fn ($grade) => [
                'name' => $grade->name,
                'active' => $active->where('grade_id', $grade->id)->count(),
                'other' => $registrations->where('grade_id', $grade->id)->where('status', '!=', 'active')->count(),
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private function students(User $user, ?AcademicYear $year): array
    {
        $yearId = $year?->id;
        $activeStudents = $user->hasPermission('students.view') && $yearId
            ? StudentRegistration::query()->where('academic_year_id', $yearId)->where('status', 'active')->count()
            : 0;
        $attendanceToday = $user->hasPermission('attendance.student.manage') && $yearId
            ? StudentAttendance::query()->where('academic_year_id', $yearId)->whereDate('attendance_date', today())->count()
            : 0;
        $incidents = $user->hasPermission('discipline.view') && $yearId
            ? StudentIncident::query()->where('academic_year_id', $yearId)->count()
            : 0;
        $makeups = $user->hasPermission('academic_year.manage') && $yearId
            ? ExamAbsence::query()->where('academic_year_id', $yearId)->where('makeup_required', true)->count()
            : 0;

        return $this->page(
            $user,
            'Student dossier',
            'student',
            'students',
            'Manage the student journey: enrolment, family contacts, daily attendance, actions, health records, and exam follow-up.',
            [
                $this->stat($user, 'students.view', 'Active students', $activeStudents, $year ? 'Registered in '.$year->name : 'Set an open academic year'),
                $this->stat($user, 'attendance.student.manage', 'Attendance today', $attendanceToday, 'Daily records entered'),
                $this->stat($user, 'discipline.view', 'Student actions', $incidents, 'Recorded this academic year'),
                $this->stat($user, 'academic_year.manage', 'Make-up follow-ups', $makeups, 'Exam absences requiring attention'),
            ],
            [
                $this->task('students.view', 'Student directory', 'Search profiles, registrations, and student history.', 'students.index', 'students', 'Open students'),
                $this->task('students.view', 'File', 'Open student files, families, photos, and section changes.', 'students.tools', 'students', 'Open file', ['section' => 'file']),
                $this->task('students.view', 'References', 'Maintain years, classes, sections, subjects, terms, and action references.', 'students.tools', 'academic', 'Open references', ['section' => 'references']),
                $this->task('reports.view', 'Attestations', 'Select students and print enrolment or attendance attestations.', 'students.attestations', 'reports', 'Prepare attestations'),
                $this->task('reports.view', 'Reports', 'Open student, class, attendance, action, medical, exam, and grade reports from one organised report centre.', 'reports.school', 'reports', 'Open report centre', ['report' => 'student_attendance']),
                $this->task('people.manage', 'Responsible persons', 'Link family and guardian contacts to student records.', 'responsible.index', 'responsible', 'Open contacts'),
                $this->task('attendance.student.manage', 'Daily attendance', 'Record class attendance and review absences.', 'attendance.students', 'students', 'Take attendance'),
                $this->task('discipline.view', 'Student actions', 'Document incidents, positive actions, and follow-up.', 'discipline.index', 'students', 'Open actions'),
                $this->task('medical.view', 'Medical archive', 'Review protected student health records and documents.', 'medical.index', 'responsible', 'Open archive'),
                $this->task('academic_year.manage', 'Exam absences', 'Track excused absences and required make-up exams.', 'exam-absences.index', 'academic', 'Review absences'),
            ],
            [
                $this->activity('attendance.student.manage', 'Attendance work', $attendanceToday ? number_format($attendanceToday).' attendance records entered today.' : 'No student attendance has been entered today.', 'attendance.students', 'students'),
                $this->activity('discipline.view', 'Student actions', $incidents ? number_format($incidents).' actions are in this academic-year archive.' : 'No student actions are recorded in this academic year.', 'discipline.index', 'students'),
                $this->activity('academic_year.manage', 'Exam follow-up', $makeups ? number_format($makeups).' make-up exam follow-ups require review.' : 'No make-up exam follow-ups are pending.', 'exam-absences.index', 'academic'),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function teachers(User $user, ?AcademicYear $year): array
    {
        $yearId = $year?->id;
        $activeTeachers = $user->hasPermission('people.manage') ? Teacher::query()->where('is_active', true)->count() : 0;
        $checkIns = $user->hasPermission('attendance.teacher.manage')
            ? TeacherAttendance::query()->whereDate('attendance_date', today())->count()
            : 0;
        $absences = $user->hasPermission('attendance.teacher.manage')
            ? TeacherAttendance::query()->whereDate('attendance_date', today())->whereIn('status', TeacherAttendance::ABSENCE_STATUSES)->count()
            : 0;
        $substitutions = $user->hasPermission('substitutions.manage')
            ? TeacherSubstitution::query()->whereDate('substitution_date', today())->count()
            : 0;
        $assignments = $user->hasPermission('academic_year.manage') && $yearId
            ? TeacherAssignment::query()->where('academic_year_id', $yearId)->where('is_active', true)->count()
            : 0;

        return $this->page(
            $user,
            'Teacher affairs',
            'teacher',
            'teachers',
            'Run the staff day: teacher profiles, IN / OUT attendance, subject assignments, affected lessons, and fair substitute recommendations.',
            [
                $this->stat($user, 'people.manage', 'Active teachers', $activeTeachers, 'Available teacher profiles'),
                $this->stat($user, 'attendance.teacher.manage', 'Attendance today', $checkIns, 'IN / OUT records saved'),
                $this->stat($user, 'attendance.teacher.manage', 'Absences today', $absences, 'Excused or unexcused'),
                $this->stat($user, 'substitutions.manage', 'Substitution cases', $substitutions, 'Affected lessons today'),
            ],
            [
                $this->task('people.manage', 'Teacher directory', 'Open teacher profiles, assignments, workloads, and history.', 'teachers.index', 'teachers', 'Open teachers'),
                $this->task('attendance.teacher.manage', 'Teacher IN / OUT', 'Record check-ins, check-outs, absences, and late arrivals.', 'attendance.teachers', 'teachers', 'Record attendance'),
                $this->task('substitutions.manage', 'Substitution desk', 'Review affected lessons and assign fair substitutes.', 'substitutions.index', 'teachers', 'Manage substitutions'),
                $this->task('academic_year.manage', 'Teacher subject assignments', $assignments ? number_format($assignments).' active subject assignments connect teachers, classes, timetable, and workload.' : 'Set up the subjects each teacher teaches in each class.', 'school-academic.index', 'academic', 'Open assignments'),
                $this->task('collaboration.view', 'Collaboration & class life', 'Publish subject agenda items, notices, calendar events, and student performance follow-up.', 'collaboration.index', 'responsible', 'Open collaboration'),
            ],
            [
                $this->activity('attendance.teacher.manage', 'Teacher attendance', $checkIns ? number_format($checkIns).' teacher attendance records have been saved today.' : 'No teacher attendance has been saved today.', 'attendance.teachers', 'teachers'),
                $this->activity('attendance.teacher.manage', 'Absence handling', $absences ? number_format($absences).' absences can affect scheduled lessons.' : 'No teacher absences have been recorded today.', 'attendance.teachers', 'teachers'),
                $this->activity('substitutions.manage', 'Lesson coverage', $substitutions ? number_format($substitutions).' substitute-lesson cases are available today.' : 'No substitute lessons are needed today.', 'substitutions.index', 'teachers'),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function academic(User $user, ?AcademicYear $year): array
    {
        $yearId = $year?->id;
        $terms = $user->hasPermission('academic_year.manage') && $yearId ? AcademicTerm::query()->where('academic_year_id', $yearId)->count() : 0;
        $assessments = $user->hasPermission('academic_year.manage') && $yearId ? Assessment::query()->where('academic_year_id', $yearId)->count() : 0;
        $assignments = $user->hasPermission('academic_year.manage') && $yearId ? TeacherAssignment::query()->where('academic_year_id', $yearId)->where('is_active', true)->count() : 0;
        $timetableEntries = $user->hasPermission('timetable.view') && $yearId ? TimetableEntry::query()->where('academic_year_id', $yearId)->count() : 0;
        $gradeEntries = $user->hasPermission('grades.enter') && $yearId
            ? GradeEntry::query()->whereHas('assessment', fn ($query) => $query->where('academic_year_id', $yearId))->count()
            : 0;

        return $this->page(
            $user,
            'Academic affairs',
            'academic',
            'academic',
            'Operate the learning programme: academic structure, terms and assessments, teacher assignments, grades, and the graphical timetable.',
            [
                $this->stat($user, 'academic_year.manage', 'Terms', $terms, 'Configured for the active year'),
                $this->stat($user, 'academic_year.manage', 'Assessments', $assessments, 'Tests, exams, projects, and more'),
                $this->stat($user, 'academic_year.manage', 'Subject assignments', $assignments, 'Active teacher-subject links'),
                $this->stat($user, 'timetable.view', 'Scheduled lessons', $timetableEntries, 'Timetable entries in the active year'),
            ],
            [
                $this->task('academic.manage', 'Academic structure', 'Manage academic years, levels, grades, and sections.', 'academic.index', 'academic', 'Open structure'),
                $this->task('academic_year.manage', 'Academic operations', 'Configure subjects, terms, assessments, and teacher assignments.', 'school-academic.index', 'academic', 'Open operations'),
                $this->task('grades.enter', 'Grade entry', $gradeEntries ? number_format($gradeEntries).' grade records are available in your permitted assignments.' : 'Enter marks only for your permitted teacher assignments.', 'grades.index', 'academic', 'Enter grades'),
                $this->task('timetable.view', 'Graphical timetable', 'Build class schedules and prevent teacher conflicts.', 'timetable.index', 'academic', 'Open timetable'),
            ],
            [
                $this->activity('academic_year.manage', 'Assessment configuration', $assessments ? number_format($assessments).' assessments are configured for the active academic year.' : 'Set up terms and assessments for the active academic year.', 'school-academic.index', 'academic'),
                $this->activity('grades.enter', 'Grade work', $gradeEntries ? number_format($gradeEntries).' grade entries are saved in permitted assignments.' : 'No grade entries are saved in your permitted assignments yet.', 'grades.index', 'academic'),
                $this->activity('timetable.view', 'Timetable work', $timetableEntries ? number_format($timetableEntries).' lessons are currently placed in the timetable.' : 'No lessons are placed in the timetable yet.', 'timetable.index', 'academic'),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function operations(User $user, ?AcademicYear $year): array
    {
        $yearId = $year?->id;
        $canSeeStock = $user->hasPermission('books.view') || $user->hasPermission('inventory.view');
        $books = $canSeeStock
            ? Book::query()->active()->withBranchStock()->knownInBranch()->get()
            : collect();
        $stock = $books->sum('branch_quantity');
        $lowStock = $books->filter(fn (Book $book) => $book->branch_quantity <= $book->minimum_stock)->count();
        $distributionsToday = $user->hasPermission('distributions.create') && $yearId
            ? BookDistribution::query()->where('academic_year_id', $yearId)->whereDate('distributed_on', today())->count()
            : 0;
        $movements = $user->hasPermission('inventory.view') && $yearId
            ? StockMovement::query()->where('academic_year_id', $yearId)->count()
            : 0;

        return $this->page(
            $user,
            'Operations, books & reports',
            'operations',
            'operations',
            'Keep books moving from catalogue to student: packages, barcode distribution, stock control, suppliers, transfers, finance, and reports.',
            [
                $this->stat($user, 'books.view', 'Books in stock', $stock, 'Available in this branch'),
                $this->stat($user, 'inventory.view', 'Low-stock titles', $lowStock, 'At or below their reorder level'),
                $this->stat($user, 'distributions.create', 'Distributed today', $distributionsToday, 'Student book distributions completed'),
                $this->stat($user, 'inventory.view', 'Stock movements', $movements, 'Posted in the active academic year'),
            ],
            [
                $this->task('distributions.create', 'Distribute books', 'Issue a package or individual books to an active student using barcode scanning.', 'distributions.create', 'distribution', 'Start distribution'),
                $this->task('distributions.create', 'Distribution history', 'Find completed distributions by student, class, year, or date.', 'distributions.index', 'distribution', 'View history'),
                $this->task('books.view', 'Book catalogue', 'Manage titles, shelves, stock levels, and book details.', 'books.index', 'books', 'Open catalogue'),
                $this->task('class-lists.view', 'Book packages', 'Prepare the book sets used during student distribution.', 'class-lists.index', 'books', 'Open packages'),
                $this->task('inventory.view', 'Inventory ledger', 'Review all controlled stock movements and balances.', 'inventory.ledger', 'inventory', 'Open ledger'),
                $this->task('suppliers.view', 'Suppliers', 'Manage supplier records and the purchasing workflow.', 'suppliers.index', 'suppliers', 'Open suppliers'),
                $this->task('inventory.transfer', 'Book transfers', 'Move controlled stock between branches or custodians.', 'transfers.index', 'inventory', 'Manage transfers'),
                $this->task('reports.view', 'School reports', 'Review operational, academic, and distribution reports.', 'reports.school', 'reports', 'Open reports'),
            ],
            [
                $this->activity('distributions.create', 'Book distribution', $distributionsToday ? number_format($distributionsToday).' student book distributions have been completed today.' : 'No student book distributions have been completed today.', 'distributions.index', 'distribution'),
                $this->activity('inventory.view', 'Stock control', $lowStock ? number_format($lowStock).' titles are at or below their reorder level.' : 'No titles are currently at or below their reorder level.', 'inventory.ledger', 'inventory'),
                $this->activity('inventory.view', 'Inventory activity', $movements ? number_format($movements).' stock movements are posted for the active academic year.' : 'No stock movements are posted for the active academic year.', 'inventory.ledger', 'inventory'),
            ],
        );
    }

    /** @param array<int, array<string, mixed>|null> $stats @param array<int, array<string, mixed>> $tasks @param array<int, array<string, mixed>> $activities @return array<string, mixed> */
    private function page(User $user, string $title, string $tone, string $icon, string $description, array $stats, array $tasks, array $activities): array
    {
        return [
            'title' => $title,
            'tone' => $tone,
            'icon' => $icon,
            'description' => $description,
            'stats' => collect($stats)->filter()->values(),
            'tasks' => $this->permitted($user, $tasks),
            'activities' => $this->permitted($user, $activities),
        ];
    }

    /** @return array<string, mixed>|null */
    private function stat(User $user, string $permission, string $label, int|float $value, string $note): ?array
    {
        return $user->hasPermission($permission) ? compact('label', 'value', 'note') : null;
    }

    /** @return array<string, mixed> */
    private function task(string $permission, string $title, string $description, string $route, string $icon, string $action, array $params = []): array
    {
        return compact('permission', 'title', 'description', 'route', 'icon', 'action', 'params');
    }

    /** @return array<string, mixed> */
    private function activity(string $permission, string $title, string $description, string $route, string $icon): array
    {
        return compact('permission', 'title', 'description', 'route', 'icon');
    }

    /** @param array<int, array<string, mixed>> $items */
    private function permitted(User $user, array $items): Collection
    {
        return collect($items)
            ->filter(fn (array $item) => $user->hasPermission($item['permission']))
            ->values();
    }
}
