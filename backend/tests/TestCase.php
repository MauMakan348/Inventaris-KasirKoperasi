<?php

namespace Tests;

use App\Models\Item;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
}
