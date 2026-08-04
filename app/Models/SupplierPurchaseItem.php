<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupplierPurchaseItem extends Model { protected $fillable=['supplier_purchase_id','book_id','quantity_ordered','quantity_received','unit_price','discount_amount','tax_rate','line_total']; protected function casts():array{return ['unit_price'=>'decimal:2','discount_amount'=>'decimal:2','tax_rate'=>'decimal:2','line_total'=>'decimal:2'];} public function purchase(){return $this->belongsTo(SupplierPurchase::class,'supplier_purchase_id');} public function book(){return $this->belongsTo(Book::class);} }
