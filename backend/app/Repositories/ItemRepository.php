<?php

namespace App\Repositories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;

class ItemRepository
{
    /**
     * Get every item ordered by name.
     *
     * @return Collection<int, Item>
     */
    public function all(): Collection
    {
        return Item::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Find an item or fail with a not found exception.
     */
    public function findOrFail(int $id): Item
    {
        return Item::query()->findOrFail($id);
    }

    /**
     * Create an item with an explicit initial stock.
     *
     * Stock is assigned directly because it is never mass assignable.
     *
     * @param  array{sku: string, name: string, price: int}  $attributes
     */
    public function create(array $attributes, int $stock): Item
    {
        $item = new Item($attributes);
        $item->stock = $stock;
        $item->save();

        return $item;
    }

    /**
     * Update the editable item attributes.
     *
     * @param  array{sku: string, name: string, price: int}  $attributes
     */
    public function update(Item $item, array $attributes): Item
    {
        $item->fill($attributes);
        $item->save();

        return $item;
    }

    /**
     * Determine whether a SKU is already taken, optionally ignoring one item.
     */
    public function skuExists(string $sku, ?int $ignoreId = null): bool
    {
        return Item::query()
            ->where('sku', $sku)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
