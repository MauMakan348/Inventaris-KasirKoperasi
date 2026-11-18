<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function demo_items_have_an_opening_stock_movement(): void
    {
        $this->seed();

        $admin = User::query()->where('role', Role::Admin)->sole();

        $this->assertSame(10, Item::query()->count());

        foreach (Item::query()->get() as $item) {
            $this->assertDatabaseHas('stock_movements', [
                'item_id' => $item->id,
                'user_id' => $admin->id,
                'order_id' => null,
                'type' => 'RESTOCK',
                'quantity_change' => $item->stock,
                'stock_before' => 0,
                'stock_after' => $item->stock,
                'note' => 'Stok awal',
            ]);
        }

        $this->assertDatabaseCount('stock_movements', 10);
        $this->assertStockLedgerConsistent();
    }
}
