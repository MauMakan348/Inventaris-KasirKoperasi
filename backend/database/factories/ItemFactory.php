<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->bothify('SKU-###'),
            'name' => fake()->words(3, true),
            'price' => fake()->numberBetween(1000, 100000),
        ];
    }

    /**
     * Set stock without exposing it to public mass assignment.
     */
    public function withStock(int $stock): static
    {
        return $this->afterMaking(function (Item $item) use ($stock): void {
            $item->stock = $stock;
        });
    }

    /**
     * Set a default stock value for factory-created items.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Item $item): void {
            $item->stock ??= fake()->numberBetween(0, 100);
        });
    }
}
