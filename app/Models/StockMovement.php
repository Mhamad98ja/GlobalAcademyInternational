<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StockMovement extends Model { protected $fillable=['book_id','type','quantity_before','quantity_change','quantity_after','reference_type','reference_id','academic_year_id','user_id','reason','notes']; public function book(){return $this->belongsTo(Book::class);} public function user(){return $this->belongsTo(User::class);} public function reference(){return $this->morphTo();} }
