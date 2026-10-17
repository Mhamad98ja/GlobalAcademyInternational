<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentModificationRequest extends Model
{
    protected $fillable = ['assessment_id', 'requested_by', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'response'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
