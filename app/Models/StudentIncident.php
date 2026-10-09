<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class StudentIncident extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'student_id', 'student_registration_id', 'academic_year_id', 'grade_id', 'section_id', 'action_category_id', 'action_type_id', 'incident_date', 'incident_time', 'location', 'description', 'witnesses', 'action_taken', 'consequence', 'follow_up', 'parent_notified', 'attachment_path', 'notes', 'status', 'reported_by_teacher_id', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['incident_date' => 'date', 'parent_notified' => 'boolean'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function registration()
    {
        return $this->belongsTo(StudentRegistration::class, 'student_registration_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function category()
    {
        return $this->belongsTo(ActionCategory::class, 'action_category_id');
    }

    public function type()
    {
        return $this->belongsTo(ActionType::class, 'action_type_id');
    }

    public function reporter()
    {
        return $this->belongsTo(Teacher::class, 'reported_by_teacher_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
