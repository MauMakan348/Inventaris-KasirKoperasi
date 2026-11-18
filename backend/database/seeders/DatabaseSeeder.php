<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = env('SEED_DEFAULT_PASSWORD');

        if (! is_string($password) || strlen($password) < 8) {
            throw new RuntimeException('SEED_DEFAULT_PASSWORD harus berisi minimal 8 karakter.');
        }

        $users = [
            ['Admin Demo', 'admin@example.test', 'ADMIN'],
            ['Kasir Satu', 'cashier1@example.test', 'CASHIER'],
            ['Kasir Dua', 'cashier2@example.test', 'CASHIER'],
            ['Anggota Satu', 'member1@example.test', 'MEMBER'],
            ['Anggota Dua', 'member2@example.test', 'MEMBER'],
        ];

        foreach ($users as [$name, $email, $role]) {
            $user = new User;
            $user->name = $name;
            $user->email = $email;
            $user->password = $password;
            $user->role = $role;
            $user->save();
        }

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

        foreach ($items as [$sku, $name, $price, $stock]) {
            $item = new Item;
            $item->sku = $sku;
            $item->name = $name;
            $item->price = $price;
            $item->stock = $stock;
            $item->save();
        }
    }
}
