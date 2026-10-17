<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\SchoolLevel;
use Illuminate\View\View;

class AcademicController extends Controller
{
    public function index(): View
    {
        return view('academic.index', [
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'terms' => AcademicTerm::query()->with('academicYear')->orderByDesc('starts_on')->orderBy('sort_order')->get(),
            'levels' => SchoolLevel::query()->with('grades.sections')->orderBy('sort_order')->get(),
        ]);
    }
}
