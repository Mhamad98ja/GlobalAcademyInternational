<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentReversal extends Model { protected $fillable=['payment_id','amount','reason','approved_by','reversed_by','reversed_at']; protected function casts():array{return ['amount'=>'decimal:2','reversed_at'=>'datetime'];} public function payment(){return $this->belongsTo(Payment::class);} }
