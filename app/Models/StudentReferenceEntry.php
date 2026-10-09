<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentReferenceEntry extends Model
{
    protected $fillable = [
        'reference',
        'category',
        'code',
        'value',
        'value_en',
        'value_ar',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
