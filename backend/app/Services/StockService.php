<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Repositories\ItemRepository;
use App\Repositories\StockMovementRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function __construct(
        private readonly ItemRepository $items,
        private readonly StockMovementRepository $movements,
    ) {}

    /**
     * Add stock to an item and record the movement.
     *
     * @return array{item: Item, movement: StockMovement}
     */
    public function restock(int $itemId, int $quantity, ?string $note, User $actor): array
    {
        return DB::transaction(function () use ($itemId, $quantity, $note, $actor): array {
            $item = $this->lockItem($itemId);
            $stockBefore = $item->stock;
            $stockAfter = $stockBefore + $quantity;

            $item->stock = $stockAfter;
            $item->save();

            $movement = $this->movements->create([
                'item_id' => $item->id,
                'user_id' => $actor->id,
                'order_id' => null,
                'type' => StockMovementType::Restock,
                'quantity_change' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'note' => $note,
            ]);

            return ['item' => $item, 'movement' => $movement];
        }, 3);
    }

    /**
     * Set stock to the counted physical amount and record the difference.
     *
     * A zero difference changes nothing and writes no movement because the
     * ledger constraint forbids a zero quantity_change.
     *
     * @return array{item: Item, movement: StockMovement|null}
     */
    public function opname(int $itemId, int $actualStock, ?string $note, User $actor): array
    {
        return DB::transaction(function () use ($itemId, $actualStock, $note, $actor): array {
            $item = $this->lockItem($itemId);
            $stockBefore = $item->stock;
            $delta = $actualStock - $stockBefore;

            if ($delta === 0) {
                return ['item' => $item, 'movement' => null];
            }

            $item->stock = $actualStock;
            $item->save();

            $movement = $this->movements->create([
                'item_id' => $item->id,
                'user_id' => $actor->id,
                'order_id' => null,
                'type' => StockMovementType::Opname,
                'quantity_change' => $delta,
                'stock_before' => $stockBefore,
                'stock_after' => $actualStock,
                'note' => $note,
            ]);

            return ['item' => $item, 'movement' => $movement];
        }, 3);
    }

    /**
     * Get the movement history of an existing item.
     *
     * @return Collection<int, StockMovement>
     */
    public function movements(int $itemId): Collection
    {
        $this->items->findOrFail($itemId);

        return $this->movements->listByItem($itemId);
    }

    /**
     * Lock one item inside the current transaction or fail with a 404.
     */
    private function lockItem(int $itemId): Item
    {
        $item = $this->items->lockItemsForUpdate([$itemId])->get($itemId);

        if ($item === null) {
            throw (new ModelNotFoundException)->setModel(Item::class, [$itemId]);
        }

        return $item;
    }
}
