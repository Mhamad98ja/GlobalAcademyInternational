<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MedicalRecord;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MedicalRecordsController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'type' => ['nullable', 'string', 'max:80'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
        ]);
        $records = MedicalRecord::query()->with('student', 'teacher', 'createdBy')
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($filters['teacher_id'] ?? null, fn ($query, $id) => $query->where('teacher_id', $id))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('recorded_on', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('recorded_on', '<=', $date))
            ->latest('recorded_on')->paginate(25)->withQueryString();

        return view('medical.index', [
            'records' => $records,
            'filters' => $filters,
            'students' => Student::query()->orderBy('first_name')->orderBy('last_name')->get(),
            'teachers' => Teacher::query()->where('is_active', true)->orderBy('first_name')->get(),
            'types' => ['medical_report', 'doctor_note', 'sick_leave', 'injury', 'allergy', 'medical_restriction', 'nurse_event', 'scanned_document', 'other'],
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $record = MedicalRecord::create($this->data($request) + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $audit->log('medical_record.created', $record, [], $record->only(['student_id', 'teacher_id', 'type', 'recorded_on']));

        return back()->with('success', 'Medical archive record saved securely.');
    }

    public function update(Request $request, MedicalRecord $medicalRecord, AuditService $audit): RedirectResponse
    {
        $old = $medicalRecord->only(['type', 'recorded_on', 'title', 'description', 'restrictions', 'status']);
        $medicalRecord->update($this->data($request, $medicalRecord) + ['updated_by' => $request->user()->id]);
        $audit->log('medical_record.updated', $medicalRecord, $old, $medicalRecord->only(array_keys($old)));

        return back()->with('success', 'Medical archive record updated.');
    }

    public function download(MedicalRecord $medicalRecord)
    {
        abort_unless($medicalRecord->document_path && Storage::disk('local')->exists($medicalRecord->document_path), 404);

        return Storage::disk('local')->download($medicalRecord->document_path, $medicalRecord->document_name ?: 'medical-document');
    }

    /** @return array<string, mixed> */
    private function data(Request $request, ?MedicalRecord $existing = null): array
    {
        $data = $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'type' => ['required', Rule::in(['medical_report', 'doctor_note', 'sick_leave', 'injury', 'allergy', 'medical_restriction', 'nurse_event', 'scanned_document', 'other'])],
            'recorded_on' => ['required', 'date'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'restrictions' => ['nullable', 'string', 'max:3000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
            'status' => ['required', Rule::in(['active', 'archived'])],
        ]);
        if (filled($data['student_id'] ?? null) === filled($data['teacher_id'] ?? null)) {
            throw ValidationException::withMessages(['student_id' => 'Choose exactly one student or teacher for this medical record.']);
        }
        unset($data['document']);
        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $data['document_path'] = $file->store('medical-records', 'local');
            $data['document_name'] = $file->getClientOriginalName();
        } elseif ($existing) {
            $data['document_path'] = $existing->document_path;
            $data['document_name'] = $existing->document_name;
        }

        return $data;
    }
}
