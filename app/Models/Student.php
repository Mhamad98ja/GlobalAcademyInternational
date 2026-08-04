<?php
declare(strict_types=1);
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Student extends Model { use HasPublicId,SoftDeletes; protected $fillable=['student_number','registration_number','first_name','middle_name','last_name','gender','date_of_birth','phone','email','address','enrollment_date','previous_school','notes']; protected function casts():array{return ['date_of_birth'=>'date','enrollment_date'=>'date'];} public function getFullNameAttribute():string{return trim(implode(' ',array_filter([$this->first_name,$this->middle_name,$this->last_name])));} public function registrations(){return $this->hasMany(StudentRegistration::class);} public function responsiblePersons(){return $this->belongsToMany(ResponsiblePerson::class,'responsible_person_student')->withPivot(['relationship','is_primary'])->withTimestamps();} public function invoices(){return $this->hasMany(Invoice::class);} }
