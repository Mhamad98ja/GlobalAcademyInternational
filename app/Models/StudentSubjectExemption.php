<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class StudentSubjectExemption extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'student_id', 'student_registration_id', 'subject_id', 'reason', 'approved_by', 'created_by'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function registration()
    {
        return $this->belongsTo(StudentRegistration::class, 'student_registration_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
