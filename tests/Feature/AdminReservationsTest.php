<?php

use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('non-admin gets 403', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get(route('admin.reservations.index'))->assertForbidden();
});

test('admin can see all reservations', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $table = RestaurantTable::factory()->create();
    $reservation = Reservation::factory()->for($table, 'table')->confirmed()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.reservations')
        ->assertSee($reservation->guest_name);
});

test('admin can search by guest name', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $table = RestaurantTable::factory()->create();

    $alice = Reservation::factory()->for($table, 'table')->confirmed()->create(['guest_name' => 'Alice']);
    $bob = Reservation::factory()->for($table, 'table')->confirmed()->create(['guest_name' => 'Bob']);

    Livewire::actingAs($admin)
        ->test('pages::admin.reservations')
        ->set('search', 'Alice')
        ->assertSee($alice->guest_email)
        ->assertDontSee($bob->guest_email);
});

test('admin can filter by status', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $table = RestaurantTable::factory()->create();

    $pending = Reservation::factory()->for($table, 'table')->pending()->create(['guest_name' => 'Pending Person']);
    $confirmed = Reservation::factory()->for($table, 'table')->confirmed()->create(['guest_name' => 'Confirmed Person']);

    Livewire::actingAs($admin)
        ->test('pages::admin.reservations')
        ->set('statusFilter', 'pending')
        ->assertSee($pending->guest_name)
        ->assertDontSee($confirmed->guest_name);
});

test('admin can confirm a pending reservation', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $table = RestaurantTable::factory()->create();
    $r = Reservation::factory()->for($table, 'table')->pending()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.reservations')
        ->call('confirm', $r->id);

    expect($r->fresh()->status)->toBe('confirmed');
});

test('admin can cancel a reservation', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $table = RestaurantTable::factory()->create();
    $r = Reservation::factory()->for($table, 'table')->confirmed()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.reservations')
        ->call('cancel', $r->id);

    expect($r->fresh()->status)->toBe('cancelled');
    expect($r->fresh()->cancelled_at)->not->toBeNull();
});

test('admin can complete a confirmed reservation', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $table = RestaurantTable::factory()->create();
    $r = Reservation::factory()->for($table, 'table')->confirmed()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.reservations')
        ->call('complete', $r->id);

    expect($r->fresh()->status)->toBe('completed');
    expect($r->fresh()->completed_at)->not->toBeNull();
});
