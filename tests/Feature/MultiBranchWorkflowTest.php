<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookDistribution;
use App\Models\BookTransfer;
use App\Models\Branch;
use App\Models\BranchBookStock;
use App\Models\ClassBookList;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolLevel;
use App\Models\Section;
use App\Models\StockMovement;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Models\User;
use App\Services\BookTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultiBranchWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'super-administrator'], ['label' => 'Super Administrator']);
        $user = User::factory()->create(['email' => $email, 'is_active' => true]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function branches(User ...$users): array
    {
        $main = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main Branch', 'is_active' => true]);
        $north = Branch::create(['code' => 'NORTH', 'name' => 'North Branch', 'is_active' => true]);
        foreach ($users as $user) {
            $user->branches()->attach([$main->id => ['is_default' => true], $north->id => ['is_default' => false]]);
        }

        return [$main, $north];
    }

    private function branchUser(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'employee'], ['label' => 'Branch User']);
        $role->permissions()->sync(Permission::query()->whereIn('name', [
            'dashboard.view', 'students.view', 'students.manage', 'books.view', 'class-lists.view', 'distributions.create',
            'people.manage', 'reports.view',
        ])->pluck('id'));
        $user = User::factory()->create(['email' => $email, 'is_active' => true]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    public function test_branch_transfer_moves_stock_only_between_the_selected_branches(): void
    {
        $user = $this->user('manager@example.test');
        [$main, $north] = $this->branches($user);
        $book = Book::create(['sku' => 'TRF-BOOK', 'barcode' => '978000090', 'title' => 'Transfer Book', 'selling_price' => '10.00', 'tax_rate' => '5.00']);
        BranchBookStock::create(['branch_id' => $main->id, 'book_id' => $book->id, 'quantity_on_hand' => 12]);
        BranchBookStock::create(['branch_id' => $north->id, 'book_id' => $book->id, 'quantity_on_hand' => 2]);

        $this->actingAs($user)->withSession(['current_branch_id' => $main->id])
            ->post(route('transfers.store'), [
                'transfer_type' => 'branch_to_branch',
                'destination_branch_id' => (string) $north->id,
                'items' => [['book_id' => (string) $book->id, 'quantity' => '5']],
            ])->assertRedirect()->assertSessionHas('success');

        $transfer = BookTransfer::query()->firstOrFail();

        $this->assertSame('branch_to_branch', $transfer->transfer_type);
        $this->assertSame('pending_receipt', $transfer->status);
        $this->assertMatchesRegularExpression('/^TRF-\d{8}-[A-Z0-9]{10}$/', $transfer->transfer_number);
        $this->assertDatabaseHas('branch_book_stocks', ['branch_id' => $main->id, 'book_id' => $book->id, 'quantity_on_hand' => 7]);
        $this->assertDatabaseHas('branch_book_stocks', ['branch_id' => $north->id, 'book_id' => $book->id, 'quantity_on_hand' => 2]);
        $this->assertDatabaseHas('stock_movements', ['branch_id' => $main->id, 'type' => 'branch_transfer_out', 'quantity_change' => -5]);
        $this->assertDatabaseMissing('stock_movements', ['branch_id' => $north->id, 'type' => 'branch_transfer_in', 'quantity_change' => 5]);

        $invoice = $transfer->financialInvoice()->firstOrFail();
        $this->assertMatchesRegularExpression('/^BTI-\d{8}-[A-Z0-9]{10}$/', $invoice->invoice_number);
        $this->assertSame('50.00', $invoice->subtotal);
        $this->assertSame('2.50', $invoice->tax_total);
        $this->assertSame('52.50', $invoice->total);
        $this->assertSame('pending_acceptance', $invoice->status);
        $this->assertDatabaseHas('branch_transfer_invoice_items', [
            'branch_transfer_invoice_id' => $invoice->id,
            'book_id' => $book->id,
            'quantity' => 5,
            'unit_price' => '10.00',
            'line_total' => '52.50',
        ]);
        $this->actingAs($user)->withSession(['current_branch_id' => $north->id])
            ->post(route('transfers.accept', $transfer), ['receipt_notes' => 'Counted and received'])
            ->assertRedirect(route('transfers.show', $transfer))
            ->assertSessionHas('success');
        $transfer->refresh();
        $invoice->refresh();
        $this->assertSame('accepted', $transfer->status);
        $this->assertSame($user->id, $transfer->accepted_by);
        $this->assertSame('accepted', $invoice->status);
        $this->assertSame('settled', $invoice->settlement_status);
        $this->assertDatabaseHas('branch_book_stocks', ['branch_id' => $north->id, 'book_id' => $book->id, 'quantity_on_hand' => 7]);
        $this->assertDatabaseHas('stock_movements', ['branch_id' => $north->id, 'type' => 'branch_transfer_in', 'quantity_change' => 5]);
        $this->assertDatabaseHas('branch_cash_accounts', ['branch_id' => $main->id, 'balance' => '52.50']);
        $this->assertDatabaseHas('branch_cash_accounts', ['branch_id' => $north->id, 'balance' => '-52.50']);
        $this->assertDatabaseCount('branch_cash_movements', 2);
        $this->actingAs($user)->withSession(['current_branch_id' => $main->id])
            ->get(route('transfers.invoice', $transfer))
            ->assertOk()
            ->assertSee('Internal Branch Transfer Invoice')
            ->assertSee('52.50');
        $this->actingAs($user)->withSession(['current_branch_id' => $main->id])
            ->get(route('finance.index', ['tab' => 'branches']))
            ->assertOk()
            ->assertSee('Financial Center')
            ->assertSee('Total cash and branch position')
            ->assertSee('Internal branch settlement')
            ->assertSee('Internal branch transfer')
            ->assertSee($invoice->invoice_number)
            ->assertSee('52.50');
        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Incoming transfer awaiting confirmation')
            ->assertSee('Transfer accepted and settled');

        $movement = StockMovement::withoutGlobalScopes()->where('type', 'branch_transfer_out')->firstOrFail();
        $this->assertSame(['from' => 'Main Branch', 'to' => 'North Branch'], $movement->transferEndpoints());
    }

    public function test_book_packages_transfer_their_selected_package_quantity_to_another_branch(): void
    {
        $user = $this->user('manager@example.test');
        [$main, $north] = $this->branches($user);
        $year = AcademicYear::create(['name' => '2026-2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'created_by' => $user->id]);
        $level = SchoolLevel::create(['name' => 'Primary']);
        $grade = Grade::create(['school_level_id' => $level->id, 'name' => 'Grade 1']);
        $book = Book::create(['sku' => 'PACKAGE-BOOK', 'barcode' => '978000098', 'title' => 'Package Book', 'selling_price' => '10.00']);
        BranchBookStock::create(['branch_id' => $main->id, 'book_id' => $book->id, 'quantity_on_hand' => 8]);
        $package = new ClassBookList(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'Grade 1 starter package', 'is_active' => true]);
        $package->branch_id = $main->id;
        $package->save();
        $package->items()->create(['book_id' => $book->id, 'quantity' => 2, 'is_mandatory' => true, 'price_type' => 'paid', 'unit_price' => '0.00']);

        $this->actingAs($user)->withSession(['current_branch_id' => $main->id])
            ->post(route('transfers.store'), [
                'transfer_type' => 'package_to_branch',
                'destination_branch_id' => $north->id,
                'package_ids' => [(string) $package->id],
                'package_quantities' => [(string) $package->id => '3'],
                'notes' => 'Use the same starter package',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $packageTransfer = BookTransfer::query()->latest('id')->firstOrFail();
        $this->assertSame('branch_to_branch', $packageTransfer->transfer_type);
        $copiedPackage = ClassBookList::withoutGlobalScopes()->where('branch_id', $north->id)->where('name', 'Grade 1 starter package')->firstOrFail();
        $this->assertSame($year->id, $copiedPackage->academic_year_id);
        $this->assertDatabaseHas('class_book_list_items', ['class_book_list_id' => $copiedPackage->id, 'book_id' => $book->id, 'quantity' => 2]);
        $this->assertDatabaseHas('branch_book_stocks', ['branch_id' => $main->id, 'book_id' => $book->id, 'quantity_on_hand' => 2]);
        $this->assertDatabaseMissing('branch_book_stocks', ['branch_id' => $north->id, 'book_id' => $book->id, 'quantity_on_hand' => 6]);
        $this->assertDatabaseHas('stock_movements', ['branch_id' => $main->id, 'book_id' => $book->id, 'type' => 'branch_transfer_out', 'quantity_change' => -6]);
        $this->assertDatabaseHas('branch_transfer_invoices', ['book_transfer_id' => $packageTransfer->id, 'total' => '60.00']);
        $this->assertSame('60.00', $packageTransfer->financialInvoice()->firstOrFail()->total);
        $this->actingAs($user)->withSession(['current_branch_id' => $north->id])
            ->post(route('transfers.accept', $packageTransfer))
            ->assertRedirect();
        $this->assertDatabaseHas('branch_book_stocks', ['branch_id' => $north->id, 'book_id' => $book->id, 'quantity_on_hand' => 6]);
        $this->assertDatabaseHas('stock_movements', ['branch_id' => $north->id, 'book_id' => $book->id, 'type' => 'branch_transfer_in', 'quantity_change' => 6]);
    }

    public function test_completed_branch_transfer_can_be_reversed_once_and_restores_branch_stock(): void
    {
        $user = $this->user('manager@example.test');
        [$main, $north] = $this->branches($user);
        $book = Book::create(['sku' => 'REVERSE-BOOK', 'barcode' => '978000096', 'title' => 'Reversible Transfer Book', 'selling_price' => '20.00']);
        BranchBookStock::create(['branch_id' => $main->id, 'book_id' => $book->id, 'quantity_on_hand' => 12]);
        BranchBookStock::create(['branch_id' => $north->id, 'book_id' => $book->id, 'quantity_on_hand' => 2]);

        $service = app(BookTransferService::class);
        $transfer = $service->betweenBranches($main, $north, [['book_id' => $book->id, 'quantity' => 5]], null, $user);
        $service->acceptBranchTransfer($transfer, $user, 'Received');

        $this->actingAs($user)->withSession(['current_branch_id' => $main->id])
            ->post(route('transfers.reverse', $transfer), ['reversal_notes' => 'Entered by mistake'])
            ->assertRedirect(route('transfers.show', $transfer))
            ->assertSessionHas('success');

        $transfer->refresh();
        $this->assertSame('reversed', $transfer->status);
        $this->assertSame($user->id, $transfer->reversed_by);
        $this->assertNotNull($transfer->reversed_at);
        $this->assertSame('Entered by mistake', $transfer->reversal_notes);
        $this->assertDatabaseHas('branch_book_stocks', ['branch_id' => $main->id, 'book_id' => $book->id, 'quantity_on_hand' => 12]);
        $this->assertDatabaseHas('branch_book_stocks', ['branch_id' => $north->id, 'book_id' => $book->id, 'quantity_on_hand' => 2]);
        $this->assertDatabaseHas('stock_movements', ['branch_id' => $north->id, 'type' => 'branch_transfer_reversal_out', 'quantity_change' => -5]);
        $this->assertDatabaseHas('stock_movements', ['branch_id' => $main->id, 'type' => 'branch_transfer_reversal_in', 'quantity_change' => 5]);
        $this->assertDatabaseHas('branch_transfer_invoices', ['book_transfer_id' => $transfer->id, 'status' => 'voided', 'voided_by' => $user->id]);
        $this->assertDatabaseHas('branch_cash_accounts', ['branch_id' => $main->id, 'balance' => '0.00']);
        $this->assertDatabaseHas('branch_cash_accounts', ['branch_id' => $north->id, 'balance' => '0.00']);

        $this->actingAs($user)->withSession(['current_branch_id' => $main->id])
            ->get(route('transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Reversed')
            ->assertDontSee('Reverse transfer');

        $this->actingAs($user)->withSession(['current_branch_id' => $main->id])
            ->post(route('transfers.reverse', $transfer))
            ->assertSessionHasErrors('transfer');
    }

    public function test_receiving_branch_can_reject_a_pending_transfer_without_a_cash_settlement(): void
    {
        $sender = $this->user('sender@example.test');
        $receiver = $this->user('receiver@example.test');
        [$main, $north] = $this->branches($sender, $receiver);
        $book = Book::create(['sku' => 'REJECT-BOOK', 'barcode' => '9780000961', 'title' => 'Rejected Transfer Book', 'selling_price' => '20.00']);
        BranchBookStock::create(['branch_id' => $main->id, 'book_id' => $book->id, 'quantity_on_hand' => 9]);
        BranchBookStock::create(['branch_id' => $north->id, 'book_id' => $book->id, 'quantity_on_hand' => 1]);

        $transfer = app(BookTransferService::class)->betweenBranches($main, $north, [['book_id' => $book->id, 'quantity' => 4]], null, $sender);

        $this->actingAs($receiver)->withSession(['current_branch_id' => $north->id])
            ->post(route('transfers.reject', $transfer), ['rejection_reason' => 'Delivery was not received.'])
            ->assertRedirect(route('transfers.show', $transfer))
            ->assertSessionHas('success');

        $transfer->refresh();
        $this->assertSame('rejected', $transfer->status);
        $this->assertSame($receiver->id, $transfer->rejected_by);
        $this->assertDatabaseHas('branch_book_stocks', ['branch_id' => $main->id, 'book_id' => $book->id, 'quantity_on_hand' => 9]);
        $this->assertDatabaseHas('branch_book_stocks', ['branch_id' => $north->id, 'book_id' => $book->id, 'quantity_on_hand' => 1]);
        $this->assertDatabaseHas('stock_movements', ['branch_id' => $main->id, 'type' => 'branch_transfer_rejected_return', 'quantity_change' => 4]);
        $this->assertDatabaseHas('branch_transfer_invoices', ['book_transfer_id' => $transfer->id, 'status' => 'rejected', 'settlement_status' => 'not_settled']);
        $this->assertDatabaseCount('branch_cash_movements', 0);
    }

    public function test_main_controller_can_view_financial_transfers_between_any_branches(): void
    {
        $controller = $this->user('controller@example.test');
        $controller->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        [$main, $north] = $this->branches($controller);
        $east = Branch::create(['code' => 'EAST', 'name' => 'East Branch', 'is_active' => true]);
        $book = Book::create(['sku' => 'CENTRAL-BOOK', 'barcode' => '978000099', 'title' => 'Central Review Book', 'selling_price' => '15.00']);
        BranchBookStock::create(['branch_id' => $main->id, 'book_id' => $book->id, 'quantity_on_hand' => 10]);
        BranchBookStock::create(['branch_id' => $north->id, 'book_id' => $book->id, 'quantity_on_hand' => 0]);
        BranchBookStock::create(['branch_id' => $east->id, 'book_id' => $book->id, 'quantity_on_hand' => 0]);

        $service = app(BookTransferService::class);
        $mainToNorth = $service->betweenBranches($main, $north, [['book_id' => $book->id, 'quantity' => 6]], null, $controller);
        $service->acceptBranchTransfer($mainToNorth, $controller);
        $northToEast = $service->betweenBranches($north, $east, [['book_id' => $book->id, 'quantity' => 2]], null, $controller);
        $service->acceptBranchTransfer($northToEast, $controller);

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->get(route('transfers.index', ['all_branches' => 1]))
            ->assertOk()
            ->assertSee('All branches')
            ->assertSee($northToEast->transfer_number)
            ->assertSee('30.00')
            ->assertSee('East Branch');
        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->get(route('finance.index', ['tab' => 'branches', 'scope' => 'all']))
            ->assertOk()
            ->assertSee('All branches')
            ->assertSee($northToEast->financialInvoice->invoice_number)
            ->assertSee('North Branch')
            ->assertSee('East Branch');

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->get(route('transfers.show', $northToEast))
            ->assertOk()
            ->assertSee('North Branch')
            ->assertSee('East Branch')
            ->assertSee('Print internal invoice');
    }

    public function test_user_custody_assignment_and_transfer_preserve_branch_stock(): void
    {
        $manager = $this->user('manager@example.test');
        $alice = $this->user('alice@example.test');
        $bob = $this->user('bob@example.test');
        [$main] = $this->branches($manager, $alice, $bob);
        $book = Book::create(['sku' => 'CUSTODY-BOOK', 'barcode' => '978000091', 'title' => 'Custody Book']);
        BranchBookStock::create(['branch_id' => $main->id, 'book_id' => $book->id, 'quantity_on_hand' => 10]);

        $service = app(BookTransferService::class);
        $service->assignToUser($main, $alice, [['book_id' => $book->id, 'quantity' => 6]], null, $manager);
        $service->betweenUsers($main, $alice, $bob, [['book_id' => $book->id, 'quantity' => 4]], null, $manager);

        $this->assertDatabaseHas('branch_book_stocks', ['branch_id' => $main->id, 'book_id' => $book->id, 'quantity_on_hand' => 10]);
        $this->assertDatabaseHas('user_book_stocks', ['branch_id' => $main->id, 'user_id' => $alice->id, 'book_id' => $book->id, 'quantity_on_hand' => 2]);
        $this->assertDatabaseHas('user_book_stocks', ['branch_id' => $main->id, 'user_id' => $bob->id, 'book_id' => $book->id, 'quantity_on_hand' => 4]);
        $this->assertDatabaseHas('stock_movements', ['branch_id' => $main->id, 'holder_user_id' => $bob->id, 'type' => 'user_custody_transfer', 'quantity_change' => 4]);
    }

    public function test_custody_transfers_can_be_reversed_when_the_recipient_still_holds_the_books(): void
    {
        $manager = $this->user('manager@example.test');
        $alice = $this->user('alice@example.test');
        $bob = $this->user('bob@example.test');
        [$main] = $this->branches($manager, $alice, $bob);
        $book = Book::create(['sku' => 'REVERSE-CUSTODY', 'barcode' => '978000097', 'title' => 'Reversible Custody Book']);
        BranchBookStock::create(['branch_id' => $main->id, 'book_id' => $book->id, 'quantity_on_hand' => 10]);

        $service = app(BookTransferService::class);
        $assignment = $service->assignToUser($main, $alice, [['book_id' => $book->id, 'quantity' => 6]], null, $manager);
        $userTransfer = $service->betweenUsers($main, $alice, $bob, [['book_id' => $book->id, 'quantity' => 4]], null, $manager);

        $this->actingAs($manager)->withSession(['current_branch_id' => $main->id])
            ->post(route('transfers.reverse', $assignment))
            ->assertSessionHasErrors('transfer');

        $this->actingAs($manager)->withSession(['current_branch_id' => $main->id])
            ->post(route('transfers.reverse', $userTransfer))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user_book_stocks', ['branch_id' => $main->id, 'user_id' => $alice->id, 'book_id' => $book->id, 'quantity_on_hand' => 6]);
        $this->assertDatabaseHas('user_book_stocks', ['branch_id' => $main->id, 'user_id' => $bob->id, 'book_id' => $book->id, 'quantity_on_hand' => 0]);

        $this->actingAs($manager)->withSession(['current_branch_id' => $main->id])
            ->post(route('transfers.reverse', $assignment))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user_book_stocks', ['branch_id' => $main->id, 'user_id' => $alice->id, 'book_id' => $book->id, 'quantity_on_hand' => 0]);
        $this->assertDatabaseHas('stock_movements', ['branch_id' => $main->id, 'holder_user_id' => $alice->id, 'type' => 'user_custody_transfer', 'quantity_change' => -6, 'reason' => 'Transfer reversal to branch stock']);
    }

    public function test_branch_scopes_student_records_to_the_active_branch(): void
    {
        $user = $this->user('manager@example.test');
        [$main, $north] = $this->branches($user);
        $year = AcademicYear::create(['name' => '2026-2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'created_by' => $user->id]);
        $level = SchoolLevel::create(['name' => 'Primary']);
        $grade = Grade::create(['school_level_id' => $level->id, 'name' => 'Grade 1']);
        $section = Section::create(['grade_id' => $grade->id, 'name' => 'A']);
        $student = Student::withoutGlobalScopes()->create(['branch_id' => $north->id, 'student_number' => 'NORTH-001', 'first_name' => 'North', 'last_name' => 'Student']);
        StudentRegistration::withoutGlobalScopes()->create(['branch_id' => $north->id, 'student_id' => $student->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'status' => 'active', 'registered_on' => '2026-09-01', 'created_by' => $user->id]);

        $this->actingAs($user)->withSession(['current_branch_id' => $main->id])->get(route('students.index'))->assertOk()->assertDontSee('North Student');
        $this->actingAs($user)->withSession(['current_branch_id' => $north->id])->get(route('students.index'))->assertOk()->assertSee('North Student');
    }

    public function test_branch_administrator_is_locked_to_their_branch_and_sees_only_its_stocked_books(): void
    {
        $controller = $this->user('controller@example.test');
        $controller->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        $beirutAdministrator = $this->user('beirut-administrator@example.test');
        $beirutAdministrator->update(['has_all_branch_access' => true]);
        $main = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main Branch', 'is_active' => true]);
        $beirut = Branch::create(['code' => 'BEIRUT', 'name' => 'Beirut Branch', 'is_active' => true]);
        $beirutAdministrator->branches()->attach([
            $main->id => ['is_default' => true, 'is_branch_administrator' => false],
            $beirut->id => ['is_default' => false, 'is_branch_administrator' => true],
        ]);
        $mainBook = Book::create(['sku' => 'MAIN-ONLY', 'barcode' => '978000094', 'title' => 'Main Only Book']);
        $beirutBook = Book::create(['sku' => 'BEIRUT-ONLY', 'barcode' => '978000095', 'title' => 'Beirut Only Book']);
        BranchBookStock::create(['branch_id' => $main->id, 'book_id' => $mainBook->id, 'quantity_on_hand' => 5]);
        BranchBookStock::create(['branch_id' => $beirut->id, 'book_id' => $beirutBook->id, 'quantity_on_hand' => 5]);

        $this->actingAs($beirutAdministrator)->withSession(['current_branch_id' => $main->id])
            ->get(route('books.index'))
            ->assertOk()
            ->assertSee('Beirut Only Book')
            ->assertDontSee('Main Only Book')
            ->assertSee('Beirut Branch')
            ->assertDontSee('branch-switcher', false);
        $this->actingAs($beirutAdministrator)->withSession(['current_branch_id' => $main->id])
            ->get(route('transfers.create'))
            ->assertOk()
            ->assertSee('Beirut Only Book')
            ->assertDontSee('Main Only Book')
            ->assertSee('Scan book barcode')
            ->assertDontSee('Select book')
            ->assertDontSee('Books: user to user custody');
        $this->actingAs($beirutAdministrator)->withSession(['current_branch_id' => $main->id])
            ->post(route('branches.select'), ['branch_id' => $main->id])
            ->assertForbidden();
        $this->actingAs($beirutAdministrator)->withSession(['current_branch_id' => $main->id])
            ->get(route('books.show', $mainBook))
            ->assertNotFound();

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->get(route('books.index'))
            ->assertOk()
            ->assertSee('Main Only Book')
            ->assertDontSee('Beirut Only Book')
            ->assertSee('branch-switcher', false);
        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->post(route('branches.select'), ['branch_id' => $beirut->id])
            ->assertRedirect();
        $this->actingAs($controller)->withSession(['current_branch_id' => $beirut->id])
            ->get(route('books.index'))
            ->assertOk()
            ->assertSee('Beirut Only Book')
            ->assertDontSee('Main Only Book');
    }

    public function test_administrator_can_create_and_update_a_branch_with_user_assignments(): void
    {
        $administrator = $this->user('administrator@example.test');
        $administrator->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        [$main] = $this->branches($administrator);
        $employee = $this->user('employee@example.test');

        $this->actingAs($administrator)->withSession(['current_branch_id' => $main->id])
            ->post(route('branches.store'), [
                'code' => 'SOUTH',
                'name' => 'South Branch',
                'phone' => '+9613000000',
                'email' => 'south@example.test',
                'address' => 'South district',
                'is_active' => '1',
                'user_ids' => [(string) $employee->id],
                'branch_administrator_id' => (string) $employee->id,
            ])->assertRedirect()->assertSessionHas('success');

        $south = Branch::where('code', 'SOUTH')->firstOrFail();
        $this->assertDatabaseHas('branch_user', ['branch_id' => $south->id, 'user_id' => $employee->id, 'is_default' => true]);

        $this->actingAs($administrator)->withSession(['current_branch_id' => $main->id])
            ->put(route('branches.update', $south), [
                'code' => 'SOUTH',
                'name' => 'South Campus',
                'is_active' => '1',
                'user_ids' => [(string) $administrator->id, (string) $employee->id],
                'branch_administrator_id' => (string) $employee->id,
            ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('branches', ['id' => $south->id, 'name' => 'South Campus']);
        $this->assertDatabaseHas('branch_user', ['branch_id' => $south->id, 'user_id' => $administrator->id, 'is_default' => false]);
        $this->assertDatabaseHas('branch_user', ['branch_id' => $main->id, 'user_id' => $administrator->id, 'is_default' => true]);
    }

    public function test_controller_administrator_can_delete_only_an_unused_non_main_branch(): void
    {
        $administrator = $this->user('administrator@example.test');
        $administrator->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        [$main] = $this->branches($administrator);
        $unused = Branch::create(['code' => 'SOUTH', 'name' => 'South Branch', 'is_active' => true]);
        $inUse = Branch::create(['code' => 'EAST', 'name' => 'East Branch', 'is_active' => true]);
        $inUse->users()->attach($administrator->id, ['is_default' => false, 'is_branch_administrator' => false]);

        $this->actingAs($administrator)->withSession(['current_branch_id' => $main->id])
            ->delete(route('branches.destroy', $unused))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('branches', ['id' => $unused->id]);

        $this->actingAs($administrator)->withSession(['current_branch_id' => $main->id])
            ->delete(route('branches.destroy', $main))
            ->assertSessionHasErrors('branch');

        $this->actingAs($administrator)->withSession(['current_branch_id' => $main->id])
            ->delete(route('branches.destroy', $inUse))
            ->assertSessionHasErrors('branch');

        $this->assertDatabaseHas('branches', ['id' => $main->id]);
        $this->assertDatabaseHas('branches', ['id' => $inUse->id]);
    }

    public function test_main_branch_code_and_active_status_are_preserved_when_updated(): void
    {
        $administrator = $this->user('administrator@example.test');
        $administrator->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        $branchAdministrator = $this->user('branch-administrator@example.test');
        [$main] = $this->branches($administrator, $branchAdministrator);
        $main->users()->updateExistingPivot($branchAdministrator->id, ['is_branch_administrator' => true]);

        $this->actingAs($administrator)->withSession(['current_branch_id' => $main->id])
            ->put(route('branches.update', $main), [
                'code' => 'RENAMED',
                'name' => 'Main Campus',
                'is_active' => '0',
                'user_ids' => [$administrator->id, $branchAdministrator->id],
                'default_user_id' => $administrator->id,
                'branch_administrator_id' => $branchAdministrator->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('branches', ['id' => $main->id, 'code' => 'MAIN', 'name' => 'Main Campus', 'is_active' => true]);
    }

    public function test_only_the_controller_administrator_can_change_branch_structure(): void
    {
        $controller = $this->user('controller@example.test');
        $controller->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        $branchAdministrator = $this->user('branch-admin@example.test');
        [$main] = $this->branches($controller, $branchAdministrator);
        $main->users()->updateExistingPivot($branchAdministrator->id, ['is_branch_administrator' => true]);

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->get(route('branches.index'))
            ->assertOk()
            ->assertSee('New branch')
            ->assertSee('Choose a branch to manage')
            ->assertDontSee('Delete branch');

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->get(route('branches.index', ['edit' => Branch::where('code', 'NORTH')->firstOrFail()->id]))
            ->assertOk()->assertSee('Delete branch');

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->get(route('admin.imports'))
            ->assertOk()
            ->assertSee('Import students')
            ->assertSee('Import books');

        $this->actingAs($branchAdministrator)->withSession(['current_branch_id' => $main->id])
            ->get(route('branches.index'))
            ->assertForbidden();

        $this->actingAs($branchAdministrator)->withSession(['current_branch_id' => $main->id])
            ->get(route('branches.users.search', ['q' => 'controller']))
            ->assertForbidden();

        $this->actingAs($branchAdministrator)->withSession(['current_branch_id' => $main->id])
            ->post(route('branches.store'), ['code' => 'BLOCKED', 'name' => 'Blocked', 'is_active' => '1'])
            ->assertForbidden();

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->post(route('branches.store'), [
                'code' => 'ALLOWED',
                'name' => 'Allowed',
                'is_active' => '1',
                'user_ids' => [$branchAdministrator->id],
                'branch_administrator_id' => $branchAdministrator->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('branches', ['code' => 'ALLOWED']);
    }

    public function test_branch_page_searches_branches_and_loads_users_only_when_requested(): void
    {
        $controller = $this->user('controller@example.test');
        $controller->update(['name' => 'Controller', 'is_controller_administrator' => true, 'has_all_branch_access' => true]);
        $branchAdministrator = $this->user('principal@example.test');
        $branchAdministrator->update(['name' => 'Principal']);
        [$main, $north] = $this->branches($controller, $branchAdministrator);
        $main->users()->updateExistingPivot($branchAdministrator->id, ['is_branch_administrator' => true]);
        $north->users()->updateExistingPivot($branchAdministrator->id, ['is_branch_administrator' => true]);
        $south = Branch::create(['code' => 'SOUTH', 'name' => 'South Branch', 'is_active' => true]);
        $unassigned = User::factory()->create(['name' => 'Searchable Employee', 'email' => 'searchable@example.test', 'is_active' => true]);

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->get(route('branches.index', ['q' => 'North']))
            ->assertOk()->assertSee('North Branch')
            ->assertDontSee('href="'.route('branches.index', ['edit' => $south->id, 'q' => 'North']).'"', false)
            ->assertDontSee('name="user_ids[]"', false);

        $this->get(route('branches.index', ['edit' => $north->id]))
            ->assertOk()->assertSee('Edit North Branch')
            ->assertSee('name="user_ids[]"', false)
            ->assertDontSee('Searchable Employee');

        $this->get(route('branches.users.search', ['q' => 'Searchable']))
            ->assertOk()->assertJsonFragment(['id' => $unassigned->id, 'name' => 'Searchable Employee'])
            ->assertJsonMissing(['name' => 'Principal']);

        foreach (range(1, 15) as $number) {
            Branch::create(['code' => 'CAMPUS-'.$number, 'name' => 'Campus '.$number, 'is_active' => true]);
        }
        $this->get(route('branches.index'))->assertOk()
            ->assertViewHas('branches', fn ($page) => $page->count() === 12 && $page->hasMorePages());
    }

    public function test_branch_people_are_grouped_and_student_accounts_cannot_be_moved_from_the_branch_form(): void
    {
        $controller = $this->user('controller@example.test');
        $controller->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        $administrator = $this->user('principal@example.test');
        [$main, $north] = $this->branches($controller, $administrator);
        $north->users()->updateExistingPivot($administrator->id, ['is_branch_administrator' => true]);
        $student = Student::withoutGlobalScopes()->create(['branch_id' => $north->id, 'student_number' => 'N-100', 'first_name' => 'Nadia', 'last_name' => 'Student']);
        $studentUser = User::factory()->create(['name' => 'Nadia Student', 'email' => 'nadia@example.test', 'student_id' => $student->id]);
        $studentRole = Role::firstOrCreate(['name' => 'student'], ['label' => 'Student']);
        $studentUser->roles()->sync([$studentRole->id]);
        $studentUser->branches()->attach([$north->id => ['is_default' => true, 'is_branch_administrator' => false]]);

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->get(route('branches.index', ['edit' => $north->id]))
            ->assertOk()->assertSee('data-group="administrators"', false)
            ->assertSee('data-group="teachers"', false)
            ->assertSee('data-group="students"', false)
            ->assertSee('Nadia Student');
        $this->get(route('branches.users.search', ['q' => 'Nadia', 'kind' => 'students', 'branch_id' => $north->id]))
            ->assertOk()->assertJsonFragment(['name' => 'Nadia Student', 'kind' => 'students']);
        $this->get(route('branches.users.search', ['q' => 'Nadia', 'kind' => 'administrators']))
            ->assertOk()->assertExactJson([]);

        $this->put(route('branches.update', $north), [
            'code' => 'NORTH', 'name' => 'North Branch', 'is_active' => '1',
            'user_ids' => [$administrator->id], 'branch_administrator_id' => $administrator->id,
        ])->assertSessionHasErrors('user_ids');
        $this->assertDatabaseHas('branch_user', ['branch_id' => $north->id, 'user_id' => $studentUser->id]);
    }

    public function test_branch_edits_preserve_each_users_default_branch_and_prevent_orphaned_accounts(): void
    {
        $controller = $this->user('controller@example.test');
        $controller->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        $administrator = $this->user('principal@example.test');
        $member = $this->user('member@example.test');
        [$main, $north] = $this->branches($controller);
        $administrator->branches()->attach([$north->id => ['is_default' => true, 'is_branch_administrator' => true]]);
        $member->branches()->attach([$north->id => ['is_default' => true, 'is_branch_administrator' => false]]);

        $payload = ['code' => 'NORTH', 'name' => 'North Branch', 'is_active' => '1', 'user_ids' => [$administrator->id], 'branch_administrator_id' => $administrator->id];
        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->put(route('branches.update', $north), $payload)->assertSessionHasErrors('user_ids');
        $this->assertDatabaseHas('branch_user', ['branch_id' => $north->id, 'user_id' => $member->id, 'is_default' => true]);

        $member->branches()->attach([$main->id => ['is_default' => false, 'is_branch_administrator' => false]]);
        $this->put(route('branches.update', $north), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('branch_user', ['branch_id' => $north->id, 'user_id' => $member->id]);
        $this->assertDatabaseHas('branch_user', ['branch_id' => $main->id, 'user_id' => $member->id, 'is_default' => true]);

        $this->put(route('branches.update', $north), array_replace($payload, ['is_active' => '0']))->assertSessionHasErrors('is_active');
        $administrator->branches()->attach([$main->id => ['is_default' => false, 'is_branch_administrator' => false]]);
        $this->put(route('branches.update', $north), array_replace($payload, ['is_active' => '0']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branch_user', ['branch_id' => $main->id, 'user_id' => $administrator->id, 'is_default' => true]);
        $this->assertDatabaseHas('branches', ['id' => $north->id, 'is_active' => false]);
    }

    public function test_responsible_administrator_cannot_be_removed_or_disabled_without_a_transfer(): void
    {
        $controller = $this->user('controller@example.test');
        $controller->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        $responsible = $this->user('responsible@example.test');
        [$main, $north] = $this->branches($controller, $responsible);
        $main->users()->updateExistingPivot($responsible->id, ['is_branch_administrator' => true]);
        $north->users()->updateExistingPivot($responsible->id, ['is_branch_administrator' => true]);

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->put(route('admin.users.update', $responsible), [
                'name' => $responsible->name,
                'email' => $responsible->email,
                'role_id' => $responsible->roles()->firstOrFail()->id,
                'branch_ids' => [$main->id, $north->id],
                'default_branch_id' => $main->id,
                'branch_administrator_ids' => [$north->id],
            ])->assertSessionHasErrors('branch_administrator_ids');
        $this->assertDatabaseHas('branch_user', ['branch_id' => $main->id, 'user_id' => $responsible->id, 'is_branch_administrator' => true]);

        $employeeRole = Role::firstOrCreate(['name' => 'employee'], ['label' => 'Employee']);
        $this->put(route('admin.users.update', $responsible), [
            'name' => 'Changed Name',
            'email' => $responsible->email,
            'role_id' => $employeeRole->id,
            'branch_ids' => [$main->id, $north->id],
            'default_branch_id' => $main->id,
            'branch_administrator_ids' => [$main->id, $north->id],
        ])->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $responsible->id, 'name' => $responsible->name]);
        $this->assertTrue($responsible->fresh()->hasRole('super-administrator'));

        $this->post(route('admin.users.toggle', $responsible))->assertStatus(422);
        $this->delete(route('admin.users.destroy', $responsible))->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $responsible->id, 'is_active' => true, 'deleted_at' => null]);
    }

    public function test_branch_administrator_can_be_prepared_on_an_existing_branch_without_displacing_its_administrator(): void
    {
        $controller = $this->user('controller@example.test');
        $controller->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        [$main] = $this->branches($controller);

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->post(route('admin.users.store'), [
                'name' => 'Incomplete Administrator',
                'email' => 'incomplete-admin@example.test',
                'password' => 'SecurePass123!',
                'password_confirmation' => 'SecurePass123!',
                'role_id' => $controller->roles()->firstOrFail()->id,
                'branch_ids' => [$main->id],
                'default_branch_id' => $main->id,
            ])->assertRedirect()->assertSessionHas('success');

        $candidate = User::where('email', 'incomplete-admin@example.test')->firstOrFail();
        $this->assertDatabaseHas('branch_user', ['user_id' => $candidate->id, 'branch_id' => $main->id, 'is_default' => true, 'is_branch_administrator' => false]);
    }

    public function test_new_user_single_branch_is_automatically_the_starting_branch(): void
    {
        $controller = $this->user('controller@example.test');
        $controller->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        [$main, $north] = $this->branches($controller);
        $employeeRole = Role::firstOrCreate(['name' => 'employee'], ['label' => 'Employee']);

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->post(route('admin.users.store'), [
                'name' => 'North Employee',
                'email' => 'north-employee@example.test',
                'password' => 'SecurePass123!',
                'password_confirmation' => 'SecurePass123!',
                'role_id' => $employeeRole->id,
                'branch_id' => $north->id,
            ])->assertRedirect()->assertSessionHas('success');

        $employee = User::where('email', 'north-employee@example.test')->firstOrFail();
        $this->assertDatabaseHas('branch_user', ['user_id' => $employee->id, 'branch_id' => $north->id, 'is_default' => true, 'is_branch_administrator' => false]);
        $this->assertDatabaseMissing('branch_user', ['user_id' => $employee->id, 'branch_id' => $main->id]);
        $this->actingAs($employee)->get(route('branches.index'))->assertForbidden();
    }

    public function test_new_branch_can_create_its_first_administrator_in_the_same_step(): void
    {
        $controller = $this->user('controller@example.test');
        $controller->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        [$main] = $this->branches($controller);

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->post(route('branches.store'), [
                'code' => 'EAST',
                'name' => 'East Campus',
                'is_active' => '1',
                'administrator_name' => 'East Administrator',
                'administrator_email' => 'east-admin@example.test',
                'administrator_password' => 'SecurePass123!',
                'administrator_password_confirmation' => 'SecurePass123!',
            ])->assertRedirect()->assertSessionHas('success');

        $east = Branch::where('code', 'EAST')->firstOrFail();
        $administrator = User::where('email', 'east-admin@example.test')->firstOrFail();
        $this->assertTrue($administrator->hasRole('super-administrator'));
        $this->assertDatabaseHas('branch_user', ['branch_id' => $east->id, 'user_id' => $administrator->id, 'is_default' => true, 'is_branch_administrator' => true]);
        $this->assertFalse($administrator->isControllerAdministrator());

        $this->get(route('admin.settings'))->assertOk();
        $this->actingAs($administrator)->withSession(['current_branch_id' => $east->id])
            ->get(route('branches.index'))->assertForbidden();
        $this->get(route('admin.settings'))->assertForbidden();
        $this->get(route('admin.audit'))->assertForbidden();
        $this->post(route('students.references.store', ['reference' => 'codes']), [])->assertForbidden();
        $this->get(route('admin.users'))->assertOk();
    }

    public function test_failed_inline_administrator_validation_does_not_create_a_branch_or_account(): void
    {
        $controller = $this->user('controller@example.test');
        $controller->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        [$main] = $this->branches($controller);

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->post(route('branches.store'), [
                'code' => 'UNFINISHED',
                'name' => 'Unfinished Branch',
                'is_active' => '1',
                'administrator_name' => 'Unfinished Administrator',
                'administrator_email' => 'unfinished@example.test',
                'administrator_password' => 'weak',
                'administrator_password_confirmation' => 'weak',
            ])->assertSessionHasErrors('administrator_password');

        $this->assertDatabaseMissing('branches', ['code' => 'UNFINISHED']);
        $this->assertDatabaseMissing('users', ['email' => 'unfinished@example.test']);
    }

    public function test_only_controller_administrator_can_assign_global_or_specific_branch_access(): void
    {
        $controller = $this->user('controller@example.test');
        $controller->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        $otherAdministrator = $this->user('other-admin@example.test');
        $managedUser = $this->branchUser('managed@example.test');
        [$main, $north] = $this->branches($controller, $otherAdministrator);
        $otherAdministrator->branches()->updateExistingPivot($main->id, ['is_branch_administrator' => true]);
        $managedUser->branches()->attach([$main->id => ['is_default' => true]]);
        $roleId = Role::where('name', 'super-administrator')->value('id');

        $this->actingAs($otherAdministrator)->withSession(['current_branch_id' => $main->id])
            ->put(route('admin.users.update', $managedUser), [
                'name' => $managedUser->name,
                'email' => $managedUser->email,
                'role_id' => $roleId,
                'has_all_branch_access' => '1',
                'branch_ids' => [$north->id],
                'default_branch_id' => $north->id,
                'branch_administrator_ids' => [$north->id],
            ])->assertRedirect();

        $managedUser->refresh()->load('branches');
        $this->assertFalse($managedUser->has_all_branch_access);
        $this->assertSame([$main->id], $managedUser->branches->pluck('id')->all());

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->put(route('admin.users.update', $managedUser), [
                'name' => $managedUser->name,
                'email' => $managedUser->email,
                'role_id' => $roleId,
                'branch_ids' => [$main->id, $north->id],
                'default_branch_id' => $north->id,
                'branch_administrator_ids' => [$north->id],
            ])->assertRedirect();

        $this->assertDatabaseHas('branch_user', [
            'branch_id' => $north->id,
            'user_id' => $managedUser->id,
            'is_default' => true,
            'is_branch_administrator' => true,
        ]);

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee('data-user-role-select', false)
            ->assertSee('name="branch_id"', false)
            ->assertSee('data-role-name="super-administrator"', false);
    }

    public function test_user_profile_photo_is_uploaded_replaced_and_served_from_the_user_profile(): void
    {
        Storage::fake('public');
        $controller = $this->user('photo-controller@example.test');
        $controller->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        $managedUser = $this->branchUser('photo-user@example.test');
        [$main] = $this->branches($controller, $managedUser);

        $first = UploadedFile::fake()->image('first.jpg');
        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->put(route('admin.users.update', $managedUser), [
                'name' => $managedUser->name,
                'email' => $managedUser->email,
                'profile_photo' => $first,
                'role_id' => Role::where('name', 'employee')->value('id'),
                'branch_ids' => [$main->id],
                'default_branch_id' => $main->id,
            ])->assertRedirect();

        $managedUser->refresh();
        $oldPath = $managedUser->profile_photo_path;
        $this->assertNotNull($oldPath);
        Storage::disk('public')->assertExists($oldPath);
        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->get(route('admin.users.photo', $managedUser))->assertOk();

        $second = UploadedFile::fake()->image('second.jpg');
        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->put(route('admin.users.update', $managedUser), [
                'name' => $managedUser->name,
                'email' => $managedUser->email,
                'profile_photo' => $second,
                'role_id' => Role::where('name', 'employee')->value('id'),
                'branch_ids' => [$main->id],
                'default_branch_id' => $main->id,
            ])->assertRedirect();

        $managedUser->refresh();
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($managedUser->profile_photo_path);
    }

    public function test_branch_administrator_can_see_student_users_from_the_student_branch(): void
    {
        $branchAdministrator = $this->user('student-directory-admin@example.test');
        [$main, $north] = $this->branches($branchAdministrator);
        $north->users()->updateExistingPivot($branchAdministrator->id, ['is_branch_administrator' => true]);
        $student = Student::withoutGlobalScopes()->create([
            'branch_id' => $north->id,
            'student_number' => 'NORTH-PORTAL-001',
            'first_name' => 'North',
            'last_name' => 'Portal Student',
            'email' => 'north.portal.student@example.test',
        ]);
        $studentRole = Role::firstOrCreate(['name' => 'student'], ['label' => 'Student']);
        $studentUser = User::factory()->create(['name' => $student->full_name, 'email' => $student->email, 'student_id' => $student->id, 'is_active' => true]);
        $studentUser->roles()->attach($studentRole);

        $this->actingAs($branchAdministrator)->withSession(['current_branch_id' => $north->id])
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee($student->full_name)
            ->assertSee('Student');

        $this->actingAs($branchAdministrator)->withSession(['current_branch_id' => $north->id])
            ->get(route('admin.users', ['role_id' => $studentRole->id]))
            ->assertOk()
            ->assertSee($student->full_name);
    }

    public function test_controller_can_manage_explicit_user_access_from_the_user_directory(): void
    {
        $controller = $this->user('access-controller@example.test');
        $controller->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        $managedUser = $this->branchUser('access-managed@example.test');
        [$main] = $this->branches($controller, $managedUser);
        $reportsPermission = Permission::firstOrCreate(['name' => 'reports.view'], ['label' => 'View reports']);
        $studentsPermission = Permission::firstOrCreate(['name' => 'students.view'], ['label' => 'View students']);
        $managedUser->roles()->firstOrFail()->permissions()->sync([$reportsPermission->id, $studentsPermission->id]);

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->get(route('admin.users.access.edit', $managedUser))
            ->assertOk()
            ->assertSee('Manage access')
            ->assertSee('permissions[]', false);

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->put(route('admin.users.access.update', $managedUser), ['permissions' => [$reportsPermission->id]])
            ->assertRedirect(route('admin.users'));

        $this->assertTrue($managedUser->fresh()->hasPermission('reports.view'));
        $this->assertFalse($managedUser->fresh()->hasPermission('students.view'));
        $this->assertDatabaseHas('permission_user', ['user_id' => $managedUser->id, 'permission_id' => $studentsPermission->id, 'allowed' => false]);
    }

    public function test_branch_super_administrator_controls_local_users_while_branch_users_cannot_view_financial_data(): void
    {
        $controller = $this->user('controller@example.test');
        $controller->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        $branchAdministrator = $this->user('branch-admin@example.test');
        $branchUser = $this->branchUser('branch-user@example.test');
        [$main] = $this->branches($controller, $branchAdministrator);
        $main->users()->updateExistingPivot($branchAdministrator->id, ['is_branch_administrator' => true]);
        $branchUser->branches()->attach([$main->id => ['is_default' => true, 'is_branch_administrator' => false]]);
        $book = Book::create(['sku' => 'FIN-BOOK', 'barcode' => '978000092', 'title' => 'Financially Protected Book', 'selling_price' => '12.50']);
        BranchBookStock::create(['branch_id' => $main->id, 'book_id' => $book->id, 'quantity_on_hand' => 1]);
        $year = AcademicYear::create(['name' => '2026-2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'status' => 'open']);
        $level = SchoolLevel::create(['name' => 'Primary']);
        $grade = Grade::create(['school_level_id' => $level->id, 'name' => 'Grade 1']);
        $section = Section::create(['grade_id' => $grade->id, 'name' => 'A']);
        $student = Student::create(['student_number' => 'BRANCH-USER-001', 'first_name' => 'Normal', 'last_name' => 'User']);
        $registration = StudentRegistration::create([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'grade_id' => $grade->id,
            'section_id' => $section->id,
            'status' => 'active',
            'registered_on' => '2026-09-01',
            'created_by' => $branchUser->id,
        ]);
        $gradeList = ClassBookList::create([
            'academic_year_id' => $year->id,
            'grade_id' => $grade->id,
            'name' => 'Grade 1 required books',
            'is_active' => true,
        ]);
        $gradeList->items()->create([
            'book_id' => $book->id,
            'quantity' => 1,
            'is_mandatory' => true,
            'price_type' => 'paid',
            'unit_price' => '0.00',
        ]);

        $this->actingAs($branchAdministrator)->withSession(['current_branch_id' => $main->id])
            ->post(route('admin.users.store'), [
                'name' => 'Created Branch User',
                'email' => 'created-branch-user@example.test',
                'password' => 'SecureBranchUser123!',
                'password_confirmation' => 'SecureBranchUser123!',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $created = User::where('email', 'created-branch-user@example.test')->firstOrFail();
        $this->assertTrue($created->hasRole('employee'));
        $this->assertDatabaseHas('branch_user', ['branch_id' => $main->id, 'user_id' => $created->id, 'is_default' => true]);

        $this->actingAs($branchAdministrator)->withSession(['current_branch_id' => $main->id])
            ->get(route('invoices.index'))
            ->assertOk();

        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee("Today's collection")
            ->assertDontSee('Outstanding balance')
            ->assertDontSee('Open ledger');
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])
            ->get(route('books.show', $book))
            ->assertOk()
            ->assertDontSee('Selling price')
            ->assertDontSee('Latest purchase price')
            ->assertDontSee('Full ledger');
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])
            ->get(route('books.index', ['tab' => 'packages']))
            ->assertOk()
            ->assertSee('Grade 1 required books')
            ->assertSee(route('class-lists.print', $gradeList), false)
            ->assertDontSee(route('class-lists.edit', $gradeList), false)
            ->assertDontSee('New package');
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])
            ->get(route('class-lists.print', $gradeList))
            ->assertOk()
            ->assertSee('Grade 1 book list');
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])
            ->get(route('class-lists.edit', $gradeList))
            ->assertForbidden();
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])
            ->get(route('distributions.create'))
            ->assertOk()
            ->assertDontSee('Payment at distribution')
            ->assertDontSee('Total sale')
            ->assertDontSee('data-distribution-sale-total', false);
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])
            ->post(route('distributions.store'), [
                'student_registration_id' => $registration->id,
                'items' => [['barcode' => $book->barcode, 'quantity' => 1]],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
        $distribution = BookDistribution::query()->where('student_registration_id', $registration->id)->firstOrFail();
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])
            ->get(route('distributions.show', $distribution))
            ->assertOk()
            ->assertSee('Print distribution list')
            ->assertDontSee('Unit price');
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])
            ->get(route('distributions.print', $distribution))
            ->assertOk()
            ->assertSee('Student Book Distribution')
            ->assertSee('Financially Protected Book')
            ->assertDontSee('12.50')
            ->assertDontSee('Balance due');
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])->get(route('invoices.index'))->assertForbidden();
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])->get(route('payments.index'))->assertForbidden();
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])->get(route('inventory.ledger'))->assertForbidden();
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])->get(route('transfers.index'))->assertForbidden();
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])->get(route('operations.index'))->assertForbidden();
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])
            ->get(route('reports.index'))
            ->assertOk()
            ->assertDontSee('Stock on hand')
            ->assertDontSee('Book distributions');
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])->get(route('reports.index', ['report' => 'stock']))->assertForbidden();
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])->get(route('reports.index', ['report' => 'distributions']))->assertForbidden();
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])->get(route('reports.index', ['report' => 'payments']))->assertForbidden();
    }
}
