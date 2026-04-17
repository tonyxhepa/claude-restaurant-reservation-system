<?php

namespace Database\Seeders;

use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@restaurant.com',
            'is_admin' => true,
        ]);

        $indoor = collect([2, 2, 4, 6])->map(fn (int $cap, int $i) => RestaurantTable::factory()->indoor()->create([
            'table_number' => 'I'.($i + 1),
            'capacity' => $cap,
        ]));

        $outdoor = collect([4, 8])->map(fn (int $cap, int $i) => RestaurantTable::factory()->outdoor()->create([
            'table_number' => 'O'.($i + 1),
            'capacity' => $cap,
        ]));

        $bar = collect([1, 2, 2])->map(fn (int $cap, int $i) => RestaurantTable::factory()->bar()->create([
            'table_number' => 'B'.($i + 1),
            'capacity' => $cap,
        ]));

        $patio = collect([6, 10])->map(fn (int $cap, int $i) => RestaurantTable::factory()->patio()->create([
            'table_number' => 'P'.($i + 1),
            'capacity' => $cap,
        ]));

        $tables = $indoor->concat($outdoor)->concat($bar)->concat($patio);

        // 5 today
        for ($i = 0; $i < 5; $i++) {
            Reservation::factory()
                ->forToday()
                ->state(['status' => $i % 2 === 0 ? 'confirmed' : 'pending'])
                ->for($tables->random(), 'table')
                ->create();
        }

        // 10 across next 7 days
        for ($i = 0; $i < 10; $i++) {
            Reservation::factory()
                ->state([
                    'reservation_date' => Carbon::today()->addDays(random_int(1, 7)),
                    'status' => random_int(0, 1) === 0 ? 'confirmed' : 'pending',
                ])
                ->for($tables->random(), 'table')
                ->create();
        }

        // 5 past completed
        for ($i = 0; $i < 5; $i++) {
            Reservation::factory()
                ->completed()
                ->state([
                    'reservation_date' => Carbon::today()->subDays(random_int(1, 14)),
                ])
                ->for($tables->random(), 'table')
                ->create();
        }
    }
}
