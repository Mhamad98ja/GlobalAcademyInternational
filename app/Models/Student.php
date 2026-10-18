<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class Student extends Model
{
    use BelongsToBranch,HasPublicId,Notifiable,SoftDeletes;

    protected $fillable = ['branch_id', 'student_number', 'registration_number', 'official_id_number', 'nationality', 'place_of_birth', 'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'phone', 'email', 'address', 'area', 'street', 'area_number', 'street_number', 'building_name', 'building_number', 'apartment', 'enrollment_date', 'previous_school', 'photo_path', 'notes'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'enrollment_date' => 'date'];
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }

    public function registrations()
    {
        return $this->hasMany(StudentRegistration::class);
    }

    public function responsiblePersons()
    {
        return $this->belongsToMany(ResponsiblePerson::class, 'responsible_person_student')->withPivot(['relationship', 'is_primary'])->withTimestamps();
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function attendances()
    {
        return $this->hasMany(StudentAttendance::class);
    }

    public function incidents()
    {
        return $this->hasMany(StudentIncident::class);
    }

    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function examAbsences()
    {
        return $this->hasMany(ExamAbsence::class);
    }

    public function gradeEntries()
    {
        return $this->hasMany(GradeEntry::class);
    }

    public function performanceEvaluations()
    {
        return $this->hasMany(StudentPerformanceEvaluation::class);
    }

    public function supportAssignments()
    {
        return $this->hasMany(StudentSupportAssignment::class);
    }

    public function subjectExemptions()
    {
        return $this->hasMany(StudentSubjectExemption::class);
    }

    public function portalUser()
    {
        return $this->hasOne(User::class);
    }
}
