<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ClassBookListItem extends Model { protected $fillable=['class_book_list_id','book_id','quantity','is_mandatory','price_type','unit_price','teacher_child_discount']; protected function casts():array{return ['is_mandatory'=>'boolean','unit_price'=>'decimal:2','teacher_child_discount'=>'decimal:2'];} public function book(){return $this->belongsTo(Book::class);} }
