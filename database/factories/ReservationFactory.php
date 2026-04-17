<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $times = ['11:00:00', '12:30:00', '13:00:00', '17:00:00', '18:30:00', '19:00:00', '20:00:00'];

        return [
            'user_id' => null,
            'restaurant_table_id' => RestaurantTable::factory(),
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'guest_phone' => fake()->phoneNumber(),
            'party_size' => fake()->numberBetween(1, 6),
            'reservation_date' => Carbon::today()->addDays(fake()->numberBetween(0, 14)),
            'start_time' => fake()->randomElement($times),
            'status' => 'pending',
            'special_notes' => null,
            'confirmation_code' => Str::upper(Str::random(8)),
            'cancelled_at' => null,
            'completed_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => 'confirmed']);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function forToday(): static
    {
        return $this->state(fn () => ['reservation_date' => Carbon::today()]);
    }

    public function forDate(Carbon $date): static
    {
        return $this->state(fn () => ['reservation_date' => $date]);
    }

    public function forGuest(): static
    {
        return $this->state(fn () => ['user_id' => null]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'guest_name' => $user->name,
            'guest_email' => $user->email,
        ]);
    }
}
