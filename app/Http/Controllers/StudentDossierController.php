<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\ResponsiblePerson;
use App\Models\SchoolLevel;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentReferenceEntry;
use App\Models\StudentRegistration;
use App\Models\SystemSetting;
use App\Services\AuditService;
use App\Services\AcademicStructureCodes;
use App\Support\StudentDossierNavigation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentDossierController extends Controller
{
    public function dashboard(Request $request): View
    {
        $yearId = $request->integer('academic_year_id') ?: null;
        $activeAcademicYear = AcademicYear::query()
            ->when($yearId, fn (Builder $query) => $query->whereKey($yearId))
            ->when(! $yearId, fn (Builder $query) => $query->where('status', 'open'))
            ->orderByDesc('starts_on')
            ->first() ?? AcademicYear::query()->orderByDesc('starts_on')->first();

        $registrations = StudentRegistration::query()
            ->with('student.responsiblePersons', 'grade')
            ->when($activeAcademicYear, fn (Builder $query) => $query->where('academic_year_id', $activeAcademicYear->id))
            ->whereHas('student')
            ->get();
        $students = $registrations->pluck('student')->filter()->unique('id')->values();
        $familyCounts = $students
            ->flatMap(fn (Student $student) => $student->responsiblePersons->pluck('id'))
            ->countBy();
        $attendance = StudentAttendance::query()
            ->when($activeAcademicYear, fn (Builder $query) => $query->where('academic_year_id', $activeAcademicYear->id))
            ->get();
        $today = today();
        $remarks = collect([
            'Absence' => [
                'Today' => $attendance->where('status', 'absent')->filter(fn (StudentAttendance $entry) => $entry->attendance_date?->isSameDay($today))->count(),
                'This week' => $attendance->where('status', 'absent')->filter(fn (StudentAttendance $entry) => $entry->attendance_date?->betweenIncluded($today->copy()->startOfWeek(), $today->copy()->endOfWeek()))->count(),
                'This month' => $attendance->where('status', 'absent')->filter(fn (StudentAttendance $entry) => $entry->attendance_date?->isSameMonth($today))->count(),
            ],
            'Authorised leave' => [
                'Today' => $attendance->where('status', 'excused')->filter(fn (StudentAttendance $entry) => $entry->attendance_date?->isSameDay($today))->count(),
                'This week' => $attendance->where('status', 'excused')->filter(fn (StudentAttendance $entry) => $entry->attendance_date?->betweenIncluded($today->copy()->startOfWeek(), $today->copy()->endOfWeek()))->count(),
                'This month' => $attendance->where('status', 'excused')->filter(fn (StudentAttendance $entry) => $entry->attendance_date?->isSameMonth($today))->count(),
            ],
            'Late' => [
                'Today' => $attendance->where('status', 'late')->filter(fn (StudentAttendance $entry) => $entry->attendance_date?->isSameDay($today))->count(),
                'This week' => $attendance->where('status', 'late')->filter(fn (StudentAttendance $entry) => $entry->attendance_date?->betweenIncluded($today->copy()->startOfWeek(), $today->copy()->endOfWeek()))->count(),
                'This month' => $attendance->where('status', 'late')->filter(fn (StudentAttendance $entry) => $entry->attendance_date?->isSameMonth($today))->count(),
            ],
        ]);

        $studentOverview = [
            'totalStudents' => $students->count(),
            'totalFamilies' => $familyCounts->count(),
            'familySizes' => collect([1, 2, 3, 4])->mapWithKeys(fn (int $size) => [$size => $familyCounts->filter(fn (int $count) => $count === $size)->count()]),
            'gender' => [
                'female' => $students->where('gender', 'female')->count(),
                'male' => $students->where('gender', 'male')->count(),
                'unspecified' => $students->whereNotIn('gender', ['male', 'female'])->count(),
            ],
            'classes' => $registrations->groupBy('grade_id')->map(function ($items): array {
                return [
                    'name' => $items->first()->grade?->name ?? 'Unassigned',
                    'active' => $items->where('status', 'active')->pluck('student_id')->unique()->count(),
                    'other' => $items->where('status', '!=', 'active')->pluck('student_id')->unique()->count(),
                ];
            })->values(),
            'remarks' => $attendance->isNotEmpty() ? $remarks : null,
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
        ];

        return view('students.dossier.dashboard-page', compact('studentOverview', 'activeAcademicYear'));
    }

    public function tools(Request $request, string $section): View|RedirectResponse
    {
        if ($section === 'references') {
            return redirect()->route('students.references.show', ['reference' => 'codes']);
        }
        $section = StudentDossierNavigation::sections()[$section];
        $items = collect($section['items'])->filter(fn (array $item) => $request->user()->hasPermission($item['permission'])
            && (! str_starts_with($item['route'], 'reports.') || $request->user()->hasPermission('reports.view')));

        return view('students.dossier.tools', compact('section', 'items'));
    }

    public function reference(Request $request, string $reference): View
    {
        $definitions = $this->referenceDefinitions();
        abort_unless(isset($definitions[$reference]), 404);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:120'],
            'academic_year' => ['nullable', 'integer', 'exists:academic_years,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $codeTypes = [
            'blood-type' => ['label' => 'Blood Type', 'values' => ['--', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']],
            'gender' => ['label' => 'Gender', 'values' => ['--', 'Male', 'Female']],
            'registration-status' => ['label' => 'Registration Status', 'values' => ['--', 'Active', 'Inactive', 'Withdrawn', 'Completed']],
        ];
        foreach (AcademicStructureCodes::TYPES as $type => $label) {
            $codeTypes[$type] = ['label' => $label, 'values' => []];
        }
        $selectedCodeType = $filters['type'] ?? 'blood-type';

        $referenceEntries = StudentReferenceEntry::query()->where('reference', $reference)->orderBy('sort_order')->orderBy('value')->get();
        $customEntries = $referenceEntries->where('code', '!=', '__category__')->values();
        $rows = match ($reference) {
            'codes' => isset(AcademicStructureCodes::TYPES[$selectedCodeType])
                ? app(AcademicStructureCodes::class)->rows($selectedCodeType)->map(fn (array $row) => [
                    ...$row,
                    'key' => $this->referenceKey('structure', $selectedCodeType."\x1f".$row['model_id']),
                ])
                : $this->codeRows($codeTypes, $customEntries),
            'division' => SchoolLevel::query()->orderBy('sort_order')->get()->map(fn (SchoolLevel $level) => ['key' => $this->referenceKey('model', $level->id), 'values' => [$level->id, $level->name, '']]),
            'class-definition' => Grade::query()->orderBy('sort_order')->get()->map(fn (Grade $grade) => ['key' => $this->referenceKey('model', $grade->id), 'values' => [$grade->name, $grade->sort_order]]),
            'classes' => Section::query()->with('grade.level')->orderBy('grade_id')->orderBy('name')->get()->map(fn (Section $section) => ['key' => $this->referenceKey('model', $section->id), 'values' => [$section->grade?->name, $section->name, $section->grade?->sort_order, '', $section->grade?->level?->name, $section->room, '', $section->grade?->name, '', '', '--', '']]),
            'other-schools' => $this->otherSchoolRows($customEntries),
            'district' => $this->districtRows($customEntries),
            'towns' => $this->townRows($customEntries),
        };

        if ($reference === 'towns' && filled($filters['district'] ?? null)) {
            $rows = $rows->filter(fn (array $row): bool => $row[0] === $filters['district']);
        }
        if (filled($filters['q'] ?? null)) {
            $needle = mb_strtolower($filters['q']);
            $rows = $rows->filter(fn (array $row): bool => str_contains(
                mb_strtolower(implode(' ', array_map(fn ($value): string => (string) $value, $row))),
                $needle,
            ));
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $rows = new LengthAwarePaginator(
            $rows->forPage($page, 20)->values(),
            $rows->count(),
            20,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $definition = $definitions[$reference];
        if ($reference === 'codes' && isset(AcademicStructureCodes::TYPES[$selectedCodeType])) {
            $definition['headers'] = match ($selectedCodeType) {
                'academic-year' => ['Academic year', 'Dates', 'Status', ''],
                'term-semester' => ['Term / semester', 'Academic year / type', 'Dates', 'Status'],
                'level' => ['Level', 'Type', 'Order', ''],
                'grade' => ['Grade', 'Level', 'Order', ''],
                'section' => ['Class section', 'Level', 'Room', ''],
            };
        }

        return view('students.dossier.reference', [
            'reference' => $definition,
            'referenceKey' => $reference,
            'rows' => $rows,
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'districts' => ResponsiblePerson::query()->whereNotNull('district')->where('district', '!=', '')->distinct()->orderBy('district')->pluck('district'),
            'codeTypes' => $codeTypes,
            'selectedCodeType' => $selectedCodeType,
            'codeCategories' => $this->codeCategories($codeTypes, $referenceEntries),
            'canManageReferences' => $request->user()->hasPermission('students.view')
                && ! in_array($reference, ['division', 'class-definition', 'classes'], true)
                && ($reference !== 'codes' || ! isset(AcademicStructureCodes::TYPES[$selectedCodeType]) || $request->user()->hasPermission('academic.manage')),
        ]);
    }

    public function referenceCreate(Request $request, string $reference): View
    {
        $this->denyCodeManagedReference($reference);
        $definitions = $this->referenceDefinitions();
        abort_unless(isset($definitions[$reference]), 404);
        $this->authorizeStructureCode($request, (string) $request->query('type', ''));

        return $this->referenceForm($reference, null, $request->boolean('category'), (string) $request->query('type', 'blood-type'));
    }

    public function referenceEdit(Request $request, string $reference, string $entry): View
    {
        $this->denyCodeManagedReference($reference);
        $definitions = $this->referenceDefinitions();
        abort_unless(isset($definitions[$reference]), 404);

        $resolved = $this->resolveReferenceEntry($reference, $entry);
        $this->authorizeStructureCode($request, $resolved['category'] ?? '');

        return $this->referenceForm($reference, $resolved, false, $resolved['category'] ?? 'blood-type');
    }

    public function referenceStore(Request $request, string $reference): RedirectResponse
    {
        $this->denyCodeManagedReference($reference);
        abort_unless(isset($this->referenceDefinitions()[$reference]), 404);
        $type = (string) $request->input('category', '');
        if ($reference === 'codes' && isset(AcademicStructureCodes::TYPES[$type])) {
            $this->authorizeStructureCode($request, $type);
            app(AcademicStructureCodes::class)->save($request, $type);
            return redirect()->route('students.references.show', ['reference' => 'codes', 'type' => $type])->with('success', 'Academic reference created.');
        }
        $this->saveReference($request, $reference);

        return redirect()->route('students.references.show', ['reference' => $reference])->with('success', 'Reference created.');
    }

    public function referenceUpdate(Request $request, string $reference, string $entry): RedirectResponse
    {
        $this->denyCodeManagedReference($reference);
        abort_unless(isset($this->referenceDefinitions()[$reference]), 404);
        $resolved = $this->resolveReferenceEntry($reference, $entry);
        if ($reference === 'codes' && $resolved['type'] === 'structure') {
            $type = $resolved['category'];
            $this->authorizeStructureCode($request, $type);
            app(AcademicStructureCodes::class)->save($request, $type, app(AcademicStructureCodes::class)->find($type, (int) $resolved['value']));
            return redirect()->route('students.references.show', ['reference' => 'codes', 'type' => $type])->with('success', 'Academic reference updated.');
        }
        $this->saveReference($request, $reference, $resolved);

        return redirect()->route('students.references.show', ['reference' => $reference])->with('success', 'Reference updated.');
    }

    public function referenceDestroy(string $reference, string $entry): RedirectResponse
    {
        $this->denyCodeManagedReference($reference);
        abort_unless(isset($this->referenceDefinitions()[$reference]), 404);
        $resolved = $this->resolveReferenceEntry($reference, $entry);
        abort_if($resolved['type'] === 'structure', 403, 'Academic references cannot be deleted here.');
        $this->deleteReference($reference, $resolved);

        return redirect()->route('students.references.show', ['reference' => $reference])->with('success', 'Reference deleted.');
    }

    private function denyCodeManagedReference(string $reference): void
    {
        abort_if(in_array($reference, ['division', 'class-definition', 'classes'], true), 403, 'Academic structure is managed in code.');
    }

    private function authorizeStructureCode(Request $request, string $type): void
    {
        if (isset(AcademicStructureCodes::TYPES[$type])) {
            abort_unless($request->user()->hasPermission('academic.manage'), 403);
        }
    }

    public function referenceCategoryStore(Request $request): RedirectResponse
    {
        $data = $request->validate(['category' => ['required', 'string', 'max:120']]);
        if (isset(AcademicStructureCodes::TYPES[$data['category']])) {
            throw ValidationException::withMessages(['category' => 'This academic code type is already available.']);
        }
        StudentReferenceEntry::query()->firstOrCreate([
            'reference' => 'codes',
            'category' => $data['category'],
            'code' => '__category__',
        ], ['value' => $data['category']]);

        return redirect()->route('students.references.show', ['reference' => 'codes', 'type' => $data['category']])->with('success', 'Code category created.');
    }

    /** @return array<string, array{title:string, headers:array<int,string>}> */
    private function referenceDefinitions(): array
    {
        return [
            'codes' => ['title' => 'Codes', 'headers' => ['Code', 'Value', 'ValueEN', 'ValueAR']],
            'division' => ['title' => 'Division', 'headers' => ['Code', 'Division', 'DivisionAR']],
            'class-definition' => ['title' => 'Class Definition', 'headers' => ['Code', 'Rank']],
            'classes' => ['title' => 'Classes', 'headers' => ['Code', 'Section', 'Rank', 'Last Year', 'Division', 'Location', 'Activity Center', 'Name', 'Name AR', 'Name FR', 'Gender', 'Department']],
            'other-schools' => ['title' => 'Other Schools', 'headers' => ['Name', 'Number']],
            'district' => ['title' => 'District', 'headers' => ['District', 'District', 'Governorate', 'Gouvernorat']],
            'towns' => ['title' => 'Towns', 'headers' => ['Town', 'Name', 'NameAR']],
        ];
    }

    private function referenceKey(string $type, string|int $value): string
    {
        return rtrim(strtr(base64_encode($type.'|'.$value), '+/', '-_'), '=');
    }

    /** @return array{type:string,value:string} */
    private function decodeReferenceKey(string $key): array
    {
        $decoded = base64_decode(strtr($key, '-_', '+/').str_repeat('=', (4 - strlen($key) % 4) % 4), true);
        abort_unless(is_string($decoded) && str_contains($decoded, '|'), 404);
        [$type, $value] = explode('|', $decoded, 2);

        return ['type' => $type, 'value' => $value];
    }

    /** @param array<string, array{label:string,values:array<int,string>}> $codeTypes */
    private function codeCategories(array $codeTypes, $entries): array
    {
        return collect(array_keys($codeTypes))
            ->merge($entries->pluck('category')->filter())
            ->unique()
            ->values()
            ->all();
    }

    /** @param array<string, array{label:string,values:array<int,string>}> $codeTypes */
    private function codeRows(array $codeTypes, $entries): \Illuminate\Support\Collection
    {
        $category = request('type') ?: array_key_first($codeTypes);
        $categoryEntries = $entries->where('category', $category);
        $suppressedCodes = $categoryEntries->where('value', '__deleted__')->pluck('code')->filter();
        $customCodes = $categoryEntries->reject(fn (StudentReferenceEntry $entry) => $entry->value === '__deleted__')->keyBy(fn (StudentReferenceEntry $entry) => (string) $entry->code);
        $builtIn = collect($codeTypes[$category]['values'] ?? [])->reject(fn (string $value) => $customCodes->has($value) || $suppressedCodes->contains($value))->map(fn (string $value) => [
            'key' => $this->referenceKey('builtin-code', $category."\x1f".$value),
            'values' => [$value, $value, $value, ''],
            'deletable' => true,
        ]);
        $custom = $customCodes->map(fn (StudentReferenceEntry $entry) => [
            'key' => $this->referenceKey('entry', $entry->id),
            'values' => [$entry->code, $entry->value, $entry->value_en, $entry->value_ar],
            'deletable' => true,
        ])->values();

        return $builtIn->concat($custom)->values();
    }

    private function otherSchoolRows($entries): \Illuminate\Support\Collection
    {
        $customValues = $entries->pluck('value');
        $existing = Student::query()->whereNotNull('previous_school')->where('previous_school', '!=', '')->distinct()->orderBy('previous_school')->pluck('previous_school')
            ->reject(fn (string $school) => $customValues->contains($school))
            ->map(fn (string $school) => ['key' => $this->referenceKey('raw-school', $school), 'values' => [$school, '']]);

        return $existing->concat($entries->map(fn (StudentReferenceEntry $entry) => ['key' => $this->referenceKey('entry', $entry->id), 'values' => [$entry->value, $entry->code]]))->values();
    }

    private function districtRows($entries): \Illuminate\Support\Collection
    {
        $customValues = $entries->pluck('value');
        $existing = ResponsiblePerson::query()->whereNotNull('district')->where('district', '!=', '')->distinct()->orderBy('district')->pluck('district')
            ->reject(fn (string $district) => $customValues->contains($district))
            ->map(fn (string $district) => ['key' => $this->referenceKey('raw-district', $district), 'values' => [$district, $district, '', '']]);

        return $existing->concat($entries->map(fn (StudentReferenceEntry $entry) => ['key' => $this->referenceKey('entry', $entry->id), 'values' => [$entry->value, $entry->value, $entry->value_en, $entry->value_en]]))->values();
    }

    private function townRows($entries): \Illuminate\Support\Collection
    {
        $existingKeys = $entries->map(fn (StudentReferenceEntry $entry) => $entry->category."\x1f".$entry->value);
        $existing = ResponsiblePerson::query()->whereNotNull('town')->where('town', '!=', '')->select('district', 'town')->distinct()->orderBy('district')->orderBy('town')->get()
            ->reject(fn (ResponsiblePerson $person) => $existingKeys->contains($person->district."\x1f".$person->town))
            ->map(fn (ResponsiblePerson $person) => ['key' => $this->referenceKey('raw-town', $person->district."\x1f".$person->town), 'values' => [$person->district, $person->town, '']]);

        return $existing->concat($entries->map(fn (StudentReferenceEntry $entry) => ['key' => $this->referenceKey('entry', $entry->id), 'values' => [$entry->category, $entry->value, $entry->value_ar]]))->values();
    }

    private function referenceForm(string $reference, ?array $entry, bool $categoryOnly = false, string $selectedCategory = 'blood-type'): View
    {
        $definitions = $this->referenceDefinitions();
        $codeTypes = [
            'blood-type' => ['label' => 'Blood Type'],
            'gender' => ['label' => 'Gender'],
            'registration-status' => ['label' => 'Registration Status'],
        ];
        foreach (AcademicStructureCodes::TYPES as $type => $label) {
            $codeTypes[$type] = ['label' => $label];
        }
        $categories = $this->codeCategories($codeTypes, StudentReferenceEntry::query()->where('reference', 'codes')->get());

        return view('students.dossier.reference-form', [
            'reference' => $definitions[$reference],
            'referenceKey' => $reference,
            'entry' => $entry['form'] ?? [],
            'entryKey' => $entry['key'] ?? null,
            'mode' => $entry ? 'edit' : 'create',
            'categoryOnly' => $categoryOnly,
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'structureTypes' => AcademicStructureCodes::TYPES,
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'levels' => SchoolLevel::query()->orderBy('sort_order')->get(),
            'grades' => Grade::query()->with('level')->orderBy('sort_order')->get(),
        ]);
    }

    /** @return array{key:string,type:string,value:string,form:array<string,mixed>} */
    private function resolveReferenceEntry(string $reference, string $key): array
    {
        $decoded = $this->decodeReferenceKey($key);
        $type = $decoded['type'];
        $value = $decoded['value'];
        $form = [];

        if ($type === 'model') {
            $model = match ($reference) {
                'division' => SchoolLevel::findOrFail((int) $value),
                'class-definition' => Grade::findOrFail((int) $value),
                'classes' => Section::findOrFail((int) $value),
                default => abort(404),
            };
            $form = match ($reference) {
                'division' => ['name' => $model->name, 'sort_order' => $model->sort_order],
                'class-definition' => ['school_level_id' => $model->school_level_id, 'name' => $model->name, 'sort_order' => $model->sort_order],
                'classes' => ['grade_id' => $model->grade_id, 'name' => $model->name, 'room' => $model->room],
            };
        } elseif ($type === 'structure') {
            abort_unless($reference === 'codes' && str_contains($value, "\x1f"), 404);
            [$category, $id] = explode("\x1f", $value, 2);
            abort_unless(isset(AcademicStructureCodes::TYPES[$category]) && ctype_digit($id), 404);
            $model = app(AcademicStructureCodes::class)->find($category, (int) $id);
            $form = app(AcademicStructureCodes::class)->form($category, $model);
            $value = $id;
        } elseif ($type === 'entry') {
            $model = StudentReferenceEntry::query()->where('reference', $reference)->findOrFail((int) $value);
            $form = match ($reference) {
                'codes' => ['category' => $model->category, 'code' => $model->code, 'value' => $model->value, 'value_en' => $model->value_en, 'value_ar' => $model->value_ar],
                'other-schools' => ['name' => $model->value, 'number' => $model->code],
                'district' => ['district' => $model->value, 'governorate' => $model->value_en],
                'towns' => ['district' => $model->category, 'name' => $model->value, 'name_ar' => $model->value_ar],
                default => [],
            };
        } elseif ($type === 'builtin-code') {
            [$category, $code] = explode("\x1f", $value, 2);
            $form = ['category' => $category, 'code' => $code, 'value' => $code, 'value_en' => $code, 'value_ar' => ''];
        } elseif ($type === 'raw-school') {
            $form = ['name' => $value, 'number' => ''];
        } elseif ($type === 'raw-district') {
            $form = ['district' => $value, 'governorate' => ''];
        } elseif ($type === 'raw-town') {
            [$district, $town] = explode("\x1f", $value, 2);
            $form = ['district' => $district, 'name' => $town, 'name_ar' => ''];
        } else {
            abort(404);
        }

        return ['key' => $key, 'type' => $type, 'value' => $value, 'form' => $form, 'category' => $category ?? ($form['category'] ?? null)];
    }

    private function saveReference(Request $request, string $reference, ?array $resolved = null): void
    {
        match ($reference) {
            'codes' => $this->saveCodeReference($request, $resolved),
            'division' => $this->saveDivisionReference($request, $resolved),
            'class-definition' => $this->saveClassDefinitionReference($request, $resolved),
            'classes' => $this->saveClassReference($request, $resolved),
            'other-schools' => $this->saveOtherSchoolReference($request, $resolved),
            'district' => $this->saveDistrictReference($request, $resolved),
            'towns' => $this->saveTownReference($request, $resolved),
            default => abort(404),
        };
    }

    private function saveCodeReference(Request $request, ?array $resolved): void
    {
        $data = $request->validate(['category' => ['nullable', 'string', 'max:120'], 'new_category' => ['nullable', 'string', 'max:120'], 'code' => ['required', 'string', 'max:120'], 'value' => ['required', 'string', 'max:255'], 'value_en' => ['nullable', 'string', 'max:255'], 'value_ar' => ['nullable', 'string', 'max:255']]);
        $category = $data['new_category'] ?? $data['category'] ?? null;
        abort_unless(filled($category), 422, 'Choose an existing code category or add a new one.');
        if (isset(AcademicStructureCodes::TYPES[$category])) {
            throw ValidationException::withMessages(['category' => 'Use the academic reference form for this code type.']);
        }
        $payload = ['reference' => 'codes', 'category' => $category, 'code' => $data['code'], 'value' => $data['value'], 'value_en' => $data['value_en'] ?? null, 'value_ar' => $data['value_ar'] ?? null];
        $entryId = $resolved && $resolved['type'] === 'entry' ? (int) $this->decodeReferenceKey($resolved['key'])['value'] : null;
        $duplicate = StudentReferenceEntry::query()->where('reference', 'codes')->where('category', $category)->where('code', $data['code'])->where('value', '!=', '__deleted__')->when($entryId, fn (Builder $query) => $query->whereKeyNot($entryId))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['code' => 'This code already exists in the selected category.']);
        }
        if ($resolved && $resolved['type'] === 'entry') {
            StudentReferenceEntry::query()->whereKey($entryId)->update($payload);
        } else {
            StudentReferenceEntry::query()->updateOrCreate(['reference' => 'codes', 'category' => $category, 'code' => $data['code']], $payload);
        }
    }

    private function saveDivisionReference(Request $request, ?array $resolved): void
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'sort_order' => ['nullable', 'integer', 'min:0']]);
        $model = $resolved && $resolved['type'] === 'model' ? SchoolLevel::findOrFail((int) $this->decodeReferenceKey($resolved['key'])['value']) : null;
        if (SchoolLevel::query()->where('name', $data['name'])->when($model, fn (Builder $query) => $query->whereKeyNot($model->id))->exists()) {
            throw ValidationException::withMessages(['name' => 'A division with this name already exists.']);
        }
        $model ? $model->update($data) : SchoolLevel::create($data);
    }

    private function saveClassDefinitionReference(Request $request, ?array $resolved): void
    {
        $data = $request->validate(['school_level_id' => ['nullable', 'exists:school_levels,id'], 'name' => ['required', 'string', 'max:100'], 'sort_order' => ['nullable', 'integer', 'min:0']]);
        $model = $resolved && $resolved['type'] === 'model' ? Grade::findOrFail((int) $this->decodeReferenceKey($resolved['key'])['value']) : null;
        $duplicate = Grade::query()->where('name', $data['name'])->when($data['school_level_id'] ?? null, fn (Builder $query, int $id) => $query->where('school_level_id', $id), fn (Builder $query) => $query->whereNull('school_level_id'))->when($model, fn (Builder $query) => $query->whereKeyNot($model->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'This class definition already exists in the selected division.']);
        }
        $model ? $model->update($data) : Grade::create($data);
    }

    private function saveClassReference(Request $request, ?array $resolved): void
    {
        $data = $request->validate(['grade_id' => ['required', 'exists:grades,id'], 'name' => ['required', 'string', 'max:100'], 'room' => ['nullable', 'string', 'max:100']]);
        $model = $resolved && $resolved['type'] === 'model' ? Section::findOrFail((int) $this->decodeReferenceKey($resolved['key'])['value']) : null;
        if (Section::query()->where('grade_id', $data['grade_id'])->where('name', $data['name'])->when($model, fn (Builder $query) => $query->whereKeyNot($model->id))->exists()) {
            throw ValidationException::withMessages(['name' => 'This section already exists in the selected class definition.']);
        }
        $model ? $model->update($data) : Section::create($data);
    }

    private function saveOtherSchoolReference(Request $request, ?array $resolved): void
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'number' => ['nullable', 'string', 'max:120']]);
        if ($resolved && $resolved['type'] === 'raw-school') {
            Student::query()->where('previous_school', $resolved['value'])->update(['previous_school' => $data['name']]);
        } elseif ($resolved && $resolved['type'] === 'entry') {
            StudentReferenceEntry::query()->whereKey((int) $this->decodeReferenceKey($resolved['key'])['value'])->update(['value' => $data['name'], 'code' => $data['number'] ?? null]);
        } else {
            StudentReferenceEntry::create(['reference' => 'other-schools', 'value' => $data['name'], 'code' => $data['number'] ?? null]);
        }
    }

    private function saveDistrictReference(Request $request, ?array $resolved): void
    {
        $data = $request->validate(['district' => ['required', 'string', 'max:255'], 'governorate' => ['nullable', 'string', 'max:255']]);
        if ($resolved && $resolved['type'] === 'raw-district') {
            ResponsiblePerson::query()->where('district', $resolved['value'])->update(['district' => $data['district']]);
        } elseif ($resolved && $resolved['type'] === 'entry') {
            StudentReferenceEntry::query()->whereKey((int) $this->decodeReferenceKey($resolved['key'])['value'])->update(['value' => $data['district'], 'value_en' => $data['governorate'] ?? null]);
        } else {
            StudentReferenceEntry::create(['reference' => 'district', 'value' => $data['district'], 'value_en' => $data['governorate'] ?? null]);
        }
    }

    private function saveTownReference(Request $request, ?array $resolved): void
    {
        $data = $request->validate(['district' => ['required', 'string', 'max:255'], 'name' => ['required', 'string', 'max:255'], 'name_ar' => ['nullable', 'string', 'max:255']]);
        if ($resolved && $resolved['type'] === 'raw-town') {
            [$oldDistrict, $oldTown] = explode("\x1f", $resolved['value'], 2);
            ResponsiblePerson::query()->where('district', $oldDistrict)->where('town', $oldTown)->update(['district' => $data['district'], 'town' => $data['name']]);
        } elseif ($resolved && $resolved['type'] === 'entry') {
            StudentReferenceEntry::query()->whereKey((int) $this->decodeReferenceKey($resolved['key'])['value'])->update(['category' => $data['district'], 'value' => $data['name'], 'value_ar' => $data['name_ar'] ?? null]);
        } else {
            StudentReferenceEntry::create(['reference' => 'towns', 'category' => $data['district'], 'value' => $data['name'], 'value_ar' => $data['name_ar'] ?? null]);
        }
    }

    /** @param array{key:string,type:string,value:string,form:array<string,mixed>} $resolved */
    private function deleteReference(string $reference, array $resolved): void
    {
        if ($reference === 'codes') {
            if ($resolved['type'] === 'builtin-code') {
                [$category, $code] = explode("\x1f", $resolved['value'], 2);
                StudentReferenceEntry::query()->updateOrCreate([
                    'reference' => 'codes', 'category' => $category, 'code' => $code,
                ], ['value' => '__deleted__', 'value_en' => null, 'value_ar' => null]);

                return;
            }
            $entry = StudentReferenceEntry::query()->whereKey((int) $resolved['value'])->firstOrFail();
            $builtInCodes = [
                'blood-type' => ['--', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
                'gender' => ['--', 'Male', 'Female'],
                'registration-status' => ['--', 'Active', 'Inactive', 'Withdrawn', 'Completed'],
            ];
            if (in_array($entry->code, $builtInCodes[$entry->category] ?? [], true)) {
                $entry->update(['value' => '__deleted__', 'value_en' => null, 'value_ar' => null]);
            } else {
                $entry->delete();
            }

            return;
        }

        if ($reference === 'other-schools') {
            $resolved['type'] === 'entry'
                ? StudentReferenceEntry::query()->whereKey((int) $resolved['value'])->delete()
                : Student::query()->where('previous_school', $resolved['value'])->update(['previous_school' => null]);

            return;
        }

        if ($reference === 'district') {
            $resolved['type'] === 'entry'
                ? StudentReferenceEntry::query()->whereKey((int) $resolved['value'])->delete()
                : ResponsiblePerson::query()->where('district', $resolved['value'])->update(['district' => null]);

            return;
        }

        if ($reference === 'towns') {
            if ($resolved['type'] === 'entry') {
                StudentReferenceEntry::query()->whereKey((int) $resolved['value'])->delete();
            } else {
                [$district, $town] = explode("\x1f", $resolved['value'], 2);
                ResponsiblePerson::query()->where('district', $district)->where('town', $town)->update(['town' => null]);
            }

            return;
        }

        $model = match ($reference) {
            'division' => SchoolLevel::findOrFail((int) $resolved['value']),
            'class-definition' => Grade::findOrFail((int) $resolved['value']),
            'classes' => Section::findOrFail((int) $resolved['value']),
            default => abort(404),
        };
        try {
            DB::transaction(function () use ($model): void {
                if ($model instanceof SchoolLevel) {
                    foreach ($model->grades()->with('sections')->get() as $grade) {
                        foreach ($grade->sections as $section) {
                            $section->delete();
                        }
                        $grade->delete();
                    }
                } elseif ($model instanceof Grade) {
                    foreach ($model->sections as $section) {
                        $section->delete();
                    }
                }
                $model->delete();
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['reference' => 'This reference is used by existing school records and cannot be deleted. Reassign or remove those records first.']);
        }
    }

    public function report(Request $request, string $report): View
    {
        $reports = [
            'class-list' => ['title' => 'Class List Reports', 'items' => [
                ['Class list', 'Students arranged by academic year, class, and section.', 'reports.index', ['report' => 'class_sections', 'module' => 'students']],
                ['Student information list', 'Choose the identity and contact columns to display.', 'reports.index', ['report' => 'student_information', 'module' => 'students']],
                ['Attendance class list', 'Daily and period attendance by class.', 'reports.school', ['report' => 'student_attendance', 'module' => 'students']],
            ]],
            'statistical' => ['title' => 'Statistical Report', 'items' => [
                ['Students statistics', 'Registration counts by year, grade, section, status, and gender.', 'reports.index', ['report' => 'students', 'module' => 'students']],
                ['Attendance statistics', 'Absence and attendance totals for the selected period.', 'reports.school', ['report' => 'student_attendance', 'module' => 'students']],
                ['Student performance statistics', 'Academic grade results by class and subject.', 'reports.school', ['report' => 'grades', 'module' => 'students']],
            ]],
            'labels' => ['title' => 'Label Reports', 'items' => [
                ['Student labels', 'Print student name, number, and current class labels.', 'reports.index', ['report' => 'student_information', 'module' => 'students']],
                ['Family labels', 'Print family and guardian contact labels.', 'reports.index', ['report' => 'student_information', 'module' => 'students']],
            ]],
            'catalog' => ['title' => 'Catalog Report', 'items' => [
                ['Student catalog', 'Complete student directory with selected columns.', 'reports.index', ['report' => 'student_information', 'module' => 'students']],
                ['Registration catalog', 'Registration history by school year and class.', 'reports.index', ['report' => 'students', 'module' => 'students']],
            ]],
            'registration' => ['title' => 'Registration Report', 'items' => [
                ['Not registered', 'Students without an active registration.', 'reports.index', ['report' => 'students', 'registration_status' => 'inactive', 'module' => 'students']],
                ['Daily registration', 'Registrations recorded within a selected date range.', 'reports.index', ['report' => 'students', 'module' => 'students']],
                ['Online Re-registration', 'Review registration records submitted for the new year.', 'reports.index', ['report' => 'students', 'module' => 'students']],
                ['Official class difference', 'Compare student class placement between years.', 'reports.index', ['report' => 'class_sections', 'module' => 'students']],
                ['Students Enrollment', 'Active enrolment list by class and section.', 'reports.index', ['report' => 'class_sections', 'registration_status' => 'active', 'module' => 'students']],
            ]],
            'dynamic' => ['title' => 'Student & Family Report', 'items' => [
                ['Dynamic Reports', 'Choose student and family fields, filters, and output columns.', 'reports.index', ['report' => 'student_information', 'module' => 'students']],
            ]],
        ];
        abort_unless(isset($reports[$report]), 404);

        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        if (filled($filters['q'] ?? null)) {
            $needle = mb_strtolower($filters['q']);
            $reports[$report]['items'] = array_values(array_filter(
                $reports[$report]['items'],
                fn (array $item): bool => str_contains(mb_strtolower($item[0].' '.$item[1]), $needle),
            ));
        }

        return view('students.dossier.report', ['report' => $reports[$report], 'reportKey' => $report]);
    }

    public function families(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        return view('students.dossier.families', [
            'families' => ResponsiblePerson::query()
                ->where('branch_id', app(\App\Services\BranchContext::class)->id())
                ->doesntHave('students')
                ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $people) => $people
                    ->whereInsensitiveLike('full_name', $search)
                    ->orWhereInsensitiveLike('parent_code', $search)
                    ->orWhereInsensitiveLike('phone', $search)))
                ->orderBy('full_name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function sectionChanges(Request $request): View
    {
        $filters = $this->sectionFilters($request);
        $registrations = StudentRegistration::query()
            ->whereHas('student')
            ->with('student', 'academicYear', 'grade', 'section')
            ->where('status', 'active')
            ->when($filters['academic_year_id'] ?? null, fn (Builder $query, int $id) => $query->where('academic_year_id', $id))
            ->when($filters['grade_id'] ?? null, fn (Builder $query, int $id) => $query->where('grade_id', $id))
            ->when($filters['section_id'] ?? null, fn (Builder $query, int $id) => $query->where('section_id', $id))
            ->when($filters['gender'] ?? null, fn (Builder $query, string $gender) => $query->whereHas('student', fn (Builder $students) => $students->where('gender', $gender)))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('student', fn (Builder $students) => $students
                ->whereInsensitiveLike('student_number', $search)
                ->orWhereInsensitiveLike('first_name', $search)
                ->orWhereInsensitiveLike('last_name', $search)))
            ->orderBy('student_id')
            ->paginate(50)
            ->withQueryString();

        return view('students.dossier.section-changes', [
            'filters' => $filters,
            'registrations' => $registrations,
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'grades' => Grade::query()->with('sections')->orderBy('sort_order')->get(),
        ]);
    }

    public function updateSections(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'grade_id' => ['required', 'integer', 'exists:grades,id'],
            'new_section_id' => ['required', 'integer', 'exists:sections,id'],
            'registration_ids' => ['required', 'array', 'min:1'],
            'registration_ids.*' => ['required', 'integer', 'distinct', 'exists:student_registrations,id'],
        ]);

        $newSection = Section::query()->whereKey($data['new_section_id'])->where('grade_id', $data['grade_id'])->first();
        if (! $newSection) {
            throw ValidationException::withMessages(['new_section_id' => 'The destination section must belong to the selected grade.']);
        }

        $registrations = StudentRegistration::query()
            ->whereHas('student')
            ->whereKey($data['registration_ids'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('grade_id', $data['grade_id'])
            ->where('status', 'active')
            ->get();
        if ($registrations->count() !== count($data['registration_ids'])) {
            throw ValidationException::withMessages(['registration_ids' => 'Every selected student must have an active registration in the selected academic year and grade.']);
        }

        DB::transaction(function () use ($registrations, $newSection, $audit): void {
            foreach ($registrations as $registration) {
                $old = ['section_id' => $registration->section_id];
                $registration->update(['section_id' => $newSection->id]);
                $audit->log('student.section_changed', $registration, $old, ['section_id' => $newSection->id]);
            }
        });

        return redirect()->route('students.section-changes', [
            'academic_year_id' => $data['academic_year_id'],
            'grade_id' => $data['grade_id'],
            'section_id' => $newSection->id,
        ])->with('success', count($registrations).' student section assignment(s) updated.');
    }

    public function photos(Request $request): View
    {
        $filters = $this->photoFilters($request);
        $registrations = StudentRegistration::query()
            ->whereHas('student')
            ->with('student', 'academicYear', 'grade', 'section')
            ->where('status', 'active')
            ->when($filters['academic_year_id'] ?? null, fn (Builder $query, int $id) => $query->where('academic_year_id', $id))
            ->when($filters['grade_id'] ?? null, fn (Builder $query, int $id) => $query->where('grade_id', $id))
            ->when($filters['section_id'] ?? null, fn (Builder $query, int $id) => $query->where('section_id', $id))
            ->orderBy('student_id')
            ->paginate(30)
            ->withQueryString();

        return view('students.dossier.photos', [
            'filters' => $filters,
            'registrations' => $registrations,
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'grades' => Grade::query()->with('sections')->orderBy('sort_order')->get(),
        ]);
    }

    public function attestations(Request $request): View
    {
        $filters = $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $registrations = StudentRegistration::query()
            ->whereHas('student')
            ->with('student', 'academicYear', 'grade', 'section')
            ->where('status', 'active')
            ->when($filters['student_id'] ?? null, fn (Builder $query, int $id) => $query->where('student_id', $id))
            ->when($filters['academic_year_id'] ?? null, fn (Builder $query, int $id) => $query->where('academic_year_id', $id))
            ->when($filters['grade_id'] ?? null, fn (Builder $query, int $id) => $query->where('grade_id', $id))
            ->when($filters['section_id'] ?? null, fn (Builder $query, int $id) => $query->where('section_id', $id))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('student', fn (Builder $students) => $students
                ->whereInsensitiveLike('student_number', $search)
                ->orWhereInsensitiveLike('first_name', $search)
                ->orWhereInsensitiveLike('last_name', $search)))
            ->orderBy('student_id')
            ->paginate(50)
            ->withQueryString();

        return view('students.dossier.attestations', [
            'filters' => $filters,
            'registrations' => $registrations,
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'grades' => Grade::query()->with('sections')->orderBy('sort_order')->get(),
        ]);
    }

    public function printAttestations(Request $request): View
    {
        $data = $request->validate([
            'registration_ids' => ['required', 'array', 'min:1', 'max:100'],
            'registration_ids.*' => ['required', 'integer', 'distinct', 'exists:student_registrations,id'],
            'certificate_type' => ['required', 'in:enrollment,attendance'],
            'reference_date' => ['required', 'date'],
            'include_enrollment_date' => ['nullable', 'boolean'],
        ]);
        $registrations = StudentRegistration::query()
            ->whereHas('student')
            ->with('student.responsiblePersons', 'academicYear', 'grade', 'section')
            ->whereKey($data['registration_ids'])
            ->where('status', 'active')
            ->orderBy('student_id')
            ->get();
        if ($registrations->count() !== count($data['registration_ids'])) {
            throw ValidationException::withMessages(['registration_ids' => 'Only active registrations can be used for attestations.']);
        }

        return view('students.dossier.attestations-print', [
            'registrations' => $registrations,
            'certificateType' => $data['certificate_type'],
            'referenceDate' => Carbon::parse($data['reference_date']),
            'includeEnrollmentDate' => (bool) ($data['include_enrollment_date'] ?? false),
            'school' => SystemSetting::query()->whereIn('key', ['school_name', 'school_address', 'school_phone'])->pluck('value', 'key'),
        ]);
    }

    /** @return array<string, mixed> */
    private function sectionFilters(Request $request): array
    {
        return $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'gender' => ['nullable', 'in:male,female'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
    }

    /** @return array<string, mixed> */
    private function photoFilters(Request $request): array
    {
        return $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
        ]);
    }
}
