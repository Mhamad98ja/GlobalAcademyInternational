<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportCardController extends Controller
{
    public function show(Request $request, string $report): View
    {
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'baccalaureate' => ['nullable', 'in:Lebanese,French,International'],
        ]);
        $years = AcademicYear::query()->orderByDesc('starts_on')->get();
        $grades = Grade::query()->with('sections')->orderBy('sort_order')->get();
        $yearId = $years->firstWhere('name', '2026-2027')?->id ?? $years->first()?->id;
        $field = static fn (string $type, string $name, string $label, array $extra = []): array => array_merge(compact('type', 'name', 'label'), $extra);
        $select = static fn (string $name, string $label, array $options = [], array $extra = []): array => array_merge($field('select', $name, $label, ['options' => $options]), $extra);
        $year = static fn (): array => $field('year', 'academic_year_id', 'Academic year', ['default' => $yearId]);
        $grade = static fn (string $label = 'Class'): array => $field('grade', 'grade_id', $label);
        $section = static fn (): array => $field('section', 'section_id', 'Section');
        $bac = static fn (): array => $select('baccalaureate', 'Baccalaureate', ['Lebanese' => 'Lebanese', 'French' => 'French', 'International' => 'International'], ['default' => 'Lebanese']);
        $search = static fn (string $name, string $label): array => $field('search', $name, $label);
        $check = static fn (string $name, string $label, bool $checked = false): array => $field('checkbox', $name, $label, compact('checked'));

        $definitions = [
            'report-card' => ['title' => 'Report Card', 'home' => true],
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

        return view('report-card.show', ['report' => $definitions[$report]['title'], 'reportKey' => $report, 'definition' => $definitions[$report], 'definitions' => $definitions, 'filters' => $filters, 'years' => $years, 'grades' => $grades]);
    }
}
