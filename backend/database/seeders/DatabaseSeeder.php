<?php

namespace Database\Seeders;

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
            throw new \RuntimeException('SEED_DEFAULT_PASSWORD harus berisi minimal 8 karakter.');
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

        $this->call(ItemSeeder::class);
    }
}
