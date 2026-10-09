<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class TeacherAssignment extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'academic_year_id', 'teacher_id', 'subject_id', 'grade_id', 'section_id', 'required_periods_per_week', 'weekly_period_limit', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
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

    public function gradeEntries()
    {
        return $this->hasMany(GradeEntry::class);
    }

    public function timetableEntries()
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function courseContents()
    {
        return $this->hasMany(CourseContent::class);
    }

    public function performanceEvaluations()
    {
        return $this->hasMany(StudentPerformanceEvaluation::class);
    }
}
