<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class GradeChangeAudit extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'grade_entry_id', 'changed_by', 'old_values', 'new_values', 'reason'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    public function gradeEntry()
    {
        return $this->belongsTo(GradeEntry::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
