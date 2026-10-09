<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'academic_year_id', 'academic_term_id', 'subject_id', 'grade_id', 'section_id', 'name', 'type', 'maximum_score', 'weight', 'assessment_date', 'status', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['assessment_date' => 'date', 'maximum_score' => 'decimal:2', 'weight' => 'decimal:3'];
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term()
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
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
}
