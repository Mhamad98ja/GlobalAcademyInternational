<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Branch;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authorized_user_can_post_a_stock_adjustment(): void
    {
        $role = Role::create(['name' => 'super-administrator', 'label' => 'Super Administrator']);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);
        $mainBranch = Branch::where('code', 'MAIN')->firstOrFail();
        $book = Book::create([
            'sku' => 'ADJUST-001',
            'barcode' => '9780000000012',
            'title' => 'Adjustment test book',
        ]);

        $this->actingAs($user)
            ->withSession(['current_branch_id' => $mainBranch->id])
            ->from(route('inventory.adjustments'))
            ->post(route('inventory.adjustments.store'), [
                'book_id' => $book->id,
                'quantity_change' => 4,
                'reason' => 'Opening count',
                'notes' => 'Verified stock count',
            ])
            ->assertRedirect(route('inventory.adjustments'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('stock_adjustments', [
            'branch_id' => $mainBranch->id,
            'book_id' => $book->id,
            'quantity_change' => 4,
            'reason' => 'Opening count',
            'created_by' => $user->id,
        ]);
        $this->assertDatabaseHas('branch_book_stocks', [
            'branch_id' => $mainBranch->id,
            'book_id' => $book->id,
            'quantity_on_hand' => 4,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'branch_id' => $mainBranch->id,
            'book_id' => $book->id,
            'type' => 'manual_adjustment',
            'quantity_change' => 4,
        ]);
    }

    public function test_inventory_ledger_shows_page_numbers_for_multiple_pages(): void
    {
        $role = Role::create(['name' => 'super-administrator', 'label' => 'Super Administrator']);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);
        $mainBranch = Branch::where('code', 'MAIN')->firstOrFail();
        $book = Book::create(['sku' => 'LEDGER-001', 'barcode' => '9780000000029', 'title' => 'Ledger pagination book']);

        foreach (range(1, 26) as $number) {
            StockMovement::withoutGlobalScopes()->create([
                'branch_id' => $mainBranch->id,
                'book_id' => $book->id,
                'type' => 'manual_adjustment',
                'quantity_before' => $number - 1,
                'quantity_change' => 1,
                'quantity_after' => $number,
                'user_id' => $user->id,
                'reason' => match ($number) {
                    1 => 'Oldest ledger marker',
                    26 => 'Newest ledger marker',
                    default => 'Ledger movement '.$number,
                },
            ]);
        }

        $this->actingAs($user)->withSession(['current_branch_id' => $mainBranch->id])
            ->get(route('inventory.ledger'))
            ->assertOk()
            ->assertSee('Showing 1-25 of 26')
            ->assertSee('aria-label="Pagination"', false)
            ->assertSee('page=2', false)
            ->assertSee('name="search"', false)
            ->assertDontSee('name="book_id"', false)
            ->assertSee('Newest ledger marker')
            ->assertDontSee('Oldest ledger marker');
        $this->actingAs($user)->withSession(['current_branch_id' => $mainBranch->id])
            ->get(route('inventory.ledger', ['page' => 2]))
            ->assertOk()
            ->assertSee('Showing 26-26 of 26')
            ->assertSee('Oldest ledger marker')
            ->assertDontSee('Newest ledger marker');
        $this->actingAs($user)->withSession(['current_branch_id' => $mainBranch->id])
            ->get(route('inventory.ledger', ['search' => 'not-a-matching-book']))
            ->assertOk()
            ->assertSee('No inventory movements found.');
    }
}
