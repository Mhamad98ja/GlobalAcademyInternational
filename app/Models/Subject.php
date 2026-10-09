<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'code', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function assignments()
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }

    public function timetableEntries()
    {
        return $this->hasMany(TimetableEntry::class);
    }
}
