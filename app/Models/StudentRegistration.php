<?php
declare(strict_types=1);
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
class StudentRegistration extends Model { use HasPublicId; protected $fillable=['student_id','academic_year_id','grade_id','section_id','teacher_id','status','registered_on','notes','created_by']; protected function casts():array{return ['registered_on'=>'date'];} public function student(){return $this->belongsTo(Student::class);} public function academicYear(){return $this->belongsTo(AcademicYear::class);} public function grade(){return $this->belongsTo(Grade::class);} public function section(){return $this->belongsTo(Section::class);} public function teacher(){return $this->belongsTo(Teacher::class);} }
