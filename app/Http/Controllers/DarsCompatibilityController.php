<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\ResponsiblePerson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DarsCompatibilityController extends Controller
{
    public function studentSearch(Request $request): RedirectResponse
    {
        $query = array_filter([
            'search' => trim(implode(' ', array_filter([$request->query('txtLastName'), $request->query('txtFirstName')]))),
            'academic_year_id' => $request->query('academic_year_id'),
        ], static fn ($value) => filled($value));

        return redirect()->route('students.index', $query);
    }

    public function studentProfile(Request $request): RedirectResponse
    {
        $student = Student::query()->findOrFail($request->integer('id'));

        return redirect()->route('students.show', [$student, 'tab' => 'general']);
    }

    public function responsibleProfile(Request $request): RedirectResponse
    {
        $person = ResponsiblePerson::query()->findOrFail($request->integer('id') ?: $request->integer('Id_Parent'));

        return redirect()->route('responsible.edit', $person);
    }

    public function studentSection(Request $request, string $section): RedirectResponse
    {
        $studentId = $request->integer('id') ?: $request->integer('IdSt');
        $routes = [
            'remarks' => ['students.show', [$studentId, 'tab' => 'remarks']],
            'evaluations' => ['students.show', [$studentId, 'tab' => 'evaluations']],
            'notes' => ['students.show', [$studentId, 'tab' => 'notes']],
            'subjects' => ['students.show', [$studentId, 'tab' => 'subjects']],
            'history' => ['students.show', [$studentId, 'tab' => 'history']],
            'schools' => ['students.show', [$studentId, 'tab' => 'schools']],
            'files' => ['students.show', [$studentId, 'tab' => 'files']],
            'professional' => ['collaboration.section', ['section' => 'performance', 'student_id' => $studentId]],
            'activities' => ['students.show', [$studentId, 'tab' => 'activities']],
            'medical' => ['students.show', [$studentId, 'tab' => 'medical']],
            'follow-up' => ['collaboration.section', ['section' => 'performance', 'student_id' => $studentId]],
        ];
        abort_unless(isset($routes[$section]), 404);

        [$route, $parameters] = $routes[$section];

        return redirect()->route($route, $parameters);
    }

    public function administrationPage(string $page): RedirectResponse
    {
        return redirect()->route('admin.dars.show', ['page' => $page]);
    }

    public function reportCard(string $report): RedirectResponse
    {
        return redirect()->route('report-card.show', ['report' => $report] + request()->query());
    }

    public function studentReport(Request $request): RedirectResponse
    {
        $report = [
            '2124' => 'class-list',
            '2125' => 'statistical',
            '2126' => 'labels',
            '2127' => 'catalog',
            '2128' => 'registration',
        ][(string) $request->query('pid')] ?? 'dynamic';

        return redirect()->route('students.reports.show', ['report' => $report]);
    }

    public function collaborationPage(string $section): RedirectResponse
    {
        if ($section === 'evaluation-grades') {
            return redirect()->route('grades.index', ['view' => 'evaluations'] + request()->query());
        }

        if ($section === 'grade-book') {
            return redirect()->route('grades.index', ['view' => 'grade-book'] + request()->query());
        }

        return redirect()->route('collaboration.section', ['section' => $section] + request()->query());
    }
}
