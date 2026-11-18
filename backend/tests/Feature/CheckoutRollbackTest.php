<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Repositories\StockMovementRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class CheckoutRollbackTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => Role::Admin]);
        $this->cashier = User::factory()->create(['role' => Role::Cashier]);
    }

    #[Test]
    public function hp01_one_short_item_rolls_back_the_whole_cart(): void
    {
        $item1 = $this->item('SKU-1', 'Barang 1', 5000, 10);
        $item2 = $this->item('SKU-2', 'Barang 2', 5000, 10);
        $item3 = $this->item('SKU-3', 'Barang 3', 5000, 10);
        $item4 = $this->item('SKU-4', 'Barang 4', 5000, 0);
        $item5 = $this->item('SKU-5', 'Barang 5', 5000, 1);

        $before = $this->snapshotState();

        Sanctum::actingAs($this->cashier);

        $this->postJson('/api/orders', [
            'payment_method' => 'CASH',
            'amount_paid' => 100000,
            'items' => [
                ['item_id' => $item1->id, 'quantity' => 1],
                ['item_id' => $item2->id, 'quantity' => 1],
                ['item_id' => $item3->id, 'quantity' => 1],
                ['item_id' => $item4->id, 'quantity' => 1],
                ['item_id' => $item5->id, 'quantity' => 3],
            ],
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'INSUFFICIENT_STOCK')
            ->assertJsonPath('error.message', 'Stok tidak mencukupi untuk satu atau lebih barang.')
            ->assertJsonCount(2, 'error.details')
            ->assertJsonPath('error.details.0.item_id', $item4->id)
            ->assertJsonPath('error.details.0.requested', 1)
            ->assertJsonPath('error.details.0.available', 0)
            ->assertJsonPath('error.details.1.item_id', $item5->id)
            ->assertJsonPath('error.details.1.requested', 3)
            ->assertJsonPath('error.details.1.available', 1);

        $this->assertSame($before, $this->snapshotState());
        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function hp05_duplicate_lines_are_merged_before_stock_check(): void
    {
        $item = $this->item('SKU-DUP', 'Barang Ganda', 3000, 5);

        Sanctum::actingAs($this->cashier);

        $response = $this->postJson('/api/orders', [
            'payment_method' => 'CASH',
            'amount_paid' => 15000,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 2],
                ['item_id' => $item->id, 'quantity' => 3],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 5)
            ->assertJsonPath('data.total_amount', 15000);

        $this->assertDatabaseHas('items', ['id' => $item->id, 'stock' => 0]);
        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function hp05_duplicate_lines_beyond_stock_are_rejected_together(): void
    {
        $item = $this->item('SKU-DUP2', 'Barang Ganda Dua', 3000, 4);

        $before = $this->snapshotState();

        Sanctum::actingAs($this->cashier);

        $this->postJson('/api/orders', [
            'payment_method' => 'CASH',
            'amount_paid' => 15000,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 2],
                ['item_id' => $item->id, 'quantity' => 3],
            ],
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'INSUFFICIENT_STOCK')
            ->assertJsonPath('error.details.0.item_id', $item->id)
            ->assertJsonPath('error.details.0.requested', 5)
            ->assertJsonPath('error.details.0.available', 4);

        $this->assertSame($before, $this->snapshotState());
        $this->assertDatabaseHas('items', ['id' => $item->id, 'stock' => 4]);
        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function hp07_failure_on_the_third_movement_rolls_everything_back(): void
    {
        $items = [
            $this->item('SKU-R1', 'Barang R1', 5000, 10),
            $this->item('SKU-R2', 'Barang R2', 5000, 10),
            $this->item('SKU-R3', 'Barang R3', 5000, 10),
        ];

        $before = $this->snapshotState();
        $this->bindFailingMovementRepository(failOnCall: 3);

        Sanctum::actingAs($this->cashier);

        $this->postJson('/api/orders', [
            'payment_method' => 'CASH',
            'amount_paid' => 15000,
            'items' => array_map(
                fn (Item $item): array => ['item_id' => $item->id, 'quantity' => 1],
                $items,
            ),
        ])->assertInternalServerError()->assertJsonPath('error.code', 'INTERNAL_ERROR');

        $this->assertSame($before, $this->snapshotState());
        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function hp07_failure_after_the_first_stock_change_rolls_everything_back(): void
    {
        $items = [
            $this->item('SKU-S1', 'Barang S1', 5000, 10),
            $this->item('SKU-S2', 'Barang S2', 5000, 10),
        ];

        $before = $this->snapshotState();
        $this->bindFailingMovementRepository(failOnCall: 2);

        Sanctum::actingAs($this->cashier);

        $this->postJson('/api/orders', [
            'payment_method' => 'CASH',
            'amount_paid' => 10000,
            'items' => array_map(
                fn (Item $item): array => ['item_id' => $item->id, 'quantity' => 1],
                $items,
            ),
        ])->assertInternalServerError()->assertJsonPath('error.code', 'INTERNAL_ERROR');

        $this->assertSame($before, $this->snapshotState());
        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function database_check_constraint_still_rejects_negative_stock(): void
    {
        $item = $this->item('SKU-CHK', 'Barang Cek', 5000, 5);

        try {
            DB::transaction(function () use ($item): void {
                DB::update('UPDATE items SET stock = -1 WHERE id = ?', [$item->id]);
            });

            $this->fail('The stock check constraint should reject a negative stock value.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('items_stock_non_negative_check', $exception->getMessage());
        }

        $this->assertDatabaseHas('items', ['id' => $item->id, 'stock' => 5]);
        $this->assertStockLedgerConsistent();
    }

    private function bindFailingMovementRepository(int $failOnCall): void
    {
        $this->app->bind(StockMovementRepository::class, fn () => new class($failOnCall) extends StockMovementRepository
        {
            private int $calls = 0;

            public function __construct(private readonly int $failOnCall) {}

            public function create(array $attributes): StockMovement
            {
                $this->calls++;

                if ($this->calls === $this->failOnCall) {
                    throw new RuntimeException('forced stock movement failure');
                }

                return parent::create($attributes);
            }
        });
    }

    private function item(string $sku, string $name, int $price, int $stock): Item
    {
        return $this->createItemWithLedger($this->admin, $sku, $name, $price, $stock);
    }
}
