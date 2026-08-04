<?php
declare(strict_types=1);
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
class Payment extends Model { use HasPublicId; protected $fillable=['payment_number','invoice_id','student_id','payment_date','amount','method','cashbox','reference_number','status','idempotency_key','notes','received_by']; protected function casts():array{return ['payment_date'=>'date','amount'=>'decimal:2'];} public function invoice(){return $this->belongsTo(Invoice::class);} public function student(){return $this->belongsTo(Student::class);} public function reversal(){return $this->hasOne(PaymentReversal::class);} }
