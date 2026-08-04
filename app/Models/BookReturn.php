<?php
declare(strict_types=1);
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
class BookReturn extends Model { use HasPublicId; protected $fillable=['student_id','invoice_id','returned_on','refund_total','status','notes','accepted_by']; protected function casts():array{return ['returned_on'=>'date','refund_total'=>'decimal:2'];} public function items(){return $this->hasMany(BookReturnItem::class);} public function invoice(){return $this->belongsTo(Invoice::class);} public function student(){return $this->belongsTo(Student::class);} }
