<?php

namespace Database\Factories;

use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantTable>
 */
class RestaurantTableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'table_number' => 'T'.fake()->unique()->numberBetween(100, 9999),
            'capacity' => fake()->randomElement([2, 2, 4, 4, 6, 8]),
            'section' => fake()->randomElement(RestaurantTable::sections()),
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function indoor(): static
    {
        return $this->state(fn () => ['section' => 'indoor']);
    }

    public function outdoor(): static
    {
        return $this->state(fn () => ['section' => 'outdoor']);
    }

    public function bar(): static
    {
        return $this->state(fn () => ['section' => 'bar']);
    }

    public function patio(): static
    {
        return $this->state(fn () => ['section' => 'patio']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
