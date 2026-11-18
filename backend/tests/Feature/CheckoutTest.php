<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => Role::Admin]);
        $this->cashier = User::factory()->create(['role' => Role::Cashier]);
        $this->member = User::factory()->create(['role' => Role::Member]);
    }

    #[Test]
    public function pos_cash_checkout_stores_order_and_returns_change(): void
    {
        $itemA = $this->item('SKU-A', 'Barang A', 5000, 10);
        $itemB = $this->item('SKU-B', 'Barang B', 3000, 5);

        Sanctum::actingAs($this->cashier);

        $response = $this->postJson('/api/orders', [
            'payment_method' => 'CASH',
            'amount_paid' => 20000,
            'items' => [
                ['item_id' => $itemA->id, 'quantity' => 2],
                ['item_id' => $itemB->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.channel', 'POS')
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.payment_method', 'CASH')
            ->assertJsonPath('data.total_amount', 13000)
            ->assertJsonPath('data.amount_paid', 20000)
            ->assertJsonPath('data.change_amount', 7000)
            ->assertJsonPath('data.user.id', $this->cashier->id)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.item_id', $itemA->id)
            ->assertJsonPath('data.items.0.item_name', 'Barang A')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_price', 5000)
            ->assertJsonPath('data.items.0.subtotal', 10000)
            ->assertJsonPath('data.items.1.item_id', $itemB->id)
            ->assertJsonPath('data.items.1.subtotal', 3000);

        $this->assertMatchesRegularExpression(
            '/^KOP-\d{8}-[A-Z0-9]{8}$/',
            $response->json('data.order_code'),
        );

        $orderId = $response->json('data.id');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'user_id' => $this->cashier->id,
            'channel' => 'POS',
            'status' => 'COMPLETED',
            'payment_method' => 'CASH',
            'total_amount' => 13000,
            'amount_paid' => 20000,
            'change_amount' => 7000,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'item_id' => $itemA->id,
            'item_name' => 'Barang A',
            'quantity' => 2,
            'unit_price' => 5000,
            'subtotal' => 10000,
        ]);

        $this->assertDatabaseHas('items', ['id' => $itemA->id, 'stock' => 8]);
        $this->assertDatabaseHas('items', ['id' => $itemB->id, 'stock' => 4]);

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $itemA->id,
            'user_id' => $this->cashier->id,
            'order_id' => $orderId,
            'type' => 'SALE_POS',
            'quantity_change' => -2,
            'stock_before' => 10,
            'stock_after' => 8,
            'note' => null,
        ]);

        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function pos_transfer_ignores_amount_paid(): void
    {
        $item = $this->item('SKU-T', 'Barang Transfer', 5000, 3);

        Sanctum::actingAs($this->cashier);

        $this->postJson('/api/orders', [
            'payment_method' => 'TRANSFER',
            'amount_paid' => 100,
            'items' => [['item_id' => $item->id, 'quantity' => 2]],
        ])->assertCreated()
            ->assertJsonPath('data.channel', 'POS')
            ->assertJsonPath('data.payment_method', 'TRANSFER')
            ->assertJsonPath('data.total_amount', 10000)
            ->assertJsonPath('data.amount_paid', 10000)
            ->assertJsonPath('data.change_amount', 0);

        $this->assertDatabaseHas('items', ['id' => $item->id, 'stock' => 1]);
        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function online_transfer_checkout_uses_online_channel(): void
    {
        $item = $this->item('SKU-O', 'Barang Online', 4000, 5);

        Sanctum::actingAs($this->member);

        $response = $this->postJson('/api/orders', [
            'payment_method' => 'TRANSFER',
            'items' => [['item_id' => $item->id, 'quantity' => 2]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.channel', 'ONLINE')
            ->assertJsonPath('data.payment_method', 'TRANSFER')
            ->assertJsonPath('data.total_amount', 8000)
            ->assertJsonPath('data.amount_paid', 8000)
            ->assertJsonPath('data.change_amount', 0)
            ->assertJsonPath('data.user.id', $this->member->id);

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'order_id' => $response->json('data.id'),
            'type' => 'SALE_ONLINE',
            'quantity_change' => -2,
        ]);

        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function admin_cannot_checkout(): void
    {
        $item = $this->item('SKU-ADM', 'Barang Admin', 5000, 5);
        $before = $this->snapshotState();

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/orders', [
            'payment_method' => 'CASH',
            'amount_paid' => 5000,
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
        ])->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertSame($before, $this->snapshotState());
    }

    #[Test]
    public function checkout_requires_authentication(): void
    {
        $this->postJson('/api/orders', [
            'payment_method' => 'CASH',
            'amount_paid' => 5000,
            'items' => [['item_id' => 1, 'quantity' => 1]],
        ])->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    #[Test]
    public function member_cannot_pay_with_cash(): void
    {
        $item = $this->item('SKU-MC', 'Barang Member', 5000, 5);
        $before = $this->snapshotState();

        Sanctum::actingAs($this->member);

        $this->postJson('/api/orders', [
            'payment_method' => 'CASH',
            'amount_paid' => 5000,
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.payment_method.0', 'Pembayaran CASH tidak tersedia untuk pesanan online.');

        $this->assertSame($before, $this->snapshotState());
    }

    #[Test]
    public function invalid_checkout_payloads_change_nothing(): void
    {
        $item = $this->item('SKU-V', 'Barang Validasi', 5000, 100);
        $before = $this->snapshotState();

        $line = ['item_id' => $item->id, 'quantity' => 1];

        $cases = [
            'empty cart' => ['items' => [], 'payment_method' => 'CASH', 'amount_paid' => 0],
            'too many lines' => [
                'items' => array_fill(0, 51, $line),
                'payment_method' => 'CASH',
                'amount_paid' => 100000,
            ],
            'zero quantity' => ['items' => [['item_id' => $item->id, 'quantity' => 0]], 'payment_method' => 'CASH', 'amount_paid' => 5000],
            'negative quantity' => ['items' => [['item_id' => $item->id, 'quantity' => -1]], 'payment_method' => 'CASH', 'amount_paid' => 5000],
            'fractional quantity' => ['items' => [['item_id' => $item->id, 'quantity' => 1.5]], 'payment_method' => 'CASH', 'amount_paid' => 5000],
            'text quantity' => ['items' => [['item_id' => $item->id, 'quantity' => 'abc']], 'payment_method' => 'CASH', 'amount_paid' => 5000],
            'quantity above limit' => ['items' => [['item_id' => $item->id, 'quantity' => 1001]], 'payment_method' => 'CASH', 'amount_paid' => 5000],
            'missing payment method' => ['items' => [$line], 'amount_paid' => 5000],
            'invalid payment method' => ['items' => [$line], 'payment_method' => 'BITCOIN', 'amount_paid' => 5000],
            'cash without amount paid' => ['items' => [$line], 'payment_method' => 'CASH'],
            'text item id' => ['items' => [['item_id' => 'abc', 'quantity' => 1]], 'payment_method' => 'CASH', 'amount_paid' => 5000],
            'scalar line' => ['items' => ['bukan-objek'], 'payment_method' => 'CASH', 'amount_paid' => 5000],
        ];

        Sanctum::actingAs($this->cashier);

        foreach ($cases as $label => $payload) {
            $this->postJson('/api/orders', $payload)
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }

        $this->assertSame($before, $this->snapshotState());
    }

    #[Test]
    public function unknown_item_returns_not_found_without_changes(): void
    {
        $item = $this->item('SKU-404', 'Barang Ada', 5000, 10);
        $before = $this->snapshotState();

        Sanctum::actingAs($this->cashier);

        $this->postJson('/api/orders', [
            'payment_method' => 'CASH',
            'amount_paid' => 5000,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
                ['item_id' => 999999, 'quantity' => 1],
            ],
        ])->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonPath('error.message', 'Barang tidak ditemukan.')
            ->assertJsonPath('error.details.item_ids', [999999]);

        $this->assertSame($before, $this->snapshotState());
        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function cash_payment_below_total_returns_insufficient_payment(): void
    {
        $item = $this->item('SKU-PAY', 'Barang Bayar', 5000, 10);
        $before = $this->snapshotState();

        Sanctum::actingAs($this->cashier);

        $this->postJson('/api/orders', [
            'payment_method' => 'CASH',
            'amount_paid' => 4000,
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'INSUFFICIENT_PAYMENT')
            ->assertJsonPath('error.details.total_amount', 5000)
            ->assertJsonPath('error.details.amount_paid', 4000);

        $this->assertSame($before, $this->snapshotState());
    }

    #[Test]
    public function fake_prices_from_client_are_ignored(): void
    {
        $item = $this->item('SKU-HP06', 'Barang Asli', 5000, 10);

        Sanctum::actingAs($this->cashier);

        $this->postJson('/api/orders', [
            'payment_method' => 'CASH',
            'amount_paid' => 10000,
            'total_amount' => 1,
            'channel' => 'ONLINE',
            'status' => 'CANCELLED',
            'user_id' => $this->member->id,
            'items' => [[
                'item_id' => $item->id,
                'quantity' => 2,
                'price' => 1,
                'unit_price' => 1,
                'subtotal' => 1,
                'item_name' => 'Nama Palsu',
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.total_amount', 10000)
            ->assertJsonPath('data.amount_paid', 10000)
            ->assertJsonPath('data.change_amount', 0)
            ->assertJsonPath('data.channel', 'POS')
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.user.id', $this->cashier->id)
            ->assertJsonPath('data.items.0.item_name', 'Barang Asli')
            ->assertJsonPath('data.items.0.unit_price', 5000)
            ->assertJsonPath('data.items.0.subtotal', 10000);

        $this->assertStockLedgerConsistent();
    }

    #[Test]
    public function order_code_uses_the_business_timezone_date(): void
    {
        $this->travelTo(Carbon::parse('2026-01-01 20:00:00', 'UTC'));

        $item = $this->item('SKU-DATE', 'Barang Tanggal', 5000, 5);
        Sanctum::actingAs($this->cashier);

        $response = $this->postJson('/api/orders', [
            'payment_method' => 'CASH',
            'amount_paid' => 5000,
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertStringStartsWith('KOP-20260102-', $response->json('data.order_code'));
    }

    #[Test]
    public function checkout_locks_items_with_one_ordered_for_update_query(): void
    {
        $itemA = $this->item('SKU-LA', 'Barang A', 5000, 10);
        $itemB = $this->item('SKU-LB', 'Barang B', 4000, 10);

        Sanctum::actingAs($this->cashier);

        $locks = [];

        DB::listen(function ($query) use (&$locks): void {
            if (str_contains(strtolower($query->sql), 'for update')) {
                $locks[] = [
                    'sql' => strtolower($query->sql),
                    'level' => DB::transactionLevel(),
                ];
            }
        });

        $this->postJson('/api/orders', [
            'payment_method' => 'CASH',
            'amount_paid' => 9000,
            'items' => [
                ['item_id' => $itemB->id, 'quantity' => 1],
                ['item_id' => $itemA->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        $this->assertCount(1, $locks);
        $this->assertMatchesRegularExpression('/order by "id"/', $locks[0]['sql']);
        $this->assertMatchesRegularExpression('/for update/', $locks[0]['sql']);
        $this->assertGreaterThanOrEqual(1, $locks[0]['level']);
    }

    private function item(string $sku, string $name, int $price, int $stock): Item
    {
        return $this->createItemWithLedger($this->admin, $sku, $name, $price, $stock);
    }
}
