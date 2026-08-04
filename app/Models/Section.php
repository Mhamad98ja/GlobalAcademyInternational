<?php
declare(strict_types=1);
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
class Section extends Model { use HasPublicId; protected $fillable=['grade_id','name','room']; public function grade(){return $this->belongsTo(Grade::class);} }
