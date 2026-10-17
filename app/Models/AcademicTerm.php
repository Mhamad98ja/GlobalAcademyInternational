<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class AcademicTerm extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'academic_year_id', 'name', 'type', 'starts_on', 'ends_on', 'status', 'sort_order'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }

    public static function activeForYear(?int $academicYearId): ?self
    {
        if (! $academicYearId) {
            return null;
        }

        $openTerms = static::query()
            ->where('academic_year_id', $academicYearId)
            ->where('status', 'open');

        return (clone $openTerms)
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->orderBy('ends_on')
            ->orderByDesc('sort_order')
            ->orderByDesc('id')
            ->first()
            ?? $openTerms->orderBy('sort_order')->orderBy('starts_on')->orderBy('id')->first();
    }
}
