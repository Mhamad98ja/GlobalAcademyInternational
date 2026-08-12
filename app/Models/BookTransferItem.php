<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookTransferItem extends Model
{
    protected $fillable = ['book_transfer_id', 'book_id', 'quantity'];

    public function transfer(): BelongsTo { return $this->belongsTo(BookTransfer::class, 'book_transfer_id'); }
    public function book(): BelongsTo { return $this->belongsTo(Book::class); }
}
