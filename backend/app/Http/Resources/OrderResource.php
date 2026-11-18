<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
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
            'order_code' => $this->order_code,
            'channel' => $this->channel->value,
            'status' => $this->status,
            'payment_method' => $this->payment_method->value,
            'total_amount' => $this->total_amount,
            'amount_paid' => $this->amount_paid,
            'change_amount' => $this->change_amount,
            'created_at' => $this->created_at?->toISOString(),
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ],
            'items' => $this->whenLoaded('orderItems', fn () => $this->orderItems
                ->map(fn (OrderItem $item): array => [
                    'id' => $item->id,
                    'item_id' => $item->item_id,
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ])
                ->values()
                ->all()),
        ];
    }
}
