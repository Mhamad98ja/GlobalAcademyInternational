<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class TimetablePeriod extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'academic_year_id', 'period_number', 'label', 'starts_at', 'ends_at', 'kind'];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
