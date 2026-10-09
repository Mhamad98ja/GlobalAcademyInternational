<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use HasPublicId,SoftDeletes;

    protected $fillable = ['employee_number', 'first_name', 'last_name', 'phone', 'email', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function assignments()
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    public function attendances()
    {
        return $this->hasMany(TeacherAttendance::class);
    }

    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function timetableEntries()
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function substitutions()
    {
        return $this->hasMany(TeacherSubstitution::class, 'substitute_teacher_id');
    }

    public function performanceEvaluations()
    {
        return $this->hasMany(StudentPerformanceEvaluation::class);
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }
}
