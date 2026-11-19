<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Item;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportTest extends TestCase
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
    public function empty_period_returns_zeroes_and_both_channels(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/reports/sales?from=2026-11-01&to=2026-11-30')
            ->assertOk()
            ->assertJsonPath('data.period', [
                'from' => '2026-11-01',
                'to' => '2026-11-30',
                'timezone' => config('app.business_timezone'),
            ])
            ->assertJsonPath('data.total_orders', 0)
            ->assertJsonPath('data.total_revenue', 0)
            ->assertJsonPath('data.by_channel', [
                ['channel' => 'POS', 'total_orders' => 0, 'total_revenue' => 0],
                ['channel' => 'ONLINE', 'total_orders' => 0, 'total_revenue' => 0],
            ])
            ->assertJsonPath('data.top_items', []);
    }

    #[Test]
    public function period_aggregates_orders_channels_and_top_items(): void
    {
        $itemA = $this->item('SKU-RP1', 'Buku Tulis', 5000, 100);
        $itemB = $this->item('SKU-RP2', 'Pulpen', 3000, 100);

        $this->checkout($this->member, [
            ['item_id' => $itemA->id, 'quantity' => 2],
            ['item_id' => $itemB->id, 'quantity' => 1],
        ]);

        $this->checkout($this->cashier, [
            ['item_id' => $itemA->id, 'quantity' => 1],
        ], 'CASH', 5000);

        $before = $this->snapshotState();
        $today = $this->businessToday();

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/reports/sales?from='.$today.'&to='.$today);

        $response->assertOk()
            ->assertJsonPath('data.total_orders', 2)
            ->assertJsonPath('data.total_revenue', 18000)
            ->assertJsonPath('data.by_channel', [
                ['channel' => 'POS', 'total_orders' => 1, 'total_revenue' => 5000],
                ['channel' => 'ONLINE', 'total_orders' => 1, 'total_revenue' => 13000],
            ])
            ->assertJsonCount(2, 'data.top_items')
            ->assertJsonPath('data.top_items.0.item_id', $itemA->id)
            ->assertJsonPath('data.top_items.0.item_name', 'Buku Tulis')
            ->assertJsonPath('data.top_items.0.quantity_sold', 3)
            ->assertJsonPath('data.top_items.0.revenue', 15000)
            ->assertJsonPath('data.top_items.1.item_id', $itemB->id)
            ->assertJsonPath('data.top_items.1.quantity_sold', 1)
            ->assertJsonPath('data.top_items.1.revenue', 3000);

        $this->assertSame($before, $this->snapshotState());
    }

    #[Test]
    public function totals_match_the_channel_breakdown(): void
    {
        $item = $this->item('SKU-RP3', 'Barang Konsisten', 4000, 100);

        $this->checkout($this->cashier, [['item_id' => $item->id, 'quantity' => 1]], 'CASH', 4000);
        $this->checkout($this->cashier, [['item_id' => $item->id, 'quantity' => 2]], 'CASH', 8000);
        $this->checkout($this->member, [['item_id' => $item->id, 'quantity' => 3]]);

        $today = $this->businessToday();

        Sanctum::actingAs($this->admin);

        $data = $this->getJson('/api/reports/sales?from='.$today.'&to='.$today)
            ->assertOk()
            ->json('data');

        $this->assertSame(
            $data['total_orders'],
            array_sum(array_column($data['by_channel'], 'total_orders')),
        );
        $this->assertSame(
            $data['total_revenue'],
            array_sum(array_column($data['by_channel'], 'total_revenue')),
        );
        $this->assertSame(3, $data['total_orders']);
        $this->assertSame(24000, $data['total_revenue']);
    }

    #[Test]
    public function business_timezone_boundaries_are_exclusive_and_inclusive(): void
    {
        $item = $this->item('SKU-RP4', 'Barang Waktu', 5000, 100);

        $beforeMidnight = $this->checkout($this->cashier, [['item_id' => $item->id, 'quantity' => 1]], 'CASH', 5000);
        $afterMidnight = $this->checkout($this->cashier, [['item_id' => $item->id, 'quantity' => 2]], 'CASH', 10000);

        DB::table('orders')->where('id', $beforeMidnight['id'])->update(['created_at' => '2026-11-19 15:59:59']);
        DB::table('orders')->where('id', $afterMidnight['id'])->update(['created_at' => '2026-11-19 16:00:00']);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/reports/sales?from=2026-11-19&to=2026-11-19')
            ->assertOk()
            ->assertJsonPath('data.total_orders', 1)
            ->assertJsonPath('data.total_revenue', 5000);

        $this->getJson('/api/reports/sales?from=2026-11-20&to=2026-11-20')
            ->assertOk()
            ->assertJsonPath('data.total_orders', 1)
            ->assertJsonPath('data.total_revenue', 10000);
    }

    #[Test]
    public function the_end_date_is_inclusive_for_multi_day_ranges(): void
    {
        $item = $this->item('SKU-RP5', 'Barang Rentang', 5000, 100);

        $first = $this->checkout($this->cashier, [['item_id' => $item->id, 'quantity' => 1]], 'CASH', 5000);
        $last = $this->checkout($this->cashier, [['item_id' => $item->id, 'quantity' => 1]], 'CASH', 5000);
        $outside = $this->checkout($this->cashier, [['item_id' => $item->id, 'quantity' => 1]], 'CASH', 5000);

        DB::table('orders')->where('id', $first['id'])->update(['created_at' => '2026-11-17 12:00:00']);
        DB::table('orders')->where('id', $last['id'])->update(['created_at' => '2026-11-19 12:00:00']);
        DB::table('orders')->where('id', $outside['id'])->update(['created_at' => '2026-11-20 12:00:00']);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/reports/sales?from=2026-11-17&to=2026-11-19')
            ->assertOk()
            ->assertJsonPath('data.total_orders', 2)
            ->assertJsonPath('data.total_revenue', 10000);
    }

    #[Test]
    public function top_items_are_ranked_and_limited_to_ten(): void
    {
        $items = [];

        $items[] = $this->item('SKU-T1', 'Barang T1', 1000, 100);
        $items[] = $this->item('SKU-T2', 'Barang T2', 2000, 100);
        $items[] = $this->item('SKU-T3', 'Barang T3', 1000, 100);
        $items[] = $this->item('SKU-T4', 'Barang T4', 1000, 100);

        for ($number = 5; $number <= 12; $number++) {
            $items[] = $this->item('SKU-T'.$number, 'Barang T'.$number, 1000, 100);
        }

        $quantities = [10, 5, 5, 5, 1, 1, 1, 1, 1, 1, 1, 1];

        $lines = [];
        $total = 0;

        foreach ($items as $index => $item) {
            $quantity = $quantities[$index];
            $lines[] = ['item_id' => $item->id, 'quantity' => $quantity];
            $total += $item->price * $quantity;
        }

        $this->checkout($this->cashier, $lines, 'CASH', $total);

        $today = $this->businessToday();

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/reports/sales?from='.$today.'&to='.$today)->assertOk();

        $response->assertJsonCount(10, 'data.top_items');

        $ids = array_column($response->json('data.top_items'), 'item_id');
        $expected = array_map(fn (Item $item): int => $item->id, array_slice($items, 0, 10));

        $this->assertSame($expected, $ids);
        $this->assertSame(10, $response->json('data.top_items.0.quantity_sold'));
        $this->assertSame(5, $response->json('data.top_items.1.quantity_sold'));
        $this->assertSame(10000, $response->json('data.top_items.1.revenue'));
        $this->assertSame(5, $response->json('data.top_items.2.quantity_sold'));
        $this->assertSame(5000, $response->json('data.top_items.2.revenue'));
    }

    #[Test]
    public function missing_parameters_default_to_today_in_the_business_timezone(): void
    {
        $this->travelTo(Carbon::parse('2026-11-19 17:00:00', 'UTC'));

        $item = $this->item('SKU-RP6', 'Barang Default', 5000, 10);
        $this->checkout($this->cashier, [['item_id' => $item->id, 'quantity' => 1]], 'CASH', 5000);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/reports/sales')
            ->assertOk()
            ->assertJsonPath('data.period.from', '2026-11-20')
            ->assertJsonPath('data.period.to', '2026-11-20')
            ->assertJsonPath('data.total_orders', 1);

        $this->travelTo(Carbon::parse('2026-11-19 15:00:00', 'UTC'));

        $this->checkout($this->cashier, [['item_id' => $item->id, 'quantity' => 1]], 'CASH', 5000);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/reports/sales')
            ->assertOk()
            ->assertJsonPath('data.period.from', '2026-11-19')
            ->assertJsonPath('data.period.to', '2026-11-19')
            ->assertJsonPath('data.total_orders', 1);
    }

    #[Test]
    public function invalid_periods_are_rejected_and_change_nothing(): void
    {
        $before = $this->snapshotState();

        Sanctum::actingAs($this->admin);

        $cases = [
            'only from' => '?from=2026-11-01',
            'only to' => '?to=2026-11-30',
            'inverted' => '?from=2026-11-30&to=2026-11-01',
            'bad format' => '?from=2026-11-1&to=2026-11-30',
            'impossible date' => '?from=2026-02-01&to=2026-02-30',
            'too long' => '?from=2026-01-01&to=2027-01-03',
        ];

        foreach ($cases as $label => $query) {
            $this->getJson('/api/reports/sales'.$query)
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }

        $this->getJson('/api/reports/sales?from=2026-01-01&to=2027-01-02')
            ->assertOk();

        $this->assertSame($before, $this->snapshotState());
    }

    #[Test]
    public function report_is_admin_only(): void
    {
        $this->getJson('/api/reports/sales')->assertUnauthorized();

        foreach ([Role::Cashier, Role::Member] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->getJson('/api/reports/sales?from=2026-11-01&to=2026-11-30')
                ->assertForbidden()
                ->assertJsonPath('error.code', 'FORBIDDEN');
        }
    }

    #[Test]
    public function report_numbers_are_json_integers(): void
    {
        $item = $this->item('SKU-RP7', 'Barang Integer', 5000, 10);
        $this->checkout($this->cashier, [['item_id' => $item->id, 'quantity' => 2]], 'CASH', 10000);

        $today = $this->businessToday();

        Sanctum::actingAs($this->admin);

        $data = $this->getJson('/api/reports/sales?from='.$today.'&to='.$today)
            ->assertOk()
            ->json('data');

        $this->assertIsInt($data['total_orders']);
        $this->assertIsInt($data['total_revenue']);

        foreach ($data['by_channel'] as $channel) {
            $this->assertIsInt($channel['total_orders']);
            $this->assertIsInt($channel['total_revenue']);
        }

        foreach ($data['top_items'] as $topItem) {
            $this->assertIsInt($topItem['item_id']);
            $this->assertIsString($topItem['item_name']);
            $this->assertIsInt($topItem['quantity_sold']);
            $this->assertIsInt($topItem['revenue']);
        }
    }

    /**
     * @param  list<array{item_id: int, quantity: int}>  $items
     * @return array<string, mixed>
     */
    private function checkout(User $user, array $items, string $method = 'TRANSFER', ?int $amountPaid = null): array
    {
        Sanctum::actingAs($user);

        $payload = ['payment_method' => $method, 'items' => $items];

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

    private function businessToday(): string
    {
        return CarbonImmutable::now((string) config('app.business_timezone'))->toDateString();
    }
}
