<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class TimetableEntry extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'academic_year_id', 'teacher_assignment_id', 'teacher_id', 'subject_id', 'grade_id', 'section_id', 'day_of_week', 'period_number', 'is_parallel', 'room'];

    protected function casts(): array
    {
        return ['is_parallel' => 'boolean'];
    }

    public function assignment()
    {
        return $this->belongsTo(TeacherAssignment::class, 'teacher_assignment_id');
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

    public function substitutions()
    {
        return $this->hasMany(TeacherSubstitution::class);
    }
}
