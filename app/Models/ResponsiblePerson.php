<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResponsiblePerson extends Model
{
    use HasPublicId,SoftDeletes;

    protected $table = 'responsible_persons';

    protected $fillable = ['teacher_id', 'parent_code', 'title', 'full_name', 'first_name', 'middle_name', 'last_name', 'type', 'blood_type', 'religion', 'family_type', 'occupation', 'occupation_details', 'employee_number', 'phone', 'email', 'register_number', 'district', 'town', 'nationality', 'secondary_nationality', 'is_alumni', 'is_deceased', 'employment_status', 'employer', 'position', 'work_address', 'family_status', 'is_second_marriage', 'address', 'area', 'street', 'area_number', 'street_number', 'building_name', 'building_number', 'apartment', 'notes'];

    protected function casts(): array
    {
        return ['is_alumni' => 'boolean', 'is_deceased' => 'boolean', 'is_second_marriage' => 'boolean'];
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'responsible_person_student')->withPivot(['relationship', 'is_primary'])->withTimestamps();
    }
}
