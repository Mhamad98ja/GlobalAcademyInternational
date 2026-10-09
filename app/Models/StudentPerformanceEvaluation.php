<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class StudentPerformanceEvaluation extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'student_id', 'student_registration_id', 'teacher_assignment_id', 'teacher_id', 'category', 'rating', 'observed_on', 'note', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['observed_on' => 'date'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function registration()
    {
        return $this->belongsTo(StudentRegistration::class, 'student_registration_id');
    }

    public function assignment()
    {
        return $this->belongsTo(TeacherAssignment::class, 'teacher_assignment_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
