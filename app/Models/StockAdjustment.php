<?php
declare(strict_types=1);
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
class StockAdjustment extends Model { use HasPublicId,BelongsToBranch; protected $fillable=['book_id','quantity_change','reason','notes','approved_by','created_by']; public function book(){return $this->belongsTo(Book::class);} }
