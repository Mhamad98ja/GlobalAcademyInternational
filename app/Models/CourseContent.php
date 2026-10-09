<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class CourseContent extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'teacher_assignment_id', 'title', 'content', 'content_type', 'resource_url', 'available_from', 'due_on', 'published_at', 'created_by'];

    protected function casts(): array
    {
        return ['available_from' => 'datetime', 'due_on' => 'date', 'published_at' => 'datetime'];
    }

    public function assignment()
    {
        return $this->belongsTo(TeacherAssignment::class, 'teacher_assignment_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
