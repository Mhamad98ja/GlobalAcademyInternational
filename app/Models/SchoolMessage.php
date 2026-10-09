<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class SchoolMessage extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'sender_id', 'subject', 'body', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipients()
    {
        return $this->hasMany(SchoolMessageRecipient::class);
    }
}
