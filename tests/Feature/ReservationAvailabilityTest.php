<?php

use App\Models\Reservation;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('table is available when no conflicting reservations', function () {
    $table = RestaurantTable::factory()->create(['capacity' => 4]);

    expect(Reservation::isTableAvailable($table->id, Carbon::tomorrow()->toDateString(), '18:00'))->toBeTrue();
});

test('table is unavailable within 90-min buffer window', function () {
    $table = RestaurantTable::factory()->create(['capacity' => 4]);
    $date = Carbon::tomorrow()->toDateString();

    Reservation::factory()->for($table, 'table')->confirmed()->create([
        'reservation_date' => $date,
        'start_time' => '18:00',
    ]);

    expect(Reservation::isTableAvailable($table->id, $date, '18:30'))->toBeFalse();
    expect(Reservation::isTableAvailable($table->id, $date, '19:15'))->toBeFalse();
    expect(Reservation::isTableAvailable($table->id, $date, '16:45'))->toBeFalse();
});

test('table is available outside buffer window', function () {
    $table = RestaurantTable::factory()->create();
    $date = Carbon::tomorrow()->toDateString();

    Reservation::factory()->for($table, 'table')->confirmed()->create([
        'reservation_date' => $date,
        'start_time' => '18:00',
    ]);

    expect(Reservation::isTableAvailable($table->id, $date, '19:45'))->toBeTrue();
    expect(Reservation::isTableAvailable($table->id, $date, '16:15'))->toBeTrue();
});

test('cancelled reservations do not block availability', function () {
    $table = RestaurantTable::factory()->create();
    $date = Carbon::tomorrow()->toDateString();

    Reservation::factory()->for($table, 'table')->cancelled()->create([
        'reservation_date' => $date,
        'start_time' => '18:00',
    ]);

    expect(Reservation::isTableAvailable($table->id, $date, '18:30'))->toBeTrue();
});

test('completed reservations do not block availability', function () {
    $table = RestaurantTable::factory()->create();
    $date = Carbon::tomorrow()->toDateString();

    Reservation::factory()->for($table, 'table')->completed()->create([
        'reservation_date' => $date,
        'start_time' => '18:00',
    ]);

    expect(Reservation::isTableAvailable($table->id, $date, '18:30'))->toBeTrue();
});

test('availableTablesForSlot filters by party size', function () {
    RestaurantTable::factory()->create(['capacity' => 2]);
    RestaurantTable::factory()->create(['capacity' => 4]);
    RestaurantTable::factory()->create(['capacity' => 6]);

    $tables = Reservation::availableTablesForSlot(Carbon::tomorrow()->toDateString(), '18:00', 4);

    expect($tables)->toHaveCount(2);
    expect($tables->pluck('capacity')->all())->toBe([4, 6]);
});

test('availableTablesForSlot excludes inactive tables', function () {
    RestaurantTable::factory()->create(['capacity' => 4, 'is_active' => true]);
    RestaurantTable::factory()->create(['capacity' => 4, 'is_active' => false]);

    $tables = Reservation::availableTablesForSlot(Carbon::tomorrow()->toDateString(), '18:00', 2);

    expect($tables)->toHaveCount(1);
});

test('availableSlotsForDate returns 30-min slots from 11:00 to 21:30', function () {
    RestaurantTable::factory()->create(['capacity' => 4]);

    $slots = Reservation::availableSlotsForDate(Carbon::tomorrow()->toDateString(), 2);

    expect($slots->first()['time'])->toBe('11:00');
    expect($slots->last()['time'])->toBe('21:30');
    expect($slots)->toHaveCount(22);
});
