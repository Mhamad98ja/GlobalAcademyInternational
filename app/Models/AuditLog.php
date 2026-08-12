<?php
declare(strict_types=1);
namespace App\Models;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model { use BelongsToBranch; protected $fillable=['branch_id','user_id','event','auditable_type','auditable_id','old_values','new_values','reason','ip_address','user_agent']; protected function casts():array{return ['old_values'=>'array','new_values'=>'array'];} public function auditable(){return $this->morphTo();} public function user(){return $this->belongsTo(User::class);} }
