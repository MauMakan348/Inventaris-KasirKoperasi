<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $stockBefore = fake()->numberBetween(0, 100);
        $quantityChange = fake()->numberBetween(-10, 20);

        return [
            'item_id' => Item::factory(),
            'user_id' => User::factory(),
            'order_id' => null,
            'type' => $quantityChange > 0 ? StockMovementType::Restock : StockMovementType::SalePos,
            'quantity_change' => $quantityChange,
            'stock_before' => $stockBefore,
            'stock_after' => max(0, $stockBefore + $quantityChange),
            'note' => null,
        ];
    }
}
