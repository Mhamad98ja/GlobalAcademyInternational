<?php
declare(strict_types=1);
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
class BookDistribution extends Model { use HasPublicId,BelongsToBranch; protected $fillable=['student_id','student_registration_id','academic_year_id','grade_id','section_id','invoice_id','distributed_on','status','notes','distributed_by']; protected function casts():array{return ['distributed_on'=>'date'];} public function student(){return $this->belongsTo(Student::class);} public function registration(){return $this->belongsTo(StudentRegistration::class,'student_registration_id');} public function invoice(){return $this->belongsTo(Invoice::class);} public function items(){return $this->hasMany(BookDistributionItem::class);} public function distributedBy(){return $this->belongsTo(User::class,'distributed_by');} }
