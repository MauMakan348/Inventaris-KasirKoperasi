<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private User $memberOne;

    private User $memberTwo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => Role::Admin]);
        $this->cashier = User::factory()->create(['role' => Role::Cashier]);
        $this->memberOne = User::factory()->create(['role' => Role::Member]);
        $this->memberTwo = User::factory()->create(['role' => Role::Member]);
    }

    #[Test]
    public function members_only_see_their_own_orders(): void
    {
        $item = $this->item('SKU-H1', 'Barang Riwayat', 5000, 50);

        $memberOneOrder = $this->checkout($this->memberOne, [
            ['item_id' => $item->id, 'quantity' => 1],
        ]);

        $memberTwoOrder = $this->checkout($this->memberTwo, [
            ['item_id' => $item->id, 'quantity' => 2],
        ]);

        Sanctum::actingAs($this->memberOne);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $memberOneOrder['id'])
            ->assertJsonPath('data.0.user.id', $this->memberOne->id)
            ->assertJsonMissingPath('data.0.items');

        $this->getJson('/api/orders/'.$memberOneOrder['id'])
            ->assertOk()
            ->assertJsonPath('data.id', $memberOneOrder['id'])
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.item_id', $item->id);

        $this->getJson('/api/orders/'.$memberTwoOrder['id'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');

        Sanctum::actingAs($this->cashier);

        $this->getJson('/api/orders')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/orders/'.$memberTwoOrder['id'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $this->memberTwo->id);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/orders')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/orders/'.$memberOneOrder['id'])->assertOk();
    }

    #[Test]
    public function order_list_is_newest_first(): void
    {
        $item = $this->item('SKU-H2', 'Barang Urutan', 5000, 50);

        $older = $this->checkout($this->cashier, [['item_id' => $item->id, 'quantity' => 1]], 'CASH', 5000);
        $newer = $this->checkout($this->cashier, [['item_id' => $item->id, 'quantity' => 1]], 'CASH', 5000);

        Sanctum::actingAs($this->cashier);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer['id'])
            ->assertJsonPath('data.1.id', $older['id']);
    }

    #[Test]
    public function order_detail_keeps_the_price_and_name_snapshot(): void
    {
        $item = $this->item('SKU-H3', 'Nama Lama', 5000, 10);

        $order = $this->checkout($this->memberOne, [
            ['item_id' => $item->id, 'quantity' => 2],
        ]);

        Sanctum::actingAs($this->admin);

        $this->putJson('/api/items/'.$item->id, [
            'sku' => 'SKU-H3',
            'name' => 'Nama Baru',
            'price' => 9000,
        ])->assertOk();

        Sanctum::actingAs($this->memberOne);

        $this->getJson('/api/orders/'.$order['id'])
            ->assertOk()
            ->assertJsonPath('data.items.0.item_name', 'Nama Lama')
            ->assertJsonPath('data.items.0.unit_price', 5000)
            ->assertJsonPath('data.items.0.subtotal', 10000);

        $this->assertDatabaseHas('items', ['id' => $item->id, 'name' => 'Nama Baru', 'price' => 9000]);
    }

    #[Test]
    public function ledger_stays_consistent_after_mixed_operations(): void
    {
        $item = $this->item('SKU-H4', 'Barang Campuran', 4000, 20);

        $this->checkout($this->memberOne, [['item_id' => $item->id, 'quantity' => 3]]);
        $this->checkout($this->cashier, [['item_id' => $item->id, 'quantity' => 4]], 'CASH', 16000);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/items/'.$item->id.'/restock', ['quantity' => 10])
            ->assertOk();

        $this->postJson('/api/items/'.$item->id.'/stock-opname', ['actual_stock' => 30])
            ->assertOk();

        $this->assertDatabaseHas('items', ['id' => $item->id, 'stock' => 30]);
        $this->assertDatabaseCount('orders', 2);
        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function order_history_requires_authentication(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
        $this->getJson('/api/orders/1')->assertUnauthorized();
    }

    /**
     * @param  list<array{item_id: int, quantity: int}>  $items
     * @return array<string, mixed>
     */
    private function checkout(User $user, array $items, string $method = 'TRANSFER', ?int $amountPaid = null): array
    {
        Sanctum::actingAs($user);

        $payload = [
            'payment_method' => $method,
            'items' => $items,
        ];

        if ($amountPaid !== null) {
            $payload['amount_paid'] = $amountPaid;
        }

        return $this->postJson('/api/orders', $payload)
            ->assertCreated()
            ->json('data');
    }

    private function item(string $sku, string $name, int $price, int $stock): Item
    {
        return $this->createItemWithLedger($this->admin, $sku, $name, $price, $stock);
    }
}
