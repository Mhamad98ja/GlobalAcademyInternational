<?php
declare(strict_types=1);
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
class AcademicYear extends Model { use HasPublicId; protected $fillable=['name','starts_on','ends_on','status','created_by']; protected function casts(): array{return ['starts_on'=>'date','ends_on'=>'date'];} public function registrations(){return $this->hasMany(StudentRegistration::class);} }
