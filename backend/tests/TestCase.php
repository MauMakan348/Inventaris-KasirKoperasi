<?php

namespace Tests;

use App\Enums\StockMovementType;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Assert every item's stock matches its stock movement ledger.
     *
     * The ledger starts at zero, so the sum of quantity_change must equal
     * the current stock and the newest movement's stock_after must match too.
     */
    protected function assertStockLedgerConsistent(): void
    {
        $items = Item::query()
            ->withSum('stockMovements as ledger_total', 'quantity_change')
            ->get();

        foreach ($items as $item) {
            $this->assertSame(
                $item->stock,
                (int) ($item->ledger_total ?? 0),
                "Stock ledger sum does not match stock for item {$item->id}.",
            );

            $latest = $item->stockMovements()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if ($latest === null) {
                $this->assertSame(0, $item->stock, "Item {$item->id} has stock but no movement.");

                continue;
            }

            $this->assertSame(
                $item->stock,
                $latest->stock_after,
                "Latest movement stock_after does not match stock for item {$item->id}.",
            );
        }
    }

    /**
     * Create an item whose stock starts from a real opening movement.
     */
    protected function createItemWithLedger(
        User $actor,
        string $sku,
        string $name,
        int $price,
        int $stock,
    ): Item {
        return DB::transaction(function () use ($actor, $sku, $name, $price, $stock): Item {
            $item = new Item(['sku' => $sku, 'name' => $name, 'price' => $price]);
            $item->stock = $stock;
            $item->save();

            if ($stock > 0) {
                StockMovement::query()->create([
                    'item_id' => $item->id,
                    'user_id' => $actor->id,
                    'order_id' => null,
                    'type' => StockMovementType::Restock,
                    'quantity_change' => $stock,
                    'stock_before' => 0,
                    'stock_after' => $stock,
                    'note' => 'Stok awal',
                ]);
            }

            return $item;
        });
    }

    /**
     * Capture stock and row counts so a failed checkout can be compared.
     *
     * @return array{stock: array<int, int>, orders: int, order_items: int, stock_movements: int}
     */
    protected function snapshotState(): array
    {
        return [
            'stock' => Item::query()->orderBy('id')->pluck('stock', 'id')->all(),
            'orders' => Order::query()->count(),
            'order_items' => OrderItem::query()->count(),
            'stock_movements' => StockMovement::query()->count(),
        ];
    }
}
