<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    use HasPublicId;

    protected $fillable = ['school_level_id', 'name', 'sort_order'];

    public function level()
    {
        return $this->belongsTo(SchoolLevel::class, 'school_level_id');
    }

    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    public function teacherAssignments()
    {
        return $this->hasMany(TeacherAssignment::class);
    }
}
