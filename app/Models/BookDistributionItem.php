<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BookDistributionItem extends Model { protected $fillable=['book_distribution_id','book_id','quantity','unit_price','discount_amount','tax_rate','line_total','reason']; protected function casts():array{return ['unit_price'=>'decimal:2','discount_amount'=>'decimal:2','tax_rate'=>'decimal:2','line_total'=>'decimal:2'];} public function distribution(){return $this->belongsTo(BookDistribution::class,'book_distribution_id');} public function book(){return $this->belongsTo(Book::class);} }
