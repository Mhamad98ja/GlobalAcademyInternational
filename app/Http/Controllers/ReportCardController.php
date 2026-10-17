<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AcademicTerm;
use App\Models\GradeEntry;
use App\Models\Grade;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportCardController extends Controller
{
    public function index(Request $request): View
    {
        return view('report-card.index', $this->generationData($request));
    }

    public function print(Request $request): View
    {
        return view('report-card.print', $this->generationData($request, true));
    }

    public function show(Request $request, string $report): View
    {
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'baccalaureate' => ['nullable', 'in:Lebanese,French,International'],
        ]);
        $years = AcademicYear::query()->orderByDesc('starts_on')->get();
        $grades = Grade::query()->with('sections')->orderBy('sort_order')->get();
        $yearId = $filters['academic_year_id'] ?? AcademicYear::query()->where('status', 'open')->orderByDesc('starts_on')->value('id') ?? $years->first()?->id;
        $terms = AcademicTerm::query()->where('academic_year_id', $yearId)->orderBy('sort_order')->orderBy('starts_on')->get();
        $activeTerm = AcademicTerm::activeForYear($yearId);
        $termId = $filters['academic_term_id'] ?? $activeTerm?->id ?? $terms->first()?->id;
        if ($termId) {
            abort_unless($terms->contains('id', (int) $termId), 422, 'The selected academic term does not belong to the selected academic year.');
        }
        $students = Student::query()
            ->with('registrations.grade', 'registrations.section')
            ->whereHas('registrations', function ($query) use ($yearId, $filters): void {
                $query->where('academic_year_id', $yearId)
                    ->where('status', 'active')
                    ->when($filters['grade_id'] ?? null, fn ($registration, $id) => $registration->where('grade_id', $id))
                    ->when($filters['section_id'] ?? null, fn ($registration, $id) => $registration->where('section_id', $id));
            })
            ->orderBy('last_name')->orderBy('first_name')->get();
        $reportCard = null;
        if ($report === 'report-card' && ! empty($filters['student_id']) && $termId) {
            $student = $students->firstWhere('id', (int) $filters['student_id']);
            abort_unless($student, 422, 'Select an active student in the selected class.');
            $entries = GradeEntry::query()
                ->with('assessment.term', 'assessment.assignment.subject', 'assessment.subject')
                ->where('student_id', $student->id)
                ->whereIn('status', ['published', 'locked'])
                ->whereHas('assessment', fn ($assessment) => $assessment
                    ->where('academic_year_id', $yearId)
                    ->where('academic_term_id', $termId))
                ->whereHas('registration', fn ($registration) => $registration->where('academic_year_id', $yearId)->where('status', 'active'))
                ->get()
                ->reject(fn (GradeEntry $entry): bool => $entry->registration?->subjectExemptions?->contains('subject_id', $entry->assessment?->subject_id ?? $entry->assessment?->assignment?->subject_id) ?? false);
            $subjects = $entries->groupBy(fn (GradeEntry $entry): string => (string) ($entry->assessment?->subject_id ?? $entry->assessment?->assignment?->subject_id ?? 'unknown'))
                ->map(function ($subjectEntries): array {
                    $weightTotal = $subjectEntries->sum(fn (GradeEntry $entry): float => max((float) ($entry->assessment?->weight ?? 1), 0.001));
                    $average = $weightTotal > 0
                        ? $subjectEntries->sum(fn (GradeEntry $entry): float => ((float) $entry->score / max((float) $entry->assessment?->maximum_score, 0.01)) * 100 * max((float) ($entry->assessment?->weight ?? 1), 0.001)) / $weightTotal
                        : 0;

                    return [
                        'name' => $subjectEntries->first()->assessment?->subject?->name ?? $subjectEntries->first()->assessment?->assignment?->subject?->name ?? 'Subject',
                        'average' => round($average, 2),
                        'entries' => $subjectEntries->sortBy(fn (GradeEntry $entry) => $entry->assessment?->assessment_date)->values(),
                    ];
                })->sortBy('name')->values();
            $reportCard = compact('student', 'subjects', 'termId', 'yearId');
        }
        $field = static fn (string $type, string $name, string $label, array $extra = []): array => array_merge(compact('type', 'name', 'label'), $extra);
        $select = static fn (string $name, string $label, array $options = [], array $extra = []): array => array_merge($field('select', $name, $label, ['options' => $options]), $extra);
        $year = static fn (): array => $field('year', 'academic_year_id', 'Academic year', ['default' => $yearId]);
        $grade = static fn (string $label = 'Class'): array => $field('grade', 'grade_id', $label);
        $section = static fn (): array => $field('section', 'section_id', 'Section');
        $bac = static fn (): array => $select('baccalaureate', 'Baccalaureate', ['Lebanese' => 'Lebanese', 'French' => 'French', 'International' => 'International'], ['default' => 'Lebanese']);
        $search = static fn (string $name, string $label): array => $field('search', $name, $label);
        $check = static fn (string $name, string $label, bool $checked = false): array => $field('checkbox', $name, $label, compact('checked'));

        $definitions = [
            'report-card' => ['title' => 'Official Report Card', 'fields' => [$year(), $field('term', 'academic_term_id', 'Academic term', ['default' => $termId]), $grade(), $section(), $select('student_id', 'Student', $students->mapWithKeys(fn (Student $student): array => [$student->id => $student->full_name])->all())], 'button' => 'Generate report card'],
            'individual-transcript' => ['title' => 'Individual Transcript', 'fields' => [$year(), $search('last_name', 'Last name'), $search('first_name', 'First name'), $grade(), $section()], 'button' => 'Search', 'empty' => 'No students found.'],
            'remarks-summary' => ['title' => 'Remarks Summary', 'fields' => [$year(), $grade(), $select('remark_type', 'Remark type'), $select('language', 'Language', ['en' => 'English'], ['default' => 'en'])], 'button' => 'Search', 'loading' => true],
            'general-transcript' => ['title' => 'General Transcript', 'fields' => [$year(), $grade(), $section(), $check('previous_average', 'Previous-year average'), $check('show_bac', 'Show baccalaureate'), $check('highest_lowest', 'Highest and lowest grade'), $select('column', 'Column'), $bac(), $check('a4', 'A4')], 'button' => 'Search'],
            'remarks-detail' => ['title' => 'Remarks', 'fields' => [$year(), $grade(), $section(), $select('column', 'Column'), $select('remark_type', 'Remark type'), $search('subject', 'Subject')], 'button' => 'Search'],
            'progress-transcript' => ['title' => 'Progress Transcript', 'fields' => [$year(), $grade(), $section(), $select('color', 'Choose a color', ['blue' => 'Blue', 'red' => 'Red']), $select('column', 'Column'), $select('compare_column', 'Compare with'), $bac()], 'button' => 'Search'],
            'subjects-list-per-class' => ['title' => 'Subjects List per Class', 'fields' => [$year(), $grade(), $section(), $bac(), $check('previous_average', 'Previous-year average'), $check('show_bac', 'Show baccalaureate'), $search('class_search', 'Class')], 'button' => 'Search'],
            'transcript-by-subject-choice' => ['title' => 'Transcript by Subject Choice', 'fields' => [$year(), $grade(), $section(), $bac(), $select('language', 'Language', ['en' => 'English'], ['default' => 'en']), $check('previous_average', 'Previous-year average'), $check('show_bac', 'Show baccalaureate'), $search('subject', 'Subject'), $search('column', 'Column')], 'button' => 'Search'],
            'student-ranking-per-subject' => ['title' => 'Student Ranking per Subject', 'fields' => [$year(), $grade(), $section(), $search('subject', 'Subject'), $select('column', 'Column'), $bac()], 'button' => 'Search'],
            'subjects-transcript' => ['title' => 'Subjects Transcript', 'fields' => [$year(), $grade(), $section(), $bac(), $search('subject', 'Subject'), $select('format', 'Format', ['landscape' => 'Landscape'], ['default' => 'landscape']), $check('previous_average', 'Previous-year average'), $check('show_bac', 'Show baccalaureate')], 'button' => 'Search'],
            'official-statement' => ['title' => 'Official Statement', 'fields' => [$year(), $select('division', 'Division'), $select('gender', 'Gender'), $select('department', 'Department'), $grade(), $bac(), $check('with_class_section', 'Include class and section', true), $search('search_one', ''), $search('search_two', ''), $search('search_three', '')], 'button' => 'Search', 'viewer' => true],
            'official-statement-mehe' => ['title' => 'Official Statement (MEHE)', 'fields' => [$year(), $grade(), $section(), $select('curriculum', 'Curriculum', ['Lebanese' => 'Lebanese'], ['default' => 'Lebanese']), $field('number', 'marks', 'Marks out of', ['default' => '20'])], 'button' => 'OK', 'viewer' => true],
            'student-performance' => ['title' => 'Student Performance', 'fields' => [$year(), $grade(), $section(), $select('column', 'Column'), $bac(), $field('configure', 'configure', 'Configure'), $field('ok', 'ok', 'OK'), $check('highest_lowest', 'Highest and lowest grade')]],
            'subject-averages-per-class' => ['title' => 'Subject Averages per Class', 'fields' => [$year(), $grade(), $bac()], 'button' => 'OK', 'viewer' => true],
            'failure-per-subject' => ['title' => 'Failure per Subject', 'fields' => [$year(), $select('division', 'Division'), $search('average', 'Average'), $select('column', 'Column')], 'button' => 'OK'],
            'underachievers-by-subject' => ['title' => 'Underachievers by Subject', 'fields' => [$select('division', 'Division'), $select('mf', 'MF'), $select('department', 'Department'), $select('column_from', 'From column'), $select('column_to', 'To column'), $bac(), $grade(), $search('class_search', 'Class'), $check('hide_brothers', 'Hide siblings', true), $search('subject', 'Subject'), $select('operator', 'Average', ['lt' => '<', 'gt' => '>']), $field('number', 'threshold', '', ['default' => ''])], 'button' => 'OK'],
            'annual-progression-of-class-average' => ['title' => 'Annual Progression of Class Average', 'fields' => [$year(), $grade(), $section(), $select('year_from', 'Start year'), $select('year_to', 'End year')], 'button' => 'OK'],
            'top-5-students-per-division' => ['title' => 'Top 5 Students per Division', 'fields' => [$year(), $select('division', 'Division'), $select('column', 'Column'), $bac(), $select('mf', 'MF'), $select('department', 'Department'), $grade(), $section(), $select('language', 'Language', ['en' => 'English'], ['default' => 'en']), $field('number', 'top', 'Top', ['default' => '5']), $select('scope', '', ['section' => 'Per section'], ['default' => 'section'])], 'button' => 'OK'],
            'highest-grade-per-subject' => ['title' => 'Highest Grade per Subject', 'fields' => [$year(), $grade(), $section(), $select('column', 'Column'), $bac(), $select('language', 'Language', ['en' => 'English'], ['default' => 'en']), $check('lowest_class', 'Show lowest-performing class')], 'button' => 'OK'],
            'export-bacc-francais' => ['title' => 'Export French Baccalaureate', 'fields' => [$year(), $grade()], 'button' => 'Search'],
            'best-annual-columns-transcript' => ['title' => 'Best Annual Columns Transcript', 'fields' => [$year(), $grade()], 'button' => 'OK'],
            'students-transcript' => ['title' => 'Students Transcript', 'statistical' => true, 'fields' => [$year(), $select('division', 'Division'), $select('gender', 'Gender'), $grade(), $bac(), $search('class_one', 'Class'), $search('class_two', ''), $search('subject', 'Subject')], 'button' => 'OK', 'viewer' => true],
            'grade-distribution' => ['title' => 'Grade Distribution', 'statistical' => true, 'fields' => [$year(), $grade(), $section(), $select('column', 'Column'), $select('compare_column', 'Column to compare'), $bac(), $search('subject', 'Subject')], 'button' => 'OK', 'viewer' => true],
            'class-average-evolution' => ['title' => 'Class Average Evolution', 'statistical' => true, 'fields' => [$year(), $grade(), $section(), $bac(), $select('term', '', ['ordinary' => 'Regular'], ['default' => 'ordinary']), $search('subject', 'Subject'), $search('column', 'Column')], 'button' => 'OK'],
            'statistical-calculation' => ['title' => 'Statistical Calculation', 'statistical' => true, 'fields' => [$year(), $grade(), $bac(), $field('heading', 'margins', 'Margins'), $search('section', 'Section'), $search('column', 'Column')], 'button' => 'OK'],
            'results-comparison-table' => ['title' => 'Results Comparison Table', 'statistical' => true, 'fields' => [$year(), $select('division', 'Division'), $select('column', 'Column'), $select('year_from', 'From year'), $select('year_to', 'To year'), $search('class', 'Class'), $search('subject', 'Subject')], 'button' => 'OK', 'viewer' => true],
        ];
        abort_unless(isset($definitions[$report]), 404);

        return view('report-card.show', ['report' => $definitions[$report]['title'], 'reportKey' => $report, 'definition' => $definitions[$report], 'definitions' => $definitions, 'filters' => $filters, 'years' => $years, 'grades' => $grades, 'terms' => $terms, 'students' => $students, 'reportCard' => $reportCard]);
    }

    /** @return array<string, mixed> */
    private function generationData(Request $request, bool $forPrint = false): array
    {
        $filters = $request->validate([
            'period' => ['nullable', Rule::in(['annual', 'partial'])],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
        ]);
        $period = $filters['period'] ?? 'partial';
        $years = AcademicYear::query()->orderByDesc('starts_on')->get();
        $yearId = (int) ($filters['academic_year_id'] ?? AcademicYear::query()->where('status', 'open')->orderByDesc('starts_on')->value('id') ?? $years->first()?->id ?? 0);
        $year = $years->firstWhere('id', $yearId);
        $terms = AcademicTerm::query()->where('academic_year_id', $yearId)->orderBy('sort_order')->orderBy('starts_on')->get();
        $allTerms = AcademicTerm::query()->whereIn('academic_year_id', $years->pluck('id'))->orderBy('academic_year_id')->orderBy('sort_order')->orderBy('starts_on')->get();
        $activeTerm = AcademicTerm::activeForYear($yearId);
        $termId = $period === 'partial' ? (int) ($filters['academic_term_id'] ?? $activeTerm?->id ?? $terms->first()?->id ?? 0) : null;
        if ($termId) {
            abort_unless($terms->contains('id', $termId), 422, 'The selected academic term does not belong to the selected academic year.');
        }
        $periodTerms = $period === 'annual'
            ? $terms
            : $terms->filter(fn (AcademicTerm $term): bool => (int) $term->id === $termId)->values();
        $registrations = StudentRegistration::query()
            ->with(['student', 'grade', 'section'])
            ->where('academic_year_id', $yearId)
            ->where('status', 'active')
            ->when($filters['grade_id'] ?? null, fn ($query, $id) => $query->where('grade_id', $id))
            ->when($filters['section_id'] ?? null, fn ($query, $id) => $query->where('section_id', $id))
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->orderBy('grade_id')->orderBy('section_id')->orderBy('student_id')
            ->get();
        $generated = $forPrint || $request->routeIs('admin.report-cards.generate') || $request->boolean('generate');
        $entries = collect();
        if ($generated && $registrations->isNotEmpty()) {
            $entries = GradeEntry::query()
                ->with(['assessment.term', 'assessment.subject', 'assessment.assignment.subject', 'assessment.assignment.teacher', 'teacher', 'registration.subjectExemptions'])
                ->whereIn('student_registration_id', $registrations->pluck('id'))
                ->whereIn('status', ['published', 'locked'])
                ->whereHas('assessment', function ($assessment) use ($yearId, $period, $termId): void {
                    $assessment->where('academic_year_id', $yearId)
                        ->when($period === 'partial', fn ($query) => $query->where('academic_term_id', $termId));
                })
                ->orderBy('assessment_id')
                ->get()
                ->reject(fn (GradeEntry $entry): bool => $entry->registration?->subjectExemptions?->contains('subject_id', $entry->assessment?->subject_id ?? $entry->assessment?->assignment?->subject_id) ?? false)
                ->values();
        }
        $cards = $registrations->map(function (StudentRegistration $registration) use ($entries, $periodTerms): array {
            $studentEntries = $entries->where('student_registration_id', $registration->id)->values();
            $subjects = $studentEntries->groupBy(fn (GradeEntry $entry): string => (string) ($entry->assessment?->subject_id ?? $entry->assessment?->assignment?->subject_id ?? 'unknown'))
                ->map(function (Collection $subjectEntries) use ($periodTerms): array {
                    $first = $subjectEntries->first();
                    $termAverages = $periodTerms->mapWithKeys(fn (AcademicTerm $term): array => [(string) $term->id => $this->weightedAverage($subjectEntries->filter(fn (GradeEntry $entry): bool => (int) $entry->assessment?->academic_term_id === (int) $term->id))])->all();
                    return [
                        'name' => $first?->assessment?->subject?->name ?? $first?->assessment?->assignment?->subject?->name ?? 'Subject',
                        'earned' => round($subjectEntries->sum(fn (GradeEntry $entry): float => (float) $entry->score), 2),
                        'maximum' => round($subjectEntries->sum(fn (GradeEntry $entry): float => (float) ($entry->assessment?->maximum_score ?? 0)), 2),
                        'average' => $this->weightedAverage($subjectEntries),
                        'termAverages' => $termAverages,
                        'assessmentCount' => $subjectEntries->count(),
                        'teachers' => $subjectEntries->map(fn (GradeEntry $entry): ?string => $entry->teacher?->full_name ?? $entry->assessment?->assignment?->teacher?->full_name)->filter()->unique()->implode(', '),
                        'entries' => $subjectEntries->sortBy(fn (GradeEntry $entry) => $entry->assessment?->assessment_date)->values(),
                    ];
                })->sortBy('name')->values();
            return [
                'registration' => $registration,
                'subjects' => $subjects,
                'overallAverage' => $this->weightedAverage($studentEntries),
                'hasGrades' => $subjects->isNotEmpty(),
            ];
        })->filter(fn (array $card): bool => $card['hasGrades'])->values();

        return [
            'years' => $years,
            'grades' => Grade::query()->with('sections')->orderBy('sort_order')->get(),
            'terms' => $terms,
            'allTerms' => $allTerms,
            'periodTerms' => $periodTerms,
            'registrations' => $registrations,
            'cards' => $cards,
            'school' => SystemSetting::query()->whereIn('key', ['school_name', 'school_address', 'school_phone'])->pluck('value', 'key'),
            'year' => $year,
            'period' => $period,
            'termId' => $termId,
            'generated' => $generated,
            'forPrint' => $forPrint,
            'filters' => $filters,
        ];
    }

    private function weightedAverage(Collection $entries): ?float
    {
        if ($entries->isEmpty()) {
            return null;
        }

        $weightTotal = $entries->sum(fn (GradeEntry $entry): float => max((float) ($entry->assessment?->weight ?? 1), 0.001));
        if ($weightTotal <= 0) {
            return null;
        }

        return round($entries->sum(fn (GradeEntry $entry): float => ((float) $entry->score / max((float) ($entry->assessment?->maximum_score ?? 0), 0.01)) * 100 * max((float) ($entry->assessment?->weight ?? 1), 0.001)) / $weightTotal, 2);
    }
}
