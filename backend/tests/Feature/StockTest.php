<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Repositories\StockMovementRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function restock_adds_stock_and_records_movement(): void
    {
        $admin = $this->admin();
        $item = $this->createItem($admin, 10);

        $response = $this->postJson("/api/items/{$item['id']}/restock", [
            'quantity' => 5,
            'note' => 'Pembelian dari supplier',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.item.id', $item['id'])
            ->assertJsonPath('data.item.stock', 15)
            ->assertJsonPath('data.movement.type', 'RESTOCK')
            ->assertJsonPath('data.movement.quantity_change', 5)
            ->assertJsonPath('data.movement.stock_before', 10)
            ->assertJsonPath('data.movement.stock_after', 15)
            ->assertJsonPath('data.movement.order_id', null)
            ->assertJsonPath('data.movement.note', 'Pembelian dari supplier')
            ->assertJsonPath('data.movement.user.id', $admin->id)
            ->assertJsonPath('data.movement.user.name', $admin->name);

        $this->assertDatabaseHas('items', ['id' => $item['id'], 'stock' => 15]);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item['id'],
            'user_id' => $admin->id,
            'type' => 'RESTOCK',
            'quantity_change' => 5,
            'stock_before' => 10,
            'stock_after' => 15,
        ]);

        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function restock_rejects_invalid_quantity(): void
    {
        $admin = $this->admin();
        $item = $this->createItem($admin, 10);

        $cases = [
            [0, 'Jumlah restock minimal 1.'],
            [-5, 'Jumlah restock minimal 1.'],
            [1.5, 'Jumlah restock harus berupa bilangan bulat.'],
            ['abc', 'Jumlah restock harus berupa bilangan bulat.'],
            [1000001, 'Jumlah restock maksimal 1000000.'],
        ];

        foreach ($cases as [$quantity, $message]) {
            $this->postJson("/api/items/{$item['id']}/restock", ['quantity' => $quantity])
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_ERROR')
                ->assertJsonPath('error.details.quantity.0', $message);
        }

        $this->assertDatabaseHas('items', ['id' => $item['id'], 'stock' => 10]);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function restock_returns_not_found_for_unknown_item(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/items/999999/restock', ['quantity' => 1])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    #[Test]
    public function restock_is_forbidden_for_non_admin(): void
    {
        $admin = $this->admin();
        $item = $this->createItem($admin, 10);

        foreach ([Role::Cashier, Role::Member] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->postJson("/api/items/{$item['id']}/restock", ['quantity' => 1])
                ->assertForbidden()
                ->assertJsonPath('error.code', 'FORBIDDEN');
        }

        $this->assertDatabaseHas('items', ['id' => $item['id'], 'stock' => 10]);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    #[Test]
    public function restock_requires_authentication(): void
    {
        $this->postJson('/api/items/1/restock', ['quantity' => 1])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    #[Test]
    public function opname_can_increase_stock(): void
    {
        $admin = $this->admin();
        $item = $this->createItem($admin, 10);

        $this->postJson("/api/items/{$item['id']}/stock-opname", [
            'actual_stock' => 25,
            'note' => 'Hasil hitung fisik',
        ])->assertOk()
            ->assertJsonPath('data.item.stock', 25)
            ->assertJsonPath('data.movement.type', 'OPNAME')
            ->assertJsonPath('data.movement.quantity_change', 15)
            ->assertJsonPath('data.movement.stock_before', 10)
            ->assertJsonPath('data.movement.stock_after', 25)
            ->assertJsonPath('data.movement.note', 'Hasil hitung fisik');

        $this->assertDatabaseHas('items', ['id' => $item['id'], 'stock' => 25]);
        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function opname_can_decrease_stock(): void
    {
        $admin = $this->admin();
        $item = $this->createItem($admin, 10);

        $this->postJson("/api/items/{$item['id']}/stock-opname", ['actual_stock' => 4])
            ->assertOk()
            ->assertJsonPath('data.item.stock', 4)
            ->assertJsonPath('data.movement.type', 'OPNAME')
            ->assertJsonPath('data.movement.quantity_change', -6)
            ->assertJsonPath('data.movement.stock_before', 10)
            ->assertJsonPath('data.movement.stock_after', 4);

        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function opname_without_difference_writes_no_movement(): void
    {
        $admin = $this->admin();
        $item = $this->createItem($admin, 10);

        $this->postJson("/api/items/{$item['id']}/stock-opname", ['actual_stock' => 10])
            ->assertOk()
            ->assertJsonPath('data.item.stock', 10)
            ->assertJsonPath('data.movement', null);

        $this->assertDatabaseHas('items', ['id' => $item['id'], 'stock' => 10]);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function opname_rejects_negative_actual_stock(): void
    {
        $admin = $this->admin();
        $item = $this->createItem($admin, 10);

        $this->postJson("/api/items/{$item['id']}/stock-opname", ['actual_stock' => -1])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.actual_stock.0', 'Stok fisik tidak boleh negatif.');

        $this->assertDatabaseHas('items', ['id' => $item['id'], 'stock' => 10]);
        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function opname_returns_not_found_for_unknown_item(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/items/999999/stock-opname', ['actual_stock' => 1])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    #[Test]
    public function opname_is_forbidden_for_non_admin(): void
    {
        $admin = $this->admin();
        $item = $this->createItem($admin, 10);

        foreach ([Role::Cashier, Role::Member] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->postJson("/api/items/{$item['id']}/stock-opname", ['actual_stock' => 1])
                ->assertForbidden()
                ->assertJsonPath('error.code', 'FORBIDDEN');
        }

        $this->assertDatabaseHas('items', ['id' => $item['id'], 'stock' => 10]);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    #[Test]
    public function movement_history_is_newest_first_with_full_detail(): void
    {
        $admin = $this->admin();
        $item = $this->createItem($admin, 10);

        $this->postJson("/api/items/{$item['id']}/restock", ['quantity' => 5, 'note' => 'Restock']);
        $this->postJson("/api/items/{$item['id']}/stock-opname", ['actual_stock' => 20, 'note' => 'Opname']);

        $response = $this->getJson("/api/items/{$item['id']}/movements");

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.type', 'OPNAME')
            ->assertJsonPath('data.1.type', 'RESTOCK')
            ->assertJsonPath('data.2.type', 'RESTOCK')
            ->assertJsonPath('data.0.stock_before', 15)
            ->assertJsonPath('data.0.stock_after', 20)
            ->assertJsonPath('data.1.stock_before', 10)
            ->assertJsonPath('data.1.stock_after', 15)
            ->assertJsonPath('data.2.stock_before', 0)
            ->assertJsonPath('data.2.stock_after', 10)
            ->assertJsonPath('data.0.user.id', $admin->id)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'item_id', 'type', 'quantity_change', 'stock_before', 'stock_after', 'order_id', 'note', 'user' => ['id', 'name'], 'created_at'],
                ],
            ]);

        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function movement_history_requires_admin_and_existing_item(): void
    {
        $admin = $this->admin();
        $item = $this->createItem($admin, 1);

        foreach ([Role::Cashier, Role::Member] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->getJson("/api/items/{$item['id']}/movements")
                ->assertForbidden()
                ->assertJsonPath('error.code', 'FORBIDDEN');
        }

        Sanctum::actingAs($admin);

        $this->getJson('/api/items/999999/movements')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    #[Test]
    public function failed_movement_write_rolls_stock_back(): void
    {
        $admin = $this->admin();
        $item = $this->createItem($admin, 10);

        $movementCount = StockMovement::query()->count();

        $this->app->bind(StockMovementRepository::class, fn () => new class extends StockMovementRepository
        {
            public function create(array $attributes): StockMovement
            {
                throw new RuntimeException('forced movement failure');
            }
        });

        $this->postJson("/api/items/{$item['id']}/restock", ['quantity' => 7])
            ->assertInternalServerError()
            ->assertJsonPath('error.code', 'INTERNAL_ERROR');

        $this->assertDatabaseHas('items', ['id' => $item['id'], 'stock' => 10]);
        $this->assertDatabaseCount('stock_movements', $movementCount);
        $this->assertStockLedgerConsistent();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin]);
    }

    /**
     * Create an item through the API so its opening ledger stays consistent.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function createItem(User $admin, int $stock, array $overrides = []): array
    {
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/items', array_merge([
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'name' => 'Barang Uji',
            'price' => 5000,
            'stock' => $stock,
        ], $overrides))->assertCreated();

        return $response->json('data');
    }
}
