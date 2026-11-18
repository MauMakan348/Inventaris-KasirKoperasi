<?php

namespace App\Repositories;

use App\Models\StockMovement;

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
}
