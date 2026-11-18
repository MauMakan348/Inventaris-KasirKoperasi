<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function item_list_is_empty_for_every_authenticated_role(): void
    {
        foreach ([Role::Admin, Role::Cashier, Role::Member] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->getJson('/api/items')
                ->assertOk()
                ->assertExactJson(['data' => []]);
        }
    }

    #[Test]
    public function item_list_returns_current_stock_ordered_by_name(): void
    {
        Sanctum::actingAs($this->user());

        Item::factory()->create(['name' => 'Zebra', 'sku' => 'SKU-Z']);
        Item::factory()->withStock(7)->create(['name' => 'Alpha', 'sku' => 'SKU-A']);
        Item::factory()->create(['name' => 'Mango', 'sku' => 'SKU-M']);

        $response = $this->getJson('/api/items')->assertOk();

        $response->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Alpha')
            ->assertJsonPath('data.0.sku', 'SKU-A')
            ->assertJsonPath('data.0.stock', 7)
            ->assertJsonPath('data.1.name', 'Mango')
            ->assertJsonPath('data.2.name', 'Zebra')
            ->assertJsonStructure([
                'data' => [
                    ['id', 'sku', 'name', 'price', 'stock', 'created_at', 'updated_at'],
                ],
            ]);
    }

    #[Test]
    public function item_detail_is_available_to_every_role(): void
    {
        $item = Item::factory()->withStock(4)->create();

        foreach ([Role::Admin, Role::Cashier, Role::Member] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->getJson('/api/items/'.$item->id)
                ->assertOk()
                ->assertJsonPath('data.id', $item->id)
                ->assertJsonPath('data.sku', $item->sku)
                ->assertJsonPath('data.stock', 4);
        }
    }

    #[Test]
    public function item_detail_returns_not_found_for_unknown_id(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/items/999999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    #[Test]
    public function admin_can_create_item_with_initial_stock_movement(): void
    {
        $admin = $this->user();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/items', [
            'sku' => 'SKU-BARU',
            'name' => 'Buku Gambar',
            'price' => 12000,
            'stock' => 15,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sku', 'SKU-BARU')
            ->assertJsonPath('data.name', 'Buku Gambar')
            ->assertJsonPath('data.price', 12000)
            ->assertJsonPath('data.stock', 15);

        $this->assertDatabaseHas('items', [
            'sku' => 'SKU-BARU',
            'name' => 'Buku Gambar',
            'price' => 12000,
            'stock' => 15,
        ]);

        $itemId = $response->json('data.id');

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $itemId,
            'user_id' => $admin->id,
            'order_id' => null,
            'type' => StockMovementType::Restock->value,
            'quantity_change' => 15,
            'stock_before' => 0,
            'stock_after' => 15,
        ]);
    }

    #[Test]
    public function admin_can_create_item_with_zero_stock_without_movement(): void
    {
        Sanctum::actingAs($this->user());

        $this->postJson('/api/items', [
            'sku' => 'SKU-KOSONG',
            'name' => 'Barang Kosong',
            'price' => 5000,
            'stock' => 0,
        ])->assertCreated()->assertJsonPath('data.stock', 0);

        $this->assertDatabaseCount('stock_movements', 0);
    }

    #[Test]
    public function create_rejects_duplicate_sku(): void
    {
        Sanctum::actingAs($this->user());

        Item::factory()->create(['sku' => 'SKU-DUP']);

        $this->postJson('/api/items', [
            'sku' => 'SKU-DUP',
            'name' => 'Barang Lain',
            'price' => 5000,
            'stock' => 1,
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.sku.0', 'SKU sudah digunakan.');

        $this->assertDatabaseCount('items', 1);
    }

    #[Test]
    public function create_rejects_non_positive_price(): void
    {
        Sanctum::actingAs($this->user());

        foreach ([0, -1] as $price) {
            $this->postJson('/api/items', [
                'sku' => 'SKU-HARGA-'.abs($price),
                'name' => 'Harga Salah',
                'price' => $price,
                'stock' => 1,
            ])->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_ERROR')
                ->assertJsonPath('error.details.price.0', 'Harga harus lebih dari 0.');
        }

        $this->assertDatabaseCount('items', 0);
    }

    #[Test]
    public function create_rejects_negative_stock(): void
    {
        Sanctum::actingAs($this->user());

        $this->postJson('/api/items', [
            'sku' => 'SKU-MINUS',
            'name' => 'Stok Minus',
            'price' => 5000,
            'stock' => -1,
        ])->assertUnprocessable()
            ->assertJsonPath('error.details.stock.0', 'Stok awal tidak boleh negatif.');

        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    #[Test]
    public function admin_can_update_item_without_changing_stock(): void
    {
        Sanctum::actingAs($this->user());

        $item = Item::factory()->withStock(10)->create([
            'sku' => 'SKU-OLD',
            'name' => 'Nama Lama',
            'price' => 5000,
        ]);

        $this->putJson('/api/items/'.$item->id, [
            'sku' => 'SKU-NEW',
            'name' => 'Nama Baru',
            'price' => 7500,
            'stock' => 999,
        ])->assertOk()
            ->assertJsonPath('data.sku', 'SKU-NEW')
            ->assertJsonPath('data.name', 'Nama Baru')
            ->assertJsonPath('data.price', 7500)
            ->assertJsonPath('data.stock', 10);

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'sku' => 'SKU-NEW',
            'name' => 'Nama Baru',
            'price' => 7500,
            'stock' => 10,
        ]);
    }

    #[Test]
    public function update_allows_keeping_the_same_sku(): void
    {
        Sanctum::actingAs($this->user());

        $item = Item::factory()->create(['sku' => 'SKU-SAMA']);

        $this->putJson('/api/items/'.$item->id, [
            'sku' => 'SKU-SAMA',
            'name' => 'Nama Diperbarui',
            'price' => 9000,
        ])->assertOk()->assertJsonPath('data.name', 'Nama Diperbarui');
    }

    #[Test]
    public function update_rejects_sku_owned_by_another_item(): void
    {
        Sanctum::actingAs($this->user());

        $item = Item::factory()->create(['sku' => 'SKU-SATU']);
        Item::factory()->create(['sku' => 'SKU-DUA']);

        $this->putJson('/api/items/'.$item->id, [
            'sku' => 'SKU-DUA',
            'name' => $item->name,
            'price' => 5000,
        ])->assertUnprocessable()
            ->assertJsonPath('error.details.sku.0', 'SKU sudah digunakan.');

        $this->assertDatabaseHas('items', ['id' => $item->id, 'sku' => 'SKU-SATU']);
    }

    #[Test]
    public function update_rejects_non_positive_price(): void
    {
        Sanctum::actingAs($this->user());

        $item = Item::factory()->create(['price' => 5000]);

        $this->putJson('/api/items/'.$item->id, [
            'sku' => $item->sku,
            'name' => $item->name,
            'price' => 0,
        ])->assertUnprocessable()
            ->assertJsonPath('error.details.price.0', 'Harga harus lebih dari 0.');

        $this->assertDatabaseHas('items', ['id' => $item->id, 'price' => 5000]);
    }

    #[Test]
    public function update_returns_not_found_for_unknown_id(): void
    {
        Sanctum::actingAs($this->user());

        $this->putJson('/api/items/999999', [
            'sku' => 'SKU-X',
            'name' => 'Tidak Ada',
            'price' => 5000,
        ])->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }

    #[Test]
    public function non_admin_cannot_create_or_update_items(): void
    {
        $item = Item::factory()->withStock(3)->create(['sku' => 'SKU-TETAP']);

        foreach ([Role::Cashier, Role::Member] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->postJson('/api/items', [
                'sku' => 'SKU-DILARANG',
                'name' => 'Dilarang',
                'price' => 5000,
                'stock' => 1,
            ])->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');

            $this->putJson('/api/items/'.$item->id, [
                'sku' => 'SKU-DIUBAH',
                'name' => 'Diubah',
                'price' => 9000,
            ])->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');
        }

        $this->assertDatabaseMissing('items', ['sku' => 'SKU-DILARANG']);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'sku' => 'SKU-TETAP', 'stock' => 3]);
    }

    #[Test]
    public function item_endpoints_require_authentication(): void
    {
        $item = Item::factory()->create();

        $this->getJson('/api/items')->assertUnauthorized();
        $this->getJson('/api/items/'.$item->id)->assertUnauthorized();
        $this->postJson('/api/items', [])->assertUnauthorized();
        $this->putJson('/api/items/'.$item->id, [])->assertUnauthorized();
    }

    private function user(Role $role = Role::Admin): User
    {
        return User::factory()->create(['role' => $role]);
    }
}
