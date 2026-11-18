<?php

namespace App\Repositories;

use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository
{
    /**
     * Persist a new order.
     *
     * @param  array{order_code: string, user_id: int, channel: mixed, status: string, payment_method: mixed, total_amount: int, amount_paid: int, change_amount: int}  $attributes
     */
    public function createOrder(array $attributes): Order
    {
        return Order::query()->create($attributes);
    }

    /**
     * Persist the item snapshots of an order.
     *
     * @param  list<array{item_id: int, item_name: string, quantity: int, unit_price: int, subtotal: int}>  $rows
     */
    public function createOrderItems(Order $order, array $rows): void
    {
        $order->orderItems()->createMany($rows);
    }

    /**
     * List the orders a viewer may see, newest first.
     *
     * @return Collection<int, Order>
     */
    public function listForViewer(User $viewer): Collection
    {
        return Order::query()
            ->with(['user:id,name'])
            ->when(
                $viewer->role === Role::Member,
                fn ($query) => $query->where('user_id', $viewer->id),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Find an order the viewer may see or fail with a not found exception.
     *
     * Members are filtered by user_id so another member's order looks absent.
     */
    public function findForViewerOrFail(int $id, User $viewer): Order
    {
        return Order::query()
            ->with(['user:id,name', 'orderItems'])
            ->when(
                $viewer->role === Role::Member,
                fn ($query) => $query->where('user_id', $viewer->id),
            )
            ->findOrFail($id);
    }
}
