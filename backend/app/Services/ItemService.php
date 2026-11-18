<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Item;
use App\Models\User;
use App\Repositories\ItemRepository;
use App\Repositories\StockMovementRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemService
{
    public function __construct(
        private readonly ItemRepository $items,
        private readonly StockMovementRepository $movements,
    ) {}

    /**
     * List every item ordered by name.
     *
     * @return Collection<int, Item>
     */
    public function list(): Collection
    {
        return $this->items->all();
    }

    /**
     * Return one item or fail with a 404.
     */
    public function show(int $id): Item
    {
        return $this->items->findOrFail($id);
    }

    /**
     * Create an item and record its initial stock as a restock movement.
     *
     * @param  array{sku: string, name: string, price: int, stock: int}  $data
     */
    public function create(array $data, User $actor): Item
    {
        $this->ensureSkuIsAvailable($data['sku']);

        return DB::transaction(function () use ($data, $actor): Item {
            $stock = (int) $data['stock'];

            $item = $this->items->create([
                'sku' => $data['sku'],
                'name' => $data['name'],
                'price' => $data['price'],
            ], $stock);

            if ($stock > 0) {
                $this->movements->create([
                    'item_id' => $item->id,
                    'user_id' => $actor->id,
                    'order_id' => null,
                    'type' => StockMovementType::Restock,
                    'quantity_change' => $stock,
                    'stock_before' => 0,
                    'stock_after' => $stock,
                    'note' => 'Stok awal',
                ]);
            }

            return $item;
        });
    }

    /**
     * Update the editable attributes; stock is never touched here.
     *
     * @param  array{sku: string, name: string, price: int}  $data
     */
    public function update(Item $item, array $data): Item
    {
        $this->ensureSkuIsAvailable($data['sku'], $item->id);

        return $this->items->update($item, [
            'sku' => $data['sku'],
            'name' => $data['name'],
            'price' => $data['price'],
        ]);
    }

    /**
     * Reject a SKU that already belongs to another item.
     */
    private function ensureSkuIsAvailable(string $sku, ?int $ignoreId = null): void
    {
        if ($this->items->skuExists($sku, $ignoreId)) {
            throw ValidationException::withMessages([
                'sku' => ['SKU sudah digunakan.'],
            ]);
        }
    }
}
