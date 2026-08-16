<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookTransfer;
use App\Models\Branch;
use App\Models\BranchBookStock;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolLevel;
use App\Models\Grade;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Models\User;
use App\Models\UserBookStock;
use App\Services\BookTransferService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'dashboard.view', 'students.view', 'students.manage', 'books.view', 'distributions.create',
            'inventory.view', 'inventory.transfer', 'people.manage', 'reports.view',
        ])->pluck('id'));
        $user = User::factory()->create(['email' => $email, 'is_active' => true]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    public function test_branch_transfer_moves_stock_only_between_the_selected_branches(): void
    {
        $user = $this->user('manager@example.test');
        [$main, $north] = $this->branches($user);
        $book = Book::create(['sku' => 'TRF-BOOK', 'barcode' => '978000090', 'title' => 'Transfer Book']);
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
        $this->assertMatchesRegularExpression('/^TRF-\d{8}-[A-Z0-9]{10}$/', $transfer->transfer_number);
        $this->assertDatabaseHas('branch_book_stocks', ['branch_id' => $main->id, 'book_id' => $book->id, 'quantity_on_hand' => 7]);
        $this->assertDatabaseHas('branch_book_stocks', ['branch_id' => $north->id, 'book_id' => $book->id, 'quantity_on_hand' => 7]);
        $this->assertDatabaseHas('stock_movements', ['branch_id' => $main->id, 'type' => 'branch_transfer_out', 'quantity_change' => -5]);
        $this->assertDatabaseHas('stock_movements', ['branch_id' => $north->id, 'type' => 'branch_transfer_in', 'quantity_change' => 5]);
    }

    public function test_completed_branch_transfer_can_be_reversed_once_and_restores_branch_stock(): void
    {
        $user = $this->user('manager@example.test');
        [$main, $north] = $this->branches($user);
        $book = Book::create(['sku' => 'REVERSE-BOOK', 'barcode' => '978000096', 'title' => 'Reversible Transfer Book']);
        BranchBookStock::create(['branch_id' => $main->id, 'book_id' => $book->id, 'quantity_on_hand' => 12]);
        BranchBookStock::create(['branch_id' => $north->id, 'book_id' => $book->id, 'quantity_on_hand' => 2]);

        $transfer = app(BookTransferService::class)->betweenBranches($main, $north, [['book_id' => $book->id, 'quantity' => 5]], null, $user);

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

        $this->actingAs($user)->withSession(['current_branch_id' => $main->id])
            ->get(route('transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Reversed')
            ->assertDontSee('Reverse transfer');

        $this->actingAs($user)->withSession(['current_branch_id' => $main->id])
            ->post(route('transfers.reverse', $transfer))
            ->assertSessionHasErrors('transfer');
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
            ->assertDontSee('Main Only Book');
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
                'default_user_id' => (string) $employee->id,
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
                'default_user_id' => (string) $administrator->id,
                'branch_administrator_id' => (string) $employee->id,
            ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('branches', ['id' => $south->id, 'name' => 'South Campus']);
        $this->assertDatabaseHas('branch_user', ['branch_id' => $south->id, 'user_id' => $administrator->id, 'is_default' => true]);
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
            ->assertSee('Delete branch');

        $this->actingAs($controller)->withSession(['current_branch_id' => $main->id])
            ->get(route('admin.imports'))
            ->assertOk()
            ->assertSee('Import students')
            ->assertSee('Import books');

        $this->actingAs($branchAdministrator)->withSession(['current_branch_id' => $main->id])
            ->get(route('branches.index'))
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
            ->assertDontSee('Outstanding balance');
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])
            ->get(route('books.show', $book))
            ->assertOk()
            ->assertDontSee('Selling price')
            ->assertDontSee('Latest purchase price');
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])
            ->get(route('distributions.create'))
            ->assertOk()
            ->assertDontSee('Payment at distribution')
            ->assertDontSee('Total sale')
            ->assertDontSee('data-distribution-sale-total', false);
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])->get(route('invoices.index'))->assertForbidden();
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])->get(route('payments.index'))->assertForbidden();
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])->get(route('operations.index'))->assertForbidden();
        $this->actingAs($branchUser)->withSession(['current_branch_id' => $main->id])->get(route('reports.index', ['report' => 'payments']))->assertForbidden();
    }
}
