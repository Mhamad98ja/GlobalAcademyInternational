<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class StudentSupportAssignment extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'student_id', 'user_id', 'role', 'notes', 'status', 'assigned_on', 'ended_on'];

    protected function casts(): array
    {
        return ['assigned_on' => 'date', 'ended_on' => 'date'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
