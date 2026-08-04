<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InvoiceItem extends Model { protected $fillable=['invoice_id','book_id','description','quantity','unit_price','discount_amount','tax_rate','tax_amount','line_total']; protected function casts():array{return ['unit_price'=>'decimal:2','discount_amount'=>'decimal:2','tax_rate'=>'decimal:2','tax_amount'=>'decimal:2','line_total'=>'decimal:2'];} public function book(){return $this->belongsTo(Book::class);} public function invoice(){return $this->belongsTo(Invoice::class);} }
