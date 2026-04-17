<?php

use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('user only sees own reservations', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $table = RestaurantTable::factory()->create();

    $mine = Reservation::factory()->for($table, 'table')->confirmed()->create([
        'user_id' => $user->id,
        'reservation_date' => Carbon::tomorrow(),
    ]);

    Reservation::factory()->for($table, 'table')->confirmed()->create([
        'user_id' => $other->id,
        'reservation_date' => Carbon::tomorrow(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::reservations')
        ->assertSee($mine->confirmation_code);
});

test('user cannot cancel another users reservation', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $table = RestaurantTable::factory()->create();

    $theirs = Reservation::factory()->for($table, 'table')->confirmed()->create([
        'user_id' => $other->id,
    ]);

    expect(fn () => Livewire::actingAs($user)
        ->test('pages::reservations')
        ->call('cancel', $theirs->id)
    )->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect($theirs->fresh()->status)->toBe('confirmed');
});

test('user can cancel own pending or confirmed reservation', function () {
    $user = User::factory()->create();
    $table = RestaurantTable::factory()->create();

    $mine = Reservation::factory()->for($table, 'table')->confirmed()->create([
        'user_id' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::reservations')
        ->call('cancel', $mine->id);

    expect($mine->fresh()->status)->toBe('cancelled');
    expect($mine->fresh()->cancelled_at)->not->toBeNull();
});

test('user cannot cancel completed reservation', function () {
    $user = User::factory()->create();
    $table = RestaurantTable::factory()->create();

    $mine = Reservation::factory()->for($table, 'table')->completed()->create([
        'user_id' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::reservations')
        ->call('cancel', $mine->id);

    expect($mine->fresh()->status)->toBe('completed');
});

test('filter tabs narrow results', function () {
    $user = User::factory()->create();
    $table = RestaurantTable::factory()->create();

    $upcoming = Reservation::factory()->for($table, 'table')->confirmed()->create([
        'user_id' => $user->id,
        'reservation_date' => Carbon::tomorrow(),
    ]);

    $past = Reservation::factory()->for($table, 'table')->completed()->create([
        'user_id' => $user->id,
        'reservation_date' => Carbon::yesterday(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::reservations')
        ->call('setFilter', 'upcoming')
        ->assertSee($upcoming->confirmation_code)
        ->assertDontSee($past->confirmation_code)
        ->call('setFilter', 'past')
        ->assertSee($past->confirmation_code)
        ->assertDontSee($upcoming->confirmation_code)
        ->call('setFilter', 'all')
        ->assertSee($past->confirmation_code)
        ->assertSee($upcoming->confirmation_code);
});
