<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookReturn;
use App\Models\ClassBookList;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolLevel;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Models\Supplier;
use App\Models\SupplierPurchase;
use App\Models\SupplierReturn;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BookDistributionService;
use App\Services\InventoryService;
use App\Services\PaymentService;
use App\Services\SupplierPaymentService;
use App\Services\SupplierPurchaseService;
use App\Services\SupplierReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CoreWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $role = Role::create(['name'=>'super-administrator','label'=>'Super Administrator']);
        $user = User::factory()->create(['is_active'=>true]);
        $user->roles()->attach($role);
        return $user;
    }

    private function registration(User $user): StudentRegistration
    {
        $year=AcademicYear::create(['name'=>'2026–2027','starts_on'=>'2026-09-01','ends_on'=>'2027-06-30','status'=>'open','created_by'=>$user->id]);
        $level=SchoolLevel::create(['name'=>'Primary']);$grade=Grade::create(['school_level_id'=>$level->id,'name'=>'Grade 4']);$section=Section::create(['grade_id'=>$grade->id,'name'=>'A']);$student=Student::create(['student_number'=>'STU-001','first_name'=>'Lina','last_name'=>'Haddad']);
        return StudentRegistration::create(['student_id'=>$student->id,'academic_year_id'=>$year->id,'grade_id'=>$grade->id,'section_id'=>$section->id,'status'=>'active','registered_on'=>'2026-09-01','created_by'=>$user->id]);
    }

    public function test_distribution_decreases_stock_and_creates_an_invoice(): void
    {
        $user=$this->administrator();$registration=$this->registration($user);$book=Book::create(['sku'=>'B-001','barcode'=>'978000001','title'=>'Math','selling_price'=>'12.00','tax_rate'=>'0.00','minimum_stock'=>1]);app(InventoryService::class)->change($book,5,'opening_stock',null,$registration->academicYear,$user,'Initial stock');
        $distribution=app(BookDistributionService::class)->distribute($registration,[['book_id'=>$book->id,'quantity'=>2]],[],$user);
        $this->assertDatabaseHas('books',['id'=>$book->id,'stock_on_hand'=>3]);$this->assertDatabaseHas('stock_movements',['book_id'=>$book->id,'type'=>'student_distribution','quantity_change'=>-2]);$this->assertDatabaseHas('invoices',['id'=>$distribution->invoice_id,'total'=>'24.00','balance_due'=>'24.00']);
    }

    public function test_partial_payment_recalculates_balance_and_overpayment_is_rejected(): void
    {
        $user=$this->administrator();$registration=$this->registration($user);$invoice=Invoice::create(['invoice_number'=>'INV-001','student_id'=>$registration->student_id,'student_registration_id'=>$registration->id,'academic_year_id'=>$registration->academic_year_id,'grade_id'=>$registration->grade_id,'section_id'=>$registration->section_id,'invoice_date'=>'2026-09-01','subtotal'=>'20.00','total'=>'20.00','balance_due'=>'20.00','created_by'=>$user->id]);
        app(PaymentService::class)->record($invoice,['amount'=>'7.50','payment_date'=>'2026-09-02','method'=>'cash','idempotency_key'=>(string)Str::uuid()],$user);
        $this->assertDatabaseHas('invoices',['id'=>$invoice->id,'amount_paid'=>'7.50','balance_due'=>'12.50','payment_status'=>'partially_paid']);
        $this->expectException(ValidationException::class);app(PaymentService::class)->record($invoice->fresh(),['amount'=>'13.00','payment_date'=>'2026-09-03','method'=>'cash','idempotency_key'=>(string)Str::uuid()],$user);
    }

    public function test_supplier_purchase_percentage_discount_updates_the_catalogue_purchase_price(): void
    {
        $user = $this->administrator();
        $supplier = Supplier::create(['code' => 'SUP-001', 'name' => 'School Press', 'is_active' => true]);
        $book = Book::create(['sku' => 'B-002', 'barcode' => '978000002', 'title' => 'Science', 'purchase_price' => '5.00', 'selling_price' => '15.00', 'minimum_stock' => 1]);

        $purchase = app(SupplierPurchaseService::class)->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-09-01',
            'items' => [[
                'book_id' => $book->id,
                'quantity_ordered' => 2,
                'unit_purchase_price' => '10.00',
                'discount_percentage' => '12.50',
                'tax_rate' => '10.00',
            ]],
        ], $user);

        $this->assertDatabaseHas('books', ['id' => $book->id, 'purchase_price' => '10.00']);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock_on_hand' => 2]);
        $this->assertDatabaseHas('supplier_purchase_items', ['supplier_purchase_id' => $purchase->id, 'discount_amount' => '2.50', 'line_total' => '19.25']);
        $this->assertDatabaseHas('supplier_purchase_items', ['supplier_purchase_id' => $purchase->id, 'quantity_received' => 2]);
        $this->assertDatabaseHas('supplier_purchases', ['id' => $purchase->id, 'discount_total' => '2.50', 'total' => '19.25', 'status' => 'received']);
        $this->assertDatabaseHas('stock_movements', ['book_id' => $book->id, 'type' => 'supplier_receipt', 'quantity_change' => 2]);
    }

    public function test_supplier_payment_updates_the_invoice_and_supplier_balance(): void
    {
        $user = $this->administrator();
        $supplier = Supplier::create(['code' => 'SUP-002', 'name' => 'Book Importer', 'opening_balance' => '100.00', 'is_active' => true]);
        $book = Book::create(['sku' => 'B-003', 'barcode' => '978000003', 'title' => 'History', 'selling_price' => '15.00', 'minimum_stock' => 1]);
        $purchase = app(SupplierPurchaseService::class)->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-09-01',
            'supplier_invoice_number' => 'SUP-INV-900',
            'items' => [[
                'book_id' => $book->id,
                'quantity_ordered' => 1,
                'unit_purchase_price' => '800.00',
                'discount_percentage' => '0',
                'tax_rate' => '0',
            ]],
        ], $user);

        app(SupplierPaymentService::class)->record($purchase, [
            'amount' => '300.00',
            'paid_on' => '2026-09-02',
            'method' => 'check',
            'reference_number' => 'CHK-1001',
        ], $user);

        $this->assertDatabaseHas('supplier_payments', ['supplier_purchase_id' => $purchase->id, 'amount' => '300.00', 'reference_number' => 'CHK-1001']);
        $this->assertDatabaseHas('supplier_purchases', ['id' => $purchase->id, 'amount_paid' => '300.00', 'payment_status' => 'partially_paid']);
        $this->assertSame('500.00', $purchase->fresh()->balance_due);

        $supplier = Supplier::query()
            ->withSum('purchases as purchases_total_sum', 'total')
            ->withSum('purchases as purchases_paid_sum', 'amount_paid')
            ->findOrFail($supplier->id);
        $this->assertSame('600.00', $supplier->account_balance);

        $this->expectException(ValidationException::class);
        app(SupplierPaymentService::class)->record($purchase->fresh(), [
            'amount' => '500.01',
            'paid_on' => '2026-09-03',
            'method' => 'cash',
        ], $user);
    }

    public function test_supplier_return_reduces_stock_and_the_purchase_balance(): void
    {
        $user = $this->administrator();
        $supplier = Supplier::create(['code' => 'SUP-RETURN', 'name' => 'Returnable Books', 'is_active' => true]);
        $book = Book::create(['sku' => 'B-RETURN', 'barcode' => '978000030', 'title' => 'Returned title', 'selling_price' => '15.00', 'minimum_stock' => 1]);
        $purchase = app(SupplierPurchaseService::class)->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-09-01',
            'items' => [[
                'book_id' => $book->id,
                'quantity_ordered' => 5,
                'unit_purchase_price' => '10.00',
                'discount_percentage' => '0',
                'tax_rate' => '0',
            ]],
        ], $user);
        $purchaseItem = $purchase->items()->firstOrFail();

        $this->actingAs($user)->get(route('supplier-returns.create', $purchase))
            ->assertOk()
            ->assertSee('Return books to supplier')
            ->assertSee('Available to return');

        $return = app(SupplierReturnService::class)->create($purchase, [[
            'supplier_purchase_item_id' => $purchaseItem->id,
            'quantity' => 2,
            'reason' => 'Wrong edition',
        ]], [
            'returned_on' => '2026-09-02',
            'reference_number' => 'CN-101',
        ], $user);

        $this->assertDatabaseHas('supplier_returns', ['id' => $return->id, 'credit_total' => '20.00', 'reference_number' => 'CN-101']);
        $this->assertDatabaseHas('supplier_return_items', ['supplier_return_id' => $return->id, 'book_id' => $book->id, 'quantity' => 2, 'credit_total' => '20.00']);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock_on_hand' => 3]);
        $this->assertDatabaseHas('stock_movements', ['book_id' => $book->id, 'type' => 'supplier_return', 'quantity_change' => -2]);
        $this->assertSame('20.00', $purchase->fresh()->return_credit_total);
        $this->assertSame('30.00', $purchase->fresh()->balance_due);
        $this->assertSame('30.00', $supplier->fresh()->account_balance);
        $this->actingAs($user)->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Return credits')
            ->assertSee('20.00');
        $this->actingAs($user)->get(route('purchases.show', $purchase))
            ->assertOk()
            ->assertSee('Supplier return history')
            ->assertSee('CN-101');

        $this->expectException(ValidationException::class);
        app(SupplierReturnService::class)->create($purchase->fresh(), [[
            'supplier_purchase_item_id' => $purchaseItem->id,
            'quantity' => 4,
            'reason' => 'Too many',
        ]], ['returned_on' => '2026-09-03'], $user);
    }

    public function test_paid_package_books_always_use_their_current_catalogue_sale_price(): void
    {
        $user = $this->administrator();
        $registration = $this->registration($user);
        $arabic = Book::create(['sku' => 'B-AR', 'barcode' => '978000020', 'title' => 'Arabic', 'selling_price' => '20.00', 'minimum_stock' => 1]);
        $english = Book::create(['sku' => 'B-EN', 'barcode' => '978000021', 'title' => 'English', 'selling_price' => '2.00', 'minimum_stock' => 1]);
        app(InventoryService::class)->change($arabic, 1, 'opening_stock', null, $registration->academicYear, $user, 'Initial stock');
        app(InventoryService::class)->change($english, 1, 'opening_stock', null, $registration->academicYear, $user, 'Initial stock');
        $package = ClassBookList::create([
            'academic_year_id' => $registration->academic_year_id,
            'grade_id' => $registration->grade_id,
            'section_id' => $registration->section_id,
            'name' => 'Grade 4 package',
            'is_active' => true,
        ]);
        $package->items()->createMany([
            ['book_id' => $arabic->id, 'quantity' => 1, 'price_type' => 'paid', 'unit_price' => '2.00', 'is_mandatory' => true],
            ['book_id' => $english->id, 'quantity' => 1, 'price_type' => 'paid', 'unit_price' => '20.00', 'is_mandatory' => true],
        ]);

        $distribution = app(BookDistributionService::class)->distribute($registration, [
            ['book_id' => $arabic->id, 'quantity' => 1],
            ['book_id' => $english->id, 'quantity' => 1],
        ], [], $user, null, $package);

        $this->assertDatabaseHas('book_distribution_items', ['book_distribution_id' => $distribution->id, 'book_id' => $arabic->id, 'unit_price' => '20.00']);
        $this->assertDatabaseHas('book_distribution_items', ['book_distribution_id' => $distribution->id, 'book_id' => $english->id, 'unit_price' => '2.00']);
        $this->assertDatabaseHas('invoices', ['id' => $distribution->invoice_id, 'total' => '22.00']);
    }

    public function test_books_and_book_packages_are_available_from_one_management_screen(): void
    {
        $user = $this->administrator();
        $registration = $this->registration($user);
        $book = Book::create(['sku' => 'B-COMBINED', 'barcode' => '978000031', 'title' => 'Combined catalogue title', 'selling_price' => '12.00', 'minimum_stock' => 1]);
        $package = ClassBookList::create([
            'academic_year_id' => $registration->academic_year_id,
            'grade_id' => $registration->grade_id,
            'section_id' => $registration->section_id,
            'name' => 'Combined package',
            'is_active' => true,
        ]);
        $package->items()->create(['book_id' => $book->id, 'quantity' => 1, 'is_mandatory' => true, 'price_type' => 'paid', 'unit_price' => '0.00']);

        $this->actingAs($user)->get(route('books.index'))
            ->assertOk()
            ->assertSee('Books & packages')
            ->assertSee('Combined catalogue title')
            ->assertSee('Book packages');
        $this->actingAs($user)->get(route('books.index', ['tab' => 'packages']))
            ->assertOk()
            ->assertSee('Combined package')
            ->assertSee('New package');
        $this->actingAs($user)->get(route('class-lists.index'))
            ->assertRedirect(route('books.index', ['tab' => 'packages']));
    }

    public function test_reports_apply_filters_that_match_the_selected_report(): void
    {
        $user = $this->administrator();
        $registration = $this->registration($user);
        $registration->student->update(['gender' => 'female', 'phone' => '03 123 456', 'email' => 'lina@example.test']);
        $otherGrade = Grade::create(['school_level_id' => $registration->grade->school_level_id, 'name' => 'Grade 5', 'sort_order' => 2]);
        $otherSection = Section::create(['grade_id' => $otherGrade->id, 'name' => 'B']);
        $otherStudent = Student::create(['student_number' => 'STU-REPORT-002', 'first_name' => 'Omar', 'last_name' => 'Saleh']);
        $otherRegistration = StudentRegistration::create([
            'student_id' => $otherStudent->id,
            'academic_year_id' => $registration->academic_year_id,
            'grade_id' => $otherGrade->id,
            'section_id' => $otherSection->id,
            'status' => 'active',
            'registered_on' => '2026-09-02',
            'created_by' => $user->id,
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-REPORT-001', 'student_id' => $registration->student_id, 'student_registration_id' => $registration->id,
            'academic_year_id' => $registration->academic_year_id, 'grade_id' => $registration->grade_id, 'section_id' => $registration->section_id,
            'invoice_date' => '2026-09-03', 'subtotal' => '20.00', 'total' => '20.00', 'balance_due' => '20.00', 'payment_status' => 'unpaid', 'created_by' => $user->id,
        ]);
        $otherInvoice = Invoice::create([
            'invoice_number' => 'INV-REPORT-002', 'student_id' => $otherStudent->id, 'student_registration_id' => $otherRegistration->id,
            'academic_year_id' => $registration->academic_year_id, 'grade_id' => $otherGrade->id, 'section_id' => $otherSection->id,
            'invoice_date' => '2026-09-04', 'subtotal' => '30.00', 'total' => '30.00', 'balance_due' => '30.00', 'payment_status' => 'unpaid', 'created_by' => $user->id,
        ]);
        Payment::create([
            'payment_number' => 'PAY-REPORT-001', 'invoice_id' => $invoice->id, 'student_id' => $registration->student_id,
            'payment_date' => '2026-09-05', 'amount' => '5.00', 'method' => 'cash', 'status' => 'valid',
            'idempotency_key' => (string) Str::uuid(), 'received_by' => $user->id,
        ]);
        Payment::create([
            'payment_number' => 'PAY-REPORT-002', 'invoice_id' => $otherInvoice->id, 'student_id' => $otherStudent->id,
            'payment_date' => '2026-09-06', 'amount' => '6.00', 'method' => 'card', 'status' => 'valid',
            'idempotency_key' => (string) Str::uuid(), 'received_by' => $user->id,
        ]);
        Book::create(['sku' => 'B-REPORT-LOW', 'barcode' => '978000032', 'title' => 'Out of stock report title', 'selling_price' => '10.00', 'minimum_stock' => 1]);
        $inStockBook = Book::create(['sku' => 'B-REPORT-OK', 'barcode' => '978000033', 'title' => 'In stock report title', 'selling_price' => '10.00', 'minimum_stock' => 1]);
        app(InventoryService::class)->change($inStockBook, 3, 'opening_stock', null, $registration->academicYear, $user, 'Report test stock');

        $this->actingAs($user)->get(route('reports.index', ['report' => 'students', 'grade_id' => $registration->grade_id]))
            ->assertOk()->assertSee('Lina Haddad')->assertDontSee('Omar Saleh');
        $this->actingAs($user)->get(route('reports.index', ['report' => 'student_information', 'gender' => 'female']))
            ->assertOk()->assertSee('Student general information')->assertSee('03 123 456')->assertDontSee('Omar Saleh');
        $this->actingAs($user)->get(route('reports.print', ['report' => 'class_sections', 'section_id' => $registration->section_id]))
            ->assertOk()->assertSee('Students by class and section report')->assertSee('Lina Haddad')->assertDontSee('Omar Saleh');
        $this->actingAs($user)->get(route('reports.index', ['report' => 'invoices', 'grade_id' => $registration->grade_id, 'date_from' => '2026-09-03', 'date_to' => '2026-09-03']))
            ->assertOk()->assertSee('INV-REPORT-001')->assertDontSee('INV-REPORT-002');
        $this->actingAs($user)->get(route('reports.print', ['report' => 'invoices', 'grade_id' => $registration->grade_id]))
            ->assertOk()->assertSee('INV-REPORT-001')->assertDontSee('INV-REPORT-002');
        $this->actingAs($user)->get(route('reports.index', ['report' => 'payments', 'grade_id' => $registration->grade_id, 'payment_method' => 'cash']))
            ->assertOk()->assertSee('PAY-REPORT-001')->assertDontSee('PAY-REPORT-002');
        $this->actingAs($user)->get(route('reports.index', ['report' => 'stock', 'stock_level' => 'out_of_stock']))
            ->assertOk()->assertSee('Out of stock report title')->assertDontSee('In stock report title');
    }

    public function test_student_gender_accepts_only_male_or_female(): void
    {
        $user = $this->administrator();

        $this->actingAs($user)->post(route('students.store'), [
            'student_number' => 'STU-GENDER-001',
            'first_name' => 'Rami',
            'last_name' => 'Mansour',
            'gender' => 'other',
        ])->assertSessionHasErrors('gender');
    }

    public function test_leaving_student_preserves_profile_and_withdraws_active_registration(): void
    {
        $user = $this->administrator();
        $registration = $this->registration($user);

        $this->actingAs($user)->get(route('students.leave.create', $registration->student))
            ->assertOk()
            ->assertSee('Mark as left school')
            ->assertSee($registration->academicYear->name);

        $this->actingAs($user)->post(route('students.leave.store', $registration->student), [
            'withdrawn_on' => '2026-08-04',
            'withdrawal_reason' => 'Transferred to another school',
        ])->assertRedirect(route('students.show', $registration->student));

        $this->assertDatabaseHas('students', ['id' => $registration->student_id, 'deleted_at' => null]);
        $this->assertDatabaseHas('student_registrations', [
            'id' => $registration->id,
            'status' => 'withdrawn',
            'withdrawn_on' => '2026-08-04',
            'withdrawal_reason' => 'Transferred to another school',
        ]);
        $this->actingAs($user)->get(route('students.show', $registration->student))
            ->assertOk()
            ->assertSee('Transferred to another school')
            ->assertSee('Withdrawn');
    }

    public function test_printable_invoices_and_reports_use_the_school_document_layout(): void
    {
        $user = $this->administrator();
        $registration = $this->registration($user);
        SystemSetting::updateOrCreate(['key' => 'school_name'], ['value' => 'Cedar Grove School']);
        SystemSetting::updateOrCreate(['key' => 'school_address'], ['value' => 'Beirut, Lebanon']);
        SystemSetting::updateOrCreate(['key' => 'school_phone'], ['value' => '+961 1 555 555']);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-PRINT-001', 'student_id' => $registration->student_id, 'student_registration_id' => $registration->id,
            'academic_year_id' => $registration->academic_year_id, 'grade_id' => $registration->grade_id, 'section_id' => $registration->section_id,
            'invoice_date' => '2026-09-01', 'subtotal' => '20.00', 'total' => '20.00', 'balance_due' => '20.00', 'created_by' => $user->id,
        ]);
        $invoice->items()->create(['description' => 'Mathematics 4', 'quantity' => 1, 'unit_price' => '20.00', 'line_total' => '20.00']);

        $this->actingAs($user)->get(route('invoices.print', $invoice))
            ->assertOk()
            ->assertSee('Cedar Grove School')
            ->assertSee('Student Book Invoice')
            ->assertSee('Parent / guardian acknowledgement');
        $this->actingAs($user)->get(route('reports.print', ['report' => 'students']))
            ->assertOk()
            ->assertSee('Cedar Grove School')
            ->assertSee('Official report')
            ->assertSee('Prepared by');
    }

    public function test_academic_years_levels_grades_and_sections_can_be_edited(): void
    {
        $user = $this->administrator();
        $year = AcademicYear::create(['name' => '2026-2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'created_by' => $user->id]);
        $level = SchoolLevel::create(['name' => 'Primary', 'sort_order' => 1]);
        $grade = Grade::create(['school_level_id' => $level->id, 'name' => 'Grade 4', 'sort_order' => 1]);
        $section = Section::create(['grade_id' => $grade->id, 'name' => 'A', 'room' => '101']);

        $this->actingAs($user)->put(route('academic.years.update', $year), [
            'name' => '2026-2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'status' => 'closed',
        ])->assertRedirect()->assertSessionHas('success');
        $this->actingAs($user)->put(route('academic.levels.update', $level), ['name' => 'Elementary', 'sort_order' => 2])->assertRedirect()->assertSessionHas('success');
        $this->actingAs($user)->put(route('academic.grades.update', $grade), ['school_level_id' => $level->id, 'name' => 'Grade 4A', 'sort_order' => 2])->assertRedirect()->assertSessionHas('success');
        $this->actingAs($user)->put(route('academic.sections.update', $section), ['grade_id' => $grade->id, 'name' => 'B', 'room' => '202'])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('academic_years', ['id' => $year->id, 'status' => 'closed']);
        $this->assertDatabaseHas('school_levels', ['id' => $level->id, 'name' => 'Elementary', 'sort_order' => 2]);
        $this->assertDatabaseHas('grades', ['id' => $grade->id, 'name' => 'Grade 4A', 'sort_order' => 2]);
        $this->assertDatabaseHas('sections', ['id' => $section->id, 'name' => 'B', 'room' => '202']);
    }

    public function test_academic_structure_page_renders_its_edit_controls(): void
    {
        $user = $this->administrator();
        $year = AcademicYear::create(['name' => '2027-2028', 'starts_on' => '2027-09-01', 'ends_on' => '2028-06-30', 'status' => 'draft', 'created_by' => $user->id]);
        $level = SchoolLevel::create(['name' => 'Primary', 'sort_order' => 1]);
        $grade = Grade::create(['school_level_id' => $level->id, 'name' => 'Grade 1', 'sort_order' => 1]);
        Section::create(['grade_id' => $grade->id, 'name' => 'A']);

        $this->actingAs($user)->get(route('academic.index'))
            ->assertOk()
            ->assertSee($year->name)
            ->assertSee('Edit academic year')
            ->assertSee('Edit level')
            ->assertSee('Edit grade')
            ->assertSee('Edit section');
    }

    public function test_dashboard_uses_the_open_academic_year_without_a_manual_selector(): void
    {
        $user = $this->administrator();
        $registration = $this->registration($user);

        $this->actingAs($user)->get(route('dashboard', ['academic_year_id' => 999999]))
            ->assertOk()
            ->assertSee('Active academic year:')
            ->assertSee($registration->academicYear->name)
            ->assertDontSee('All academic years')
            ->assertDontSee('href="'.route('purchases.index').'"', false)
            ->assertDontSee('href="'.route('invoices.index').'"', false)
            ->assertDontSee('href="'.route('returns.index').'"', false);
    }

    public function test_opening_a_new_academic_year_closes_the_previous_open_year(): void
    {
        $user = $this->administrator();
        $currentYear = AcademicYear::create(['name' => '2026-2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'created_by' => $user->id]);

        $this->actingAs($user)->post(route('academic.years.store'), [
            'name' => '2027-2028',
            'starts_on' => '2027-09-01',
            'ends_on' => '2028-06-30',
            'status' => 'open',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('academic_years', ['id' => $currentYear->id, 'status' => 'closed']);
        $this->assertDatabaseHas('academic_years', ['name' => '2027-2028', 'status' => 'open']);
    }

    public function test_operations_center_combines_purchases_invoices_and_returns_without_changing_their_workflows(): void
    {
        $user = $this->administrator();
        $registration = $this->registration($user);
        $supplier = Supplier::create(['code' => 'SUP-OPS', 'name' => 'Operations Press', 'is_active' => true]);
        $purchase = SupplierPurchase::create([
            'purchase_number' => 'PUR-OPS-001', 'supplier_id' => $supplier->id, 'academic_year_id' => $registration->academic_year_id,
            'purchase_date' => '2026-09-01', 'subtotal' => '100.00', 'discount_total' => '0.00', 'tax_total' => '0.00', 'total' => '100.00',
            'amount_paid' => '20.00', 'payment_status' => 'partially_paid', 'status' => 'received', 'created_by' => $user->id,
        ]);
        SupplierReturn::create([
            'supplier_purchase_id' => $purchase->id, 'supplier_id' => $supplier->id, 'academic_year_id' => $registration->academic_year_id,
            'returned_on' => '2026-09-04', 'credit_total' => '10.00', 'reference_number' => 'CN-OPS-001', 'status' => 'completed', 'returned_by' => $user->id,
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-OPS-001', 'student_id' => $registration->student_id, 'student_registration_id' => $registration->id,
            'academic_year_id' => $registration->academic_year_id, 'grade_id' => $registration->grade_id, 'section_id' => $registration->section_id,
            'invoice_date' => '2026-09-02', 'subtotal' => '30.00', 'total' => '30.00', 'balance_due' => '30.00', 'created_by' => $user->id,
        ]);
        BookReturn::create(['student_id' => $registration->student_id, 'invoice_id' => $invoice->id, 'returned_on' => '2026-09-03', 'refund_total' => '5.00', 'status' => 'accepted', 'accepted_by' => $user->id]);

        $this->actingAs($user)->get(route('operations.index'))
            ->assertOk()
            ->assertSee('Operations Center')
            ->assertSee('PUR-OPS-001')
            ->assertSee('INV-OPS-001')
            ->assertSee('Book return')
            ->assertSee('Supplier return')
            ->assertSee('CN-OPS-001');
        $this->actingAs($user)->get(route('operations.index', ['type' => 'purchase', 'search' => 'PUR-OPS-001']))
            ->assertOk()
            ->assertSee('PUR-OPS-001')
            ->assertDontSee('INV-OPS-001');
    }

    public function test_disabled_account_cannot_sign_in(): void
    {
        $user=User::factory()->create(['email'=>'disabled@example.test','password'=>'password','is_active'=>false]);
        $this->post(route('login.store'),['email'=>$user->email,'password'=>'password'])->assertSessionHasErrors('email');$this->assertGuest();
    }

    public function test_administrator_can_soft_delete_another_user_but_not_their_own_account(): void
    {
        $administrator = $this->administrator();
        $employee = User::factory()->create(['is_active' => true]);

        $this->actingAs($administrator)
            ->delete(route('admin.users.destroy', $employee))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('users', ['id' => $employee->id]);

        $this->actingAs($administrator)
            ->delete(route('admin.users.destroy', $administrator))
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $administrator->id, 'deleted_at' => null]);
    }

    public function test_database_seeder_creates_only_administration_and_academic_structure(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['email' => 'admin@school.test', 'is_active' => true]);
        $this->assertDatabaseCount('academic_years', 2);
        $this->assertDatabaseCount('school_levels', 2);
        $this->assertDatabaseCount('grades', 15);
        $this->assertDatabaseCount('sections', 40);
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('suppliers', 0);
        $this->assertDatabaseCount('invoices', 0);
    }
}
