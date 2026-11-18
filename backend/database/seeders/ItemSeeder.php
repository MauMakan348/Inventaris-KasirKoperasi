<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemSeeder extends Seeder
{
    /**
     * Seed the demo items and their opening stock ledger.
     */
    public function run(): void
    {
        $admin = User::query()
            ->where('role', Role::Admin)
            ->orderBy('id')
            ->firstOrFail();

        $items = [
            ['SKU-001', 'Buku Tulis', 5000, 40],
            ['SKU-002', 'Pulpen Hitam', 3000, 60],
            ['SKU-003', 'Pensil 2B', 2500, 50],
            ['SKU-004', 'Penghapus Putih', 2000, 35],
            ['SKU-005', 'Penggaris 30cm', 7000, 25],
            ['SKU-006', 'Map Plastik', 4500, 30],
            ['SKU-007', 'Kertas A4', 55000, 15],
            ['SKU-008', 'Spidol Permanen', 8500, 20],
            ['SKU-009', 'Stapler Mini', 18000, 12],
            ['SKU-010', 'Isi Stapler', 6000, 28],
        ];

        DB::transaction(function () use ($items, $admin): void {
            foreach ($items as [$sku, $name, $price, $stock]) {
                $item = new Item;
                $item->sku = $sku;
                $item->name = $name;
                $item->price = $price;
                $item->stock = $stock;
                $item->save();

                $movement = new StockMovement([
                    'item_id' => $item->id,
                    'user_id' => $admin->id,
                    'order_id' => null,
                    'type' => StockMovementType::Restock,
                    'quantity_change' => $stock,
                    'stock_before' => 0,
                    'stock_after' => $stock,
                    'note' => 'Stok awal',
                ]);
                $movement->created_at = now();
                $movement->save();
            }
        });
    }
}
