<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\ClassBookList;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\ResponsiblePerson;
use App\Models\Role;
use App\Models\SchoolLevel;
use App\Models\Section;
use App\Models\Student;
use App\Models\Supplier;
use App\Models\Teacher;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\StudentRegistrationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboard.view'=>'View dashboard','students.view'=>'View students','students.manage'=>'Manage students','books.view'=>'View books','books.manage'=>'Manage books','class-lists.manage'=>'Manage class book lists','suppliers.view'=>'View suppliers','suppliers.manage'=>'Manage suppliers','purchases.manage'=>'Manage supplier purchases','distributions.create'=>'Create distributions','invoices.view'=>'View invoices','invoices.cancel'=>'Cancel invoices','payments.view'=>'View payments','payments.create'=>'Record payments','payments.reverse'=>'Reverse payments','inventory.view'=>'View inventory','inventory.adjust'=>'Adjust stock','stock.allow-negative'=>'Allow negative stock','returns.manage'=>'Manage returns','deferred.view'=>'View deferred approvals','deferred.create'=>'Create deferred approvals','deferred.approve'=>'Approve deferred payments','academic.manage'=>'Manage academic structure','people.manage'=>'Manage people','reports.view'=>'View reports','admin.manage'=>'Manage administration',
        ];
        foreach ($permissions as $name => $label) Permission::firstOrCreate(['name'=>$name],['label'=>$label]);
        $roles = [
            'super-administrator'=>['label'=>'Super Administrator','description'=>'Unrestricted system administrator','permissions'=>array_keys($permissions)],
            'school-administrator'=>['label'=>'School Administrator','description'=>'Student and daily operations administrator','permissions'=>['dashboard.view','students.view','students.manage','books.view','class-lists.manage','distributions.create','invoices.view','payments.view','payments.create','deferred.view','deferred.create','reports.view','people.manage']],
            'inventory-manager'=>['label'=>'Inventory Manager','description'=>'Stock and purchasing operations','permissions'=>['dashboard.view','books.view','books.manage','suppliers.view','suppliers.manage','purchases.manage','inventory.view','inventory.adjust','returns.manage','class-lists.manage']],
            'accountant'=>['label'=>'Accountant','description'=>'Invoices, payments and financial reports','permissions'=>['dashboard.view','invoices.view','payments.view','payments.create','payments.reverse','deferred.view','reports.view']],
            'auditor'=>['label'=>'Read-only Auditor','description'=>'Read-only reports and audit access','permissions'=>['dashboard.view','students.view','books.view','suppliers.view','invoices.view','payments.view','inventory.view','deferred.view','reports.view']],
        ];
        foreach($roles as $name=>$data){$role=Role::firstOrCreate(['name'=>$name],['label'=>$data['label'],'description'=>$data['description']]);$role->permissions()->sync(Permission::whereIn('name',$data['permissions'])->pluck('id'));}
        $admin=User::firstOrCreate(['email'=>env('SBM_ADMIN_EMAIL','admin@school.test')],['name'=>'System Administrator','password'=>Hash::make(env('SBM_ADMIN_PASSWORD','Password123@')),'is_active'=>true]);$admin->roles()->sync([Role::where('name','super-administrator')->value('id')]);
        $year=AcademicYear::firstOrCreate(['name'=>'2026–2027'],['starts_on'=>'2026-09-01','ends_on'=>'2027-06-30','status'=>'open','created_by'=>$admin->id]);
        $primary=SchoolLevel::firstOrCreate(['name'=>'Primary'],['sort_order'=>1]);$grade=Grade::firstOrCreate(['school_level_id'=>$primary->id,'name'=>'Grade 4'],['sort_order'=>4]);$section=Section::firstOrCreate(['grade_id'=>$grade->id,'name'=>'A'],['room'=>'P-401']);
        $teacher=Teacher::firstOrCreate(['employee_number'=>'T-001'],['first_name'=>'Maya','last_name'=>'Haddad','phone'=>'03 123 456','email'=>'maya.haddad@school.test','is_active'=>true]);$guardian=ResponsiblePerson::firstOrCreate(['phone'=>'03 555 011','full_name'=>'Nadia Khoury'],['type'=>'mother']);
        $student=Student::firstOrCreate(['student_number'=>'STU-2026-00001'],['registration_number'=>'R-2026-00001','first_name'=>'Karim','last_name'=>'Khoury','date_of_birth'=>'2016-03-14','phone'=>'03 555 011','enrollment_date'=>'2026-09-01']);$student->responsiblePersons()->syncWithoutDetaching([$guardian->id=>['relationship'=>'Mother','is_primary'=>true]]);if(!$student->registrations()->where('academic_year_id',$year->id)->exists()) app(StudentRegistrationService::class)->register($student,['academic_year_id'=>$year->id,'grade_id'=>$grade->id,'section_id'=>$section->id,'teacher_id'=>$teacher->id,'registered_on'=>'2026-09-01','notes'=>'Demo record'],$admin);
        $bookRows=[['sku'=>'MAT-G4-26','barcode'=>'978000000001','title'=>'Mathematics 4','author'=>'School Press','subject'=>'Mathematics','purchase_price'=>'8.00','selling_price'=>'12.00','minimum_stock'=>10,'stock'=>42],['sku'=>'ENG-G4-26','barcode'=>'978000000002','title'=>'English 4','author'=>'School Press','subject'=>'English','purchase_price'=>'7.50','selling_price'=>'11.50','minimum_stock'=>10,'stock'=>35],['sku'=>'SCI-G4-26','barcode'=>'978000000003','title'=>'Science 4','author'=>'School Press','subject'=>'Science','purchase_price'=>'9.00','selling_price'=>'13.50','minimum_stock'=>8,'stock'=>8],['sku'=>'ARB-G4-26','barcode'=>'978000000004','title'=>'Arabic Language 4','author'=>'School Press','subject'=>'Arabic','purchase_price'=>'7.00','selling_price'=>'10.00','minimum_stock'=>8,'stock'=>0]];
        foreach($bookRows as $row){$book=Book::firstOrCreate(['sku'=>$row['sku']],collect($row)->except('stock')->all());if($book->stock_on_hand===0 && $row['stock']>0)app(InventoryService::class)->change($book,(int)$row['stock'],'opening_stock',null,$year,$admin,'Development opening stock');}
        $list=ClassBookList::firstOrCreate(['academic_year_id'=>$year->id,'grade_id'=>$grade->id,'section_id'=>$section->id,'name'=>'Grade 4 complete package'],['is_active'=>true]);foreach(Book::orderBy('id')->take(3)->get() as $book)$list->items()->firstOrCreate(['book_id'=>$book->id],['quantity'=>1,'is_mandatory'=>true,'price_type'=>'paid','unit_price'=>$book->selling_price,'teacher_child_discount'=>0]);
        Supplier::firstOrCreate(['code'=>'SUP-001'],['name'=>'School Press','contact_person'=>'Rami Saab','phone'=>'01 555 770','email'=>'orders@schoolpress.test','payment_terms'=>'Net 30','is_active'=>true]);
    }
}
