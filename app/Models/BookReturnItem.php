<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BookReturnItem extends Model { protected $fillable=['book_return_id','book_distribution_item_id','book_id','quantity','condition','reason','restock','refund_amount']; protected function casts():array{return ['restock'=>'boolean','refund_amount'=>'decimal:2'];} public function book(){return $this->belongsTo(Book::class);} }
