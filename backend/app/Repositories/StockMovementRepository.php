<?php

namespace App\Repositories;

use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;

class StockMovementRepository
{
    /**
     * Persist a stock movement row.
     *
     * @param  array{item_id: int, user_id: int, order_id: int|null, type: mixed, quantity_change: int, stock_before: int, stock_after: int, note: string|null}  $attributes
     */
    public function create(array $attributes): StockMovement
    {
        return StockMovement::query()->create($attributes);
    }

    /**
     * Get the movement history of one item, newest first.
     *
     * @return Collection<int, StockMovement>
     */
    public function listByItem(int $itemId): Collection
    {
        return StockMovement::query()
            ->with(['user:id,name'])
            ->where('item_id', $itemId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}
