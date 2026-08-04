<?php
declare(strict_types=1);
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreStudentRequest extends FormRequest { public function authorize():bool{return $this->user()?->hasPermission('students.manage')??false;} public function rules():array{return ['student_number'=>['nullable','string','max:50',Rule::unique('students','student_number')->ignore($this->route('student'))],'registration_number'=>['nullable','string','max:50'],'first_name'=>['required','string','max:100'],'middle_name'=>['nullable','string','max:100'],'last_name'=>['required','string','max:100'],'gender'=>['nullable',Rule::in(['male','female','other'])],'date_of_birth'=>['nullable','date','before:today'],'phone'=>['nullable','string','max:30'],'email'=>['nullable','email','max:255'],'address'=>['nullable','string','max:2000'],'enrollment_date'=>['nullable','date'],'previous_school'=>['nullable','string','max:255'],'notes'=>['nullable','string','max:3000']];} }
