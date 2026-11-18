<?php

namespace App\Services;

use App\Enums\OrderChannel;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientPaymentException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ItemNotFoundException;
use App\Models\Order;
use App\Models\User;
use App\Repositories\ItemRepository;
use App\Repositories\OrderRepository;
use App\Repositories\StockMovementRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class OrderService
{
    public function __construct(
        private readonly ItemRepository $items,
        private readonly OrderRepository $orders,
        private readonly StockMovementRepository $movements,
    ) {}

    /**
     * Create an order and reduce stock atomically.
     *
     * Everything that touches the database runs inside one transaction, so a
     * failure in any item rolls back the order, the item snapshots, the stock
     * changes and the movement ledger together.
     *
     * @param  array{items: list<array{item_id: int, quantity: int}>, payment_method: string, amount_paid?: int|null}  $payload
     */
    public function checkout(User $user, array $payload): Order
    {
        $channel = $this->channelFor($user);
        $paymentMethod = PaymentMethod::from($payload['payment_method']);
        $lines = $this->mergeLines($payload['items']);
        $amountPaid = isset($payload['amount_paid']) ? (int) $payload['amount_paid'] : null;

        return DB::transaction(function () use ($user, $channel, $paymentMethod, $lines, $amountPaid): Order {
            $this->assertPaymentRules($channel, $paymentMethod, $amountPaid);

            // One query locks every row in ascending id order to avoid deadlocks.
            $lockedItems = $this->items->lockItemsForUpdate(array_keys($lines));

            $missingIds = array_values(array_diff(array_keys($lines), $lockedItems->keys()->all()));

            if ($missingIds !== []) {
                throw new ItemNotFoundException($missingIds);
            }

            $rows = [];
            $insufficient = [];
            $totalAmount = 0;

            foreach ($lines as $itemId => $quantity) {
                $item = $lockedItems->get($itemId);

                if ($item->stock < $quantity) {
                    $insufficient[] = [
                        'item_id' => $item->id,
                        'name' => $item->name,
                        'requested' => $quantity,
                        'available' => $item->stock,
                    ];

                    continue;
                }

                $subtotal = $item->price * $quantity;
                $totalAmount += $subtotal;

                $rows[] = [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'quantity' => $quantity,
                    'unit_price' => $item->price,
                    'subtotal' => $subtotal,
                ];
            }

            if ($insufficient !== []) {
                throw new InsufficientStockException($insufficient);
            }

            [$appliedPaid, $changeAmount] = $this->resolvePayment($paymentMethod, $totalAmount, $amountPaid);

            $order = $this->orders->createOrder([
                'order_code' => $this->generateOrderCode(),
                'user_id' => $user->id,
                'channel' => $channel,
                'status' => 'COMPLETED',
                'payment_method' => $paymentMethod,
                'total_amount' => $totalAmount,
                'amount_paid' => $appliedPaid,
                'change_amount' => $changeAmount,
            ]);

            $this->orders->createOrderItems($order, $rows);

            $movementType = $channel === OrderChannel::Pos
                ? StockMovementType::SalePos
                : StockMovementType::SaleOnline;

            foreach ($rows as $row) {
                $item = $lockedItems->get($row['item_id']);
                $stockBefore = $item->stock;
                $stockAfter = $stockBefore - $row['quantity'];

                $item->stock = $stockAfter;
                $item->save();

                $this->movements->create([
                    'item_id' => $item->id,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'type' => $movementType,
                    'quantity_change' => -$row['quantity'],
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'note' => null,
                ]);
            }

            return $order->load(['user:id,name', 'orderItems']);
        }, 3);
    }

    /**
     * List the orders the viewer is allowed to see.
     *
     * @return Collection<int, Order>
     */
    public function listForViewer(User $viewer): Collection
    {
        return $this->orders->listForViewer($viewer);
    }

    /**
     * Show one order the viewer is allowed to see.
     */
    public function showForViewer(int $id, User $viewer): Order
    {
        return $this->orders->findForViewerOrFail($id, $viewer);
    }

    /**
     * Resolve the channel from the role; only cashiers and members can order.
     */
    private function channelFor(User $user): OrderChannel
    {
        return match ($user->role) {
            Role::Cashier => OrderChannel::Pos,
            Role::Member => OrderChannel::Online,
            default => throw new LogicException('Role tidak berhak melakukan checkout.'),
        };
    }

    /**
     * Merge duplicate item lines and sort item ids ascending.
     *
     * @param  list<array{item_id: int, quantity: int}>  $lines
     * @return array<int, int>
     */
    private function mergeLines(array $lines): array
    {
        $merged = [];

        foreach ($lines as $line) {
            $itemId = (int) $line['item_id'];
            $merged[$itemId] = ($merged[$itemId] ?? 0) + (int) $line['quantity'];
        }

        ksort($merged);

        return $merged;
    }

    /**
     * Enforce payment rules that do not need database values.
     */
    private function assertPaymentRules(OrderChannel $channel, PaymentMethod $method, ?int $amountPaid): void
    {
        if ($channel === OrderChannel::Online && $method === PaymentMethod::Cash) {
            throw ValidationException::withMessages([
                'payment_method' => ['Pembayaran CASH tidak tersedia untuk pesanan online.'],
            ]);
        }

        if ($method === PaymentMethod::Cash && $amountPaid === null) {
            throw ValidationException::withMessages([
                'amount_paid' => ['Jumlah bayar wajib diisi untuk pembayaran tunai.'],
            ]);
        }
    }

    /**
     * Apply the payment method to the server-computed total.
     *
     * @return array{0: int, 1: int} paid amount and change
     */
    private function resolvePayment(PaymentMethod $method, int $totalAmount, ?int $amountPaid): array
    {
        if ($method === PaymentMethod::Transfer) {
            return [$totalAmount, 0];
        }

        $paid = (int) $amountPaid;

        if ($paid < $totalAmount) {
            throw new InsufficientPaymentException($totalAmount, $paid);
        }

        return [$paid, $paid - $totalAmount];
    }

    /**
     * Build the business-timezone order code: KOP-YYYYMMDD-XXXXXXXX.
     */
    private function generateOrderCode(): string
    {
        $date = now()
            ->setTimezone((string) config('app.business_timezone'))
            ->format('Ymd');

        return 'KOP-'.$date.'-'.Str::upper(Str::random(8));
    }
}
