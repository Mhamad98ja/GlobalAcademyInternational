<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
final class StudentRegistrationService
{
    public function __construct(private readonly AuditService $audit) {}
    public function register(Student $student,array $data,User $user): StudentRegistration
    {
        return DB::transaction(function()use($student,$data,$user):StudentRegistration{$registration=StudentRegistration::create(['student_id'=>$student->id,'academic_year_id'=>$data['academic_year_id'],'grade_id'=>$data['grade_id'],'section_id'=>$data['section_id']??null,'teacher_id'=>$data['teacher_id']??null,'status'=>'active','registered_on'=>$data['registered_on'],'notes'=>$data['notes']??null,'created_by'=>$user->id]);$this->audit->log('student.registered',$registration,[],['student'=>$student->student_number]);return $registration;});
    }
}
