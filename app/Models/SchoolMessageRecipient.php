<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolMessageRecipient extends Model
{
    protected $fillable = ['school_message_id', 'recipient_id', 'read_at', 'is_archived'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'is_archived' => 'boolean'];
    }

    public function message()
    {
        return $this->belongsTo(SchoolMessage::class, 'school_message_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
