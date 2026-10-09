<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class ExamAbsence extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'student_id', 'student_registration_id', 'assessment_id', 'academic_year_id', 'academic_term_id', 'subject_id', 'grade_id', 'section_id', 'absence_date', 'reason', 'is_excused', 'makeup_required', 'makeup_exam_date', 'medical_attachment_path', 'notes', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['absence_date' => 'date', 'makeup_exam_date' => 'date', 'is_excused' => 'boolean', 'makeup_required' => 'boolean'];
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

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
