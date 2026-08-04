<?php
declare(strict_types=1);
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class ResponsiblePerson extends Model { use HasPublicId,SoftDeletes; protected $table='responsible_persons'; protected $fillable=['teacher_id','full_name','type','employee_number','phone','email','address','notes']; public function teacher(){return $this->belongsTo(Teacher::class);} public function students(){return $this->belongsToMany(Student::class,'responsible_person_student')->withPivot(['relationship','is_primary'])->withTimestamps();} }
