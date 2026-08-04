<?php
declare(strict_types=1);
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Teacher extends Model { use HasPublicId,SoftDeletes; protected $fillable=['employee_number','first_name','last_name','phone','email','address','is_active']; protected function casts(): array{return ['is_active'=>'boolean'];} public function getFullNameAttribute():string{return trim($this->first_name.' '.$this->last_name);} }
