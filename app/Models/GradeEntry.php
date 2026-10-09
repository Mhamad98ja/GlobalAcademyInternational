<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class GradeEntry extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'student_id', 'student_registration_id', 'assessment_id', 'teacher_assignment_id', 'teacher_id', 'score', 'notes', 'status', 'is_locked', 'recorded_by', 'published_at', 'published_by'];

    protected function casts(): array
    {
        return ['score' => 'decimal:2', 'is_locked' => 'boolean', 'published_at' => 'datetime'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function registration()
    {
        return $this->belongsTo(StudentRegistration::class, 'student_registration_id');
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function assignment()
    {
        return $this->belongsTo(TeacherAssignment::class, 'teacher_assignment_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function changes()
    {
        return $this->hasMany(GradeChangeAudit::class);
    }
}
