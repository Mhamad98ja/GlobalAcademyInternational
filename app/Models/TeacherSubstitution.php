<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class TeacherSubstitution extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'substitution_date', 'timetable_entry_id', 'absent_teacher_id', 'substitute_teacher_id', 'status', 'recommendation_details', 'override_reason', 'assigned_by'];

    protected function casts(): array
    {
        return ['substitution_date' => 'date', 'recommendation_details' => 'array'];
    }

    public function entry()
    {
        return $this->belongsTo(TimetableEntry::class, 'timetable_entry_id');
    }

    public function absentTeacher()
    {
        return $this->belongsTo(Teacher::class, 'absent_teacher_id');
    }

    public function substituteTeacher()
    {
        return $this->belongsTo(Teacher::class, 'substitute_teacher_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
