<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('students.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $student = $this->route('student');
        $portalUserId = $student?->portalUser?->id;

        return [
            'student_number' => ['nullable', 'string', 'max:50', Rule::unique('students', 'student_number')->ignore($student)],
            'registration_number' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('students', 'email')->ignore($student),
                Rule::unique('users', 'email')->ignore($portalUserId),
            ],
            'password' => [
                Rule::requiredIf(fn (): bool => ! $student),
                'nullable', 'confirmed', Password::min(12)->mixedCase()->numbers(),
            ],
            'address' => ['nullable', 'string', 'max:2000'],
            'enrollment_date' => ['nullable', 'date'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
