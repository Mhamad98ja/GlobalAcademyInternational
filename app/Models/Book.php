<?php
declare(strict_types=1);
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Book extends Model { use HasPublicId,SoftDeletes; protected $fillable=['sku','isbn','barcode','title','subtitle','author','publisher','edition','subject','language','category','purchase_price','selling_price','tax_rate','minimum_stock','reserved_quantity','shelf_location','cover_path','is_active','notes']; protected $appends=['available_quantity']; protected function casts():array{return ['purchase_price'=>'decimal:2','selling_price'=>'decimal:2','tax_rate'=>'decimal:2','is_active'=>'boolean'];} public function scopeActive($query){return $query->where('is_active',true);} public function getAvailableQuantityAttribute():int{return $this->stock_on_hand-$this->reserved_quantity;} public function movements(){return $this->hasMany(StockMovement::class);} }
