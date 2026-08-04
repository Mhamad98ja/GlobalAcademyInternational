<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolLevel;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicController extends Controller
{
    public function index(): View
    {
        return view('academic.index', [
            'years' => AcademicYear::orderByDesc('starts_on')->get(),
            'levels' => SchoolLevel::with('grades.sections')->orderBy('sort_order')->get(),
        ]);
    }

    public function storeYear(Request $request): RedirectResponse
    {
        $data = $this->yearData($request);
        DB::transaction(function () use ($data, $request): void {
            $this->closeOtherOpenYears($data['status']);
            AcademicYear::create($data + ['created_by' => $request->user()->id]);
        });

        return back()->with('success', 'Academic year created.');
    }

    public function updateYear(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $data = $this->yearData($request, $academicYear);
        DB::transaction(function () use ($data, $academicYear): void {
            $this->closeOtherOpenYears($data['status'], $academicYear);
            $academicYear->update($data);
        });

        return back()->with('success', 'Academic year updated.');
    }

    public function storeLevel(Request $request): RedirectResponse
    {
        SchoolLevel::create($this->levelData($request));

        return back()->with('success', 'Level created.');
    }

    public function updateLevel(Request $request, SchoolLevel $schoolLevel): RedirectResponse
    {
        $schoolLevel->update($this->levelData($request, $schoolLevel));

        return back()->with('success', 'Level updated.');
    }

    public function storeGrade(Request $request): RedirectResponse
    {
        Grade::create($this->gradeData($request));

        return back()->with('success', 'Grade created.');
    }

    public function updateGrade(Request $request, Grade $grade): RedirectResponse
    {
        $grade->update($this->gradeData($request));

        return back()->with('success', 'Grade updated.');
    }

    public function storeSection(Request $request): RedirectResponse
    {
        Section::create($this->sectionData($request));

        return back()->with('success', 'Section created.');
    }

    public function updateSection(Request $request, Section $section): RedirectResponse
    {
        $section->update($this->sectionData($request));

        return back()->with('success', 'Section updated.');
    }

    private function yearData(Request $request, ?AcademicYear $academicYear = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('academic_years', 'name')->ignore($academicYear)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'status' => ['required', 'in:draft,open,closed'],
        ]);
    }

    private function closeOtherOpenYears(string $status, ?AcademicYear $except = null): void
    {
        if ($status !== 'open') {
            return;
        }

        AcademicYear::query()
            ->where('status', 'open')
            ->when($except, fn ($query) => $query->where('id', '!=', $except->getKey()))
            ->update(['status' => 'closed']);
    }

    private function levelData(Request $request, ?SchoolLevel $schoolLevel = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('school_levels', 'name')->ignore($schoolLevel)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function gradeData(Request $request): array
    {
        return $request->validate([
            'school_level_id' => ['nullable', 'exists:school_levels,id'],
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function sectionData(Request $request): array
    {
        return $request->validate([
            'grade_id' => ['required', 'exists:grades,id'],
            'name' => ['required', 'string', 'max:100'],
            'room' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
