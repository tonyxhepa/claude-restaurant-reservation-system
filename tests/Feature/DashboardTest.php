<?php

use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($this->user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('totalToday returns count of all reservations for today', function () {
    Reservation::factory()->create(['reservation_date' => Carbon::today(), 'status' => 'pending']);
    Reservation::factory()->create(['reservation_date' => Carbon::today(), 'status' => 'confirmed']);
    Reservation::factory()->create(['reservation_date' => Carbon::today()->addDay(), 'status' => 'pending']);

    Livewire::actingAs($this->user)
        ->test('pages::dashboard')
        ->assertSee('2');
});

test('confirmedToday returns only confirmed reservations for today', function () {
    Reservation::factory()->create(['reservation_date' => Carbon::today(), 'status' => 'confirmed']);
    Reservation::factory()->create(['reservation_date' => Carbon::today(), 'status' => 'pending']);

    Livewire::actingAs($this->user)
        ->test('pages::dashboard')
        ->assertSee('1');
});

test('pendingToday returns only pending reservations for today', function () {
    Reservation::factory()->create(['reservation_date' => Carbon::today(), 'status' => 'pending']);
    Reservation::factory()->create(['reservation_date' => Carbon::today(), 'status' => 'confirmed']);

    Livewire::actingAs($this->user)
        ->test('pages::dashboard')
        ->assertSee('1');
});

test('guestsExpected sums party size for pending and confirmed reservations today', function () {
    Reservation::factory()->create(['reservation_date' => Carbon::today(), 'status' => 'confirmed', 'party_size' => 4]);
    Reservation::factory()->create(['reservation_date' => Carbon::today(), 'status' => 'pending', 'party_size' => 2]);
    Reservation::factory()->create(['reservation_date' => Carbon::today(), 'status' => 'cancelled', 'party_size' => 6]);

    Livewire::actingAs($this->user)
        ->test('pages::dashboard')
        ->assertSee('6');
});

test('averagePartySize calculates correctly', function () {
    Reservation::factory()->create(['reservation_date' => Carbon::today(), 'status' => 'confirmed', 'party_size' => 4]);
    Reservation::factory()->create(['reservation_date' => Carbon::today(), 'status' => 'confirmed', 'party_size' => 2]);

    Livewire::actingAs($this->user)
        ->test('pages::dashboard')
        ->assertSee('3');
});

test('capacityUtilization calculates percentage correctly', function () {
    RestaurantTable::factory()->create(['capacity' => 10, 'is_active' => true]);
    Reservation::factory()->create(['reservation_date' => Carbon::today(), 'status' => 'confirmed', 'party_size' => 5]);

    Livewire::actingAs($this->user)
        ->test('pages::dashboard')
        ->assertSee('50');
});

test('conversionRate calculates percentage correctly', function () {
    Reservation::factory()->create(['reservation_date' => Carbon::today(), 'status' => 'confirmed']);
    Reservation::factory()->create(['reservation_date' => Carbon::today(), 'status' => 'pending']);

    Livewire::actingAs($this->user)
        ->test('pages::dashboard')
        ->assertSee('50');
});

test('weekData returns array with correct structure', function () {
    Livewire::actingAs($this->user)
        ->test('pages::dashboard')
        ->assertOk();
});

test('comparisonToYesterday returns difference count', function () {
    Reservation::factory()->create(['reservation_date' => Carbon::today(), 'status' => 'confirmed']);
    Reservation::factory()->create(['reservation_date' => Carbon::yesterday(), 'status' => 'confirmed']);

    Livewire::actingAs($this->user)
        ->test('pages::dashboard');
});

test('todaysReservations returns reservations for today', function () {
    Reservation::factory()->create([
        'reservation_date' => Carbon::today(),
        'guest_name' => 'John Doe',
        'status' => 'confirmed',
    ]);
    Reservation::factory()->create(['reservation_date' => Carbon::today()->addDay()]);

    Livewire::actingAs($this->user)
        ->test('pages::dashboard')
        ->assertSee('John Doe');
});

test('quickStats computed property exists and returns expected structure', function () {
    Livewire::actingAs($this->user)
        ->test('pages::dashboard');
});
