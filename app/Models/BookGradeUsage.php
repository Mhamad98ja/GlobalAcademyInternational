<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookGradeUsage extends Model
{
    protected $fillable = ['book_id', 'grade_id', 'audience'];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }
}
