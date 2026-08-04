<?php
declare(strict_types=1);
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
class DeferredPaymentApproval extends Model { use HasPublicId; protected $fillable=['student_id','student_registration_id','invoice_id','administrator_id','amount_deferred','approval_date','expected_payment_date','reason','reference_number','document_path','status','follow_up_status','notes','created_by']; protected function casts():array{return ['amount_deferred'=>'decimal:2','approval_date'=>'date','expected_payment_date'=>'date'];} public function invoice(){return $this->belongsTo(Invoice::class);} public function student(){return $this->belongsTo(Student::class);} }
