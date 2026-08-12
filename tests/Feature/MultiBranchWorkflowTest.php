<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\Branch;
use App\Models\BranchBookStock;
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

    public function test_branch_transfer_moves_stock_only_between_the_selected_branches(): void
    {
        $user = $this->user('manager@example.test');
        [$main, $north] = $this->branches($user);
        $book = Book::create(['sku' => 'TRF-BOOK', 'barcode' => '978000090', 'title' => 'Transfer Book']);
        BranchBookStock::create(['branch_id' => $main->id, 'book_id' => $book->id, 'quantity_on_hand' => 12]);
        BranchBookStock::create(['branch_id' => $north->id, 'book_id' => $book->id, 'quantity_on_hand' => 2]);

        $transfer = app(BookTransferService::class)->betweenBranches($main, $north, [['book_id' => $book->id, 'quantity' => 5]], null, $user);

        $this->assertSame('branch_to_branch', $transfer->transfer_type);
        $this->assertDatabaseHas('branch_book_stocks', ['branch_id' => $main->id, 'book_id' => $book->id, 'quantity_on_hand' => 7]);
        $this->assertDatabaseHas('branch_book_stocks', ['branch_id' => $north->id, 'book_id' => $book->id, 'quantity_on_hand' => 7]);
        $this->assertDatabaseHas('stock_movements', ['branch_id' => $main->id, 'type' => 'branch_transfer_out', 'quantity_change' => -5]);
        $this->assertDatabaseHas('stock_movements', ['branch_id' => $north->id, 'type' => 'branch_transfer_in', 'quantity_change' => 5]);
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

    public function test_administrator_can_create_and_update_a_branch_with_user_assignments(): void
    {
        $administrator = $this->user('administrator@example.test');
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
            ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('branches', ['id' => $south->id, 'name' => 'South Campus']);
        $this->assertDatabaseHas('branch_user', ['branch_id' => $south->id, 'user_id' => $administrator->id, 'is_default' => true]);
    }
}
