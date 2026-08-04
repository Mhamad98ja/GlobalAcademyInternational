<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\ClassBookList;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolLevel;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BookDistributionService;
use App\Services\InventoryService;
use App\Services\PaymentService;
use App\Services\SupplierPaymentService;
use App\Services\SupplierPurchaseService;
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
            ->assertDontSee('All academic years');
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

    public function test_disabled_account_cannot_sign_in(): void
    {
        $user=User::factory()->create(['email'=>'disabled@example.test','password'=>'password','is_active'=>false]);
        $this->post(route('login.store'),['email'=>$user->email,'password'=>'password'])->assertSessionHasErrors('email');$this->assertGuest();
    }
}
