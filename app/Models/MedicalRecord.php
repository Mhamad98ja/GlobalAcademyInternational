<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'student_id', 'teacher_id', 'type', 'recorded_on', 'title', 'description', 'restrictions', 'document_path', 'document_name', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['recorded_on' => 'date'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
