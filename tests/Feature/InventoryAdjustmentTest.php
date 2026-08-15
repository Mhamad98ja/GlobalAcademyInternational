<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Branch;
use App\Models\Role;
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
}
