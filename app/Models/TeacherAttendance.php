<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class TeacherAttendance extends Model
{
    use BelongsToBranch, HasPublicId;

    public const ABSENCE_STATUSES = ['excused_absence', 'unexcused_absence'];

    protected $fillable = ['branch_id', 'teacher_id', 'attendance_date', 'checked_in_at', 'checked_out_at', 'status', 'late_minutes', 'early_leave_minutes', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return ['attendance_date' => 'date'];
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isAbsent(): bool
    {
        return in_array($this->status, self::ABSENCE_STATUSES, true);
    }
}
