<?php
declare(strict_types=1);
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
class Invoice extends Model { use HasPublicId,BelongsToBranch; protected $fillable=['invoice_number','student_id','student_registration_id','responsible_person_id','academic_year_id','grade_id','section_id','invoice_date','due_date','subtotal','discount_total','tax_total','total','amount_paid','balance_due','payment_status','status','notes','created_by','approved_by','cancelled_at','cancelled_by','cancellation_reason']; protected function casts():array{return ['invoice_date'=>'date','due_date'=>'date','cancelled_at'=>'datetime','subtotal'=>'decimal:2','discount_total'=>'decimal:2','tax_total'=>'decimal:2','total'=>'decimal:2','amount_paid'=>'decimal:2','balance_due'=>'decimal:2'];} public function student(){return $this->belongsTo(Student::class);} public function registration(){return $this->belongsTo(StudentRegistration::class,'student_registration_id');} public function grade(){return $this->belongsTo(Grade::class);} public function section(){return $this->belongsTo(Section::class);} public function items(){return $this->hasMany(InvoiceItem::class);} public function payments(){return $this->hasMany(Payment::class);} public function responsiblePerson(){return $this->belongsTo(ResponsiblePerson::class);} }
