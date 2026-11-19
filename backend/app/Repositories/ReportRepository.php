<?php

namespace App\Repositories;

use App\Models\Order;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class ReportRepository
{
    /**
     * Aggregate order count and revenue for the period.
     *
     * Totals come from the orders table only so item joins cannot duplicate rows.
     *
     * @return array{total_orders: int, total_revenue: int}
     */
    public function totals(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $row = Order::query()
            ->where('status', 'COMPLETED')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->selectRaw('COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_revenue')
            ->first();

        return [
            'total_orders' => (int) $row->total_orders,
            'total_revenue' => (int) $row->total_revenue,
        ];
    }

    /**
     * Aggregate order count and revenue per channel for the period.
     *
     * @return array<string, array{total_orders: int, total_revenue: int}>
     */
    public function totalsByChannel(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return Order::query()
            ->where('status', 'COMPLETED')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->groupBy('channel')
            ->select('channel')
            ->selectRaw('COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_revenue')
            ->get()
            ->mapWithKeys(fn (Order $row): array => [
                $row->channel->value => [
                    'total_orders' => (int) $row->total_orders,
                    'total_revenue' => (int) $row->total_revenue,
                ],
            ])
            ->all();
    }

    /**
     * Get the best selling items for the period.
     *
     * @return list<array{item_id: int, item_name: string, quantity_sold: int, revenue: int}>
     */
    public function topItems(DateTimeInterface $start, DateTimeInterface $end, int $limit): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('items', 'items.id', '=', 'order_items.item_id')
            ->where('orders.status', 'COMPLETED')
            ->where('orders.created_at', '>=', $start)
            ->where('orders.created_at', '<', $end)
            ->groupBy('order_items.item_id', 'items.name')
            ->select('order_items.item_id', 'items.name as item_name')
            ->selectRaw('SUM(order_items.quantity) as quantity_sold, SUM(order_items.subtotal) as revenue')
            ->orderByDesc('quantity_sold')
            ->orderByDesc('revenue')
            ->orderBy('order_items.item_id')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'item_id' => (int) $row->item_id,
                'item_name' => (string) $row->item_name,
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }
}
