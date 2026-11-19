<?php

namespace App\Services;

use App\Enums\OrderChannel;
use App\Repositories\ReportRepository;
use Carbon\CarbonImmutable;

class ReportService
{
    private const MAX_TOP_ITEMS = 10;

    public function __construct(private readonly ReportRepository $reports) {}

    /**
     * Build the sales report for an inclusive business-timezone date range.
     *
     * @return array{
     *     period: array{from: string, to: string, timezone: string},
     *     total_orders: int,
     *     total_revenue: int,
     *     by_channel: list<array{channel: string, total_orders: int, total_revenue: int}>,
     *     top_items: list<array{item_id: int, item_name: string, quantity_sold: int, revenue: int}>
     * }
     */
    public function sales(?string $from, ?string $to): array
    {
        $timezone = (string) config('app.business_timezone');
        $today = CarbonImmutable::now($timezone)->toDateString();

        $fromDate = $from ?? $today;
        $toDate = $to ?? $today;

        // created_at is UTC; the business day boundaries are converted to UTC
        // and the upper bound stays exclusive by adding one day to `to`.
        $start = CarbonImmutable::parse($fromDate, $timezone)->startOfDay()->utc();
        $end = CarbonImmutable::parse($toDate, $timezone)->startOfDay()->addDay()->utc();

        $totals = $this->reports->totals($start, $end);
        $channelTotals = $this->reports->totalsByChannel($start, $end);
        $topItems = $this->reports->topItems($start, $end, self::MAX_TOP_ITEMS);

        return [
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
                'timezone' => $timezone,
            ],
            'total_orders' => $totals['total_orders'],
            'total_revenue' => $totals['total_revenue'],
            'by_channel' => $this->channels($channelTotals),
            'top_items' => $topItems,
        ];
    }

    /**
     * Always return both channels, using zeros when the period has no data.
     *
     * @param  array<string, array{total_orders: int, total_revenue: int}>  $totals
     * @return list<array{channel: string, total_orders: int, total_revenue: int}>
     */
    private function channels(array $totals): array
    {
        $channels = [];

        foreach ([OrderChannel::Pos, OrderChannel::Online] as $channel) {
            $row = $totals[$channel->value] ?? null;

            $channels[] = [
                'channel' => $channel->value,
                'total_orders' => $row['total_orders'] ?? 0,
                'total_revenue' => $row['total_revenue'] ?? 0,
            ];
        }

        return $channels;
    }
}
