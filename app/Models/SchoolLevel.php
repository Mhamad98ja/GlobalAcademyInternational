<?php
declare(strict_types=1);
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
class SchoolLevel extends Model { use HasPublicId; protected $fillable=['name','sort_order']; public function grades(){return $this->hasMany(Grade::class);} }
