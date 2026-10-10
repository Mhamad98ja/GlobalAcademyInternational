<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'academic_year_id', 'academic_term_id', 'teacher_assignment_id', 'subject_id', 'grade_id', 'section_id', 'name', 'assessment_column', 'type', 'maximum_score', 'weight', 'assessment_date', 'status', 'review_status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'notes', 'online_quiz', 'per_question', 'created_by'];

    protected function casts(): array
    {
        return ['assessment_date' => 'date', 'reviewed_at' => 'datetime', 'maximum_score' => 'decimal:2', 'weight' => 'decimal:3', 'online_quiz' => 'boolean', 'per_question' => 'boolean'];
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term()
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }

    public function assignment()
    {
        return $this->belongsTo(TeacherAssignment::class, 'teacher_assignment_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function grades()
    {
        return $this->hasMany(GradeEntry::class);
    }

    public function examAbsences()
    {
        return $this->hasMany(ExamAbsence::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
