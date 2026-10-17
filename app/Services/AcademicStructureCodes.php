<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolLevel;
use App\Models\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AcademicStructureCodes
{
    public const TYPES = [
        'academic-year' => 'Academic Years',
        'term-semester' => 'Terms / Semesters',
        'level' => 'Levels',
        'grade' => 'Grades',
        'section' => 'Sections',
    ];

    public function rows(string $type): Collection
    {
        return match ($type) {
            'academic-year' => AcademicYear::query()->orderByDesc('starts_on')->get()->map(fn ($year) => $this->row($type, $year, [$year->name, $year->starts_on->toDateString().' – '.$year->ends_on->toDateString(), ucfirst($year->status), ''])),
            'term-semester' => AcademicTerm::query()->with('academicYear')->orderByDesc('starts_on')->orderBy('sort_order')->get()->map(fn ($term) => $this->row($type, $term, [$term->name, $term->academicYear?->name.' · '.$term->type, $term->starts_on->toDateString().' – '.$term->ends_on->toDateString(), ucfirst($term->status)])),
            'level' => SchoolLevel::query()->orderBy('sort_order')->get()->map(fn ($level) => $this->row($type, $level, [$level->name, 'Level', $level->sort_order, ''])),
            'grade' => Grade::query()->with('level')->orderBy('sort_order')->get()->map(fn ($grade) => $this->row($type, $grade, [$grade->name, $grade->level?->name, $grade->sort_order, ''])),
            'section' => Section::query()->with('grade.level')->orderBy('grade_id')->orderBy('name')->get()->map(fn ($section) => $this->row($type, $section, [$section->grade?->name.' · '.$section->name, $section->grade?->level?->name, $section->room ?: 'No room', ''])),
            default => collect(),
        };
    }

    private function row(string $type, Model $model, array $values): array
    {
        return ['structure_type' => $type, 'model_id' => $model->getKey(), 'values' => $values, 'deletable' => false];
    }

    public function find(string $type, int $id): Model
    {
        return match ($type) {
            'academic-year' => AcademicYear::findOrFail($id),
            'term-semester' => AcademicTerm::findOrFail($id),
            'level' => SchoolLevel::findOrFail($id),
            'grade' => Grade::findOrFail($id),
            'section' => Section::findOrFail($id),
            default => abort(404),
        };
    }

    public function form(string $type, Model $model): array
    {
        return match ($type) {
            'academic-year' => ['name' => $model->name, 'starts_on' => $model->starts_on->toDateString(), 'ends_on' => $model->ends_on->toDateString(), 'status' => $model->status],
            'term-semester' => ['academic_year_id' => $model->academic_year_id, 'name' => $model->name, 'type' => $model->type, 'starts_on' => $model->starts_on->toDateString(), 'ends_on' => $model->ends_on->toDateString(), 'status' => $model->status, 'sort_order' => $model->sort_order],
            'level' => ['name' => $model->name, 'sort_order' => $model->sort_order],
            'grade' => ['school_level_id' => $model->school_level_id, 'name' => $model->name, 'sort_order' => $model->sort_order],
            'section' => ['grade_id' => $model->grade_id, 'name' => $model->name, 'room' => $model->room],
            default => abort(404),
        };
    }

    public function save(Request $request, string $type, ?Model $model = null): void
    {
        $data = match ($type) {
            'academic-year' => $request->validate([
                'name' => ['required', 'string', 'max:50', Rule::unique('academic_years', 'name')->ignore($model)],
                'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after:starts_on'],
                'status' => ['required', Rule::in(['draft', 'open', 'closed'])],
            ]),
            'term-semester' => $request->validate([
                'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
                'name' => ['required', 'string', 'max:100'], 'type' => ['required', Rule::in(['term', 'semester'])],
                'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after:starts_on'],
                'status' => ['required', Rule::in(['draft', 'planned', 'open', 'closed'])],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            ]),
            'level' => $request->validate([
                'name' => ['required', 'string', 'max:100', Rule::unique('school_levels', 'name')->ignore($model)],
                'sort_order' => ['required', 'integer', 'min:0'],
            ]),
            'grade' => $request->validate([
                'school_level_id' => ['required', 'integer', 'exists:school_levels,id'],
                'name' => ['required', 'string', 'max:100'], 'sort_order' => ['required', 'integer', 'min:0'],
            ]),
            'section' => $request->validate([
                'grade_id' => ['required', 'integer', 'exists:grades,id'],
                'name' => ['required', 'string', 'max:100'], 'room' => ['nullable', 'string', 'max:100'],
            ]),
            default => abort(404),
        };

        if ($type === 'term-semester') {
            $year = AcademicYear::findOrFail($data['academic_year_id']);
            if ($data['starts_on'] < $year->starts_on->toDateString() || $data['ends_on'] > $year->ends_on->toDateString()) {
                throw ValidationException::withMessages(['starts_on' => 'Term dates must be within the academic year.']);
            }
            if (AcademicTerm::query()->where('academic_year_id', $year->id)->where('name', $data['name'])->when($model, fn ($query) => $query->whereKeyNot($model->getKey()))->exists()) {
                throw ValidationException::withMessages(['name' => 'This term already exists for the selected academic year.']);
            }
        }
        if ($type === 'grade' && Grade::query()->where('school_level_id', $data['school_level_id'])->where('name', $data['name'])->when($model, fn ($query) => $query->whereKeyNot($model->getKey()))->exists()) {
            throw ValidationException::withMessages(['name' => 'This grade already exists in the selected level.']);
        }
        if ($type === 'section' && Section::query()->where('grade_id', $data['grade_id'])->where('name', $data['name'])->when($model, fn ($query) => $query->whereKeyNot($model->getKey()))->exists()) {
            throw ValidationException::withMessages(['name' => 'This section already exists in the selected grade.']);
        }

        DB::transaction(function () use ($type, $model, $data, $request): void {
            if ($type === 'academic-year' && $data['status'] === 'open') {
                AcademicYear::query()->where('status', 'open')->when($model, fn ($query) => $query->whereKeyNot($model->getKey()))->update(['status' => 'closed']);
            }
            if ($model) {
                $model->update($data);
                return;
            }
            match ($type) {
                'academic-year' => AcademicYear::create($data + ['created_by' => $request->user()->id]),
                'term-semester' => AcademicTerm::create($data),
                'level' => SchoolLevel::create($data),
                'grade' => Grade::create($data),
                'section' => Section::create($data),
            };
        });
    }
}
