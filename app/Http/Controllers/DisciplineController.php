<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ActionCategory;
use App\Models\ActionType;
use App\Models\Student;
use App\Models\StudentIncident;
use App\Models\StudentRegistration;
use App\Models\Teacher;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DisciplineController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'action_category_id' => ['nullable', 'integer', 'exists:action_categories,id'],
            'action_type_id' => ['nullable', 'integer', 'exists:action_types,id'],
            'status' => ['nullable', Rule::in(['open', 'resolved', 'dismissed'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
        $incidents = StudentIncident::query()
            ->with('student', 'category', 'type', 'reporter')
            ->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($filters['action_category_id'] ?? null, fn ($query, $id) => $query->where('action_category_id', $id))
            ->when($filters['action_type_id'] ?? null, fn ($query, $id) => $query->where('action_type_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('incident_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('incident_date', '<=', $date))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($records) => $records->whereInsensitiveLike('description', $search)->orWhereInsensitiveLike('location', $search)->orWhereHas('student', fn ($students) => $students->whereInsensitiveLike('student_number', $search)->orWhereInsensitiveLike('first_name', $search)->orWhereInsensitiveLike('last_name', $search))))
            ->latest('incident_date')->latest('incident_time')->paginate(25)->withQueryString();

        return view('discipline.index', [
            'incidents' => $incidents,
            'filters' => $filters,
            'students' => Student::query()->orderBy('first_name')->orderBy('last_name')->get(),
            'teachers' => Teacher::query()->where('is_active', true)->orderBy('first_name')->get(),
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'categories' => ActionCategory::query()->with('types')->orderBy('name')->get(),
        ]);
    }

    public function storeCategory(Request $request, AuditService $audit): RedirectResponse
    {
        $category = ActionCategory::create($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', 'string', 'max:50'],
        ]) + ['is_active' => true]);
        $audit->log('discipline.category_created', $category, [], $category->only(['name', 'kind']));

        return back()->with('success', 'Action category created.');
    }

    public function storeType(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'action_category_id' => ['required', 'integer', 'exists:action_categories,id'],
            'name' => ['required', 'string', 'max:100'],
        ]);
        $type = ActionType::create($data + ['is_active' => true]);
        $audit->log('discipline.type_created', $type, [], $type->only(['action_category_id', 'name']));

        return back()->with('success', 'Action type created.');
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $this->incidentData($request);
        $incident = StudentIncident::create($data + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $audit->log('student_incident.created', $incident, [], $incident->only(['student_id', 'action_type_id', 'incident_date', 'status']));

        return back()->with('success', 'Student action record created.');
    }

    public function update(Request $request, StudentIncident $incident, AuditService $audit): RedirectResponse
    {
        $old = $incident->only(['action_category_id', 'action_type_id', 'incident_date', 'description', 'action_taken', 'consequence', 'status']);
        $incident->update($this->incidentData($request, $incident) + ['updated_by' => $request->user()->id]);
        $audit->log('student_incident.updated', $incident, $old, $incident->only(array_keys($old)));

        return back()->with('success', 'Student action record updated.');
    }

    /** @return array<string, mixed> */
    private function incidentData(Request $request, ?StudentIncident $existing = null): array
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'student_registration_id' => ['nullable', 'integer', 'exists:student_registrations,id'],
            'action_category_id' => ['nullable', 'integer', 'exists:action_categories,id'],
            'action_type_id' => ['nullable', 'integer', 'exists:action_types,id'],
            'incident_date' => ['required', 'date'],
            'incident_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'witnesses' => ['nullable', 'string', 'max:3000'],
            'action_taken' => ['nullable', 'string', 'max:3000'],
            'consequence' => ['nullable', 'string', 'max:3000'],
            'follow_up' => ['nullable', 'string', 'max:3000'],
            'parent_notified' => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::in(['open', 'resolved', 'dismissed'])],
            'reported_by_teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
        ]);
        $registration = ! empty($data['student_registration_id'])
            ? StudentRegistration::query()->whereKey($data['student_registration_id'])->where('student_id', $data['student_id'])->firstOrFail()
            : StudentRegistration::query()->where('student_id', $data['student_id'])->where('status', 'active')->latest('registered_on')->first();
        if ($data['action_type_id'] && ! ActionType::query()->whereKey($data['action_type_id'])->when($data['action_category_id'] ?? null, fn ($query, $id) => $query->where('action_category_id', $id))->exists()) {
            throw ValidationException::withMessages(['action_type_id' => 'The action type does not belong to the selected category.']);
        }
        $data['student_registration_id'] = $registration?->id;
        $data['academic_year_id'] = $registration?->academic_year_id;
        $data['grade_id'] = $registration?->grade_id;
        $data['section_id'] = $registration?->section_id;
        $data['parent_notified'] = $request->boolean('parent_notified');
        unset($data['attachment']);
        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('student-incidents', 'local');
        } elseif ($existing) {
            $data['attachment_path'] = $existing->attachment_path;
        }

        return $data;
    }
}
