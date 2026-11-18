<?php

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'type' => $this->type->value,
            'quantity_change' => $this->quantity_change,
            'stock_before' => $this->stock_before,
            'stock_after' => $this->stock_after,
            'order_id' => $this->order_id,
            'note' => $this->note,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
