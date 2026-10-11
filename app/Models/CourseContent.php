<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class CourseContent extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'teacher_assignment_id', 'title', 'content', 'content_type', 'resource_url', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'available_from', 'due_on', 'estimated_minutes', 'disable_response', 'published_at', 'created_by'];

    protected function casts(): array
    {
        return ['available_from' => 'datetime', 'due_on' => 'date', 'estimated_minutes' => 'integer', 'disable_response' => 'boolean', 'published_at' => 'datetime'];
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
