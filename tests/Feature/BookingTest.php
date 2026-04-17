<?php

use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    RestaurantTable::factory()->indoor()->create(['capacity' => 4]);
});

test('booking page renders the luxury reservation experience copy', function () {
    Livewire::test('pages::book')
        ->assertSee('Reserve your table for an evening worth dressing up for.')
        ->assertSee('Reservation progress')
        ->assertSee('Begin your reservation');
});

test('guest can book a table through the wizard', function () {
    $date = Carbon::tomorrow()->toDateString();

    Livewire::test('pages::book')
        ->set('reservation_date', $date)
        ->set('party_size', 2)
        ->call('searchSlots')
        ->assertSet('step', 2)
        ->call('selectSlot', '18:00')
        ->assertSet('step', 3)
        ->set('guest_name', 'Jane Guest')
        ->set('guest_email', 'jane@example.com')
        ->set('guest_phone', '555-0000')
        ->call('confirmBooking')
        ->assertSet('step', 4);

    $this->assertDatabaseHas('reservations', [
        'guest_name' => 'Jane Guest',
        'guest_email' => 'jane@example.com',
        'party_size' => 2,
        'status' => 'confirmed',
        'user_id' => null,
    ]);
});

test('authenticated user booking stores user_id', function () {
    $user = User::factory()->create();
    $date = Carbon::tomorrow()->toDateString();

    Livewire::actingAs($user)
        ->test('pages::book')
        ->set('reservation_date', $date)
        ->set('party_size', 2)
        ->call('searchSlots')
        ->call('selectSlot', '18:00')
        ->call('confirmBooking');

    $this->assertDatabaseHas('reservations', [
        'user_id' => $user->id,
        'guest_email' => $user->email,
    ]);
});

test('authenticated user has guest fields pre-filled', function () {
    $user = User::factory()->create(['name' => 'John Doe', 'email' => 'john@ex.com']);

    Livewire::actingAs($user)
        ->test('pages::book')
        ->assertSet('guest_name', 'John Doe')
        ->assertSet('guest_email', 'john@ex.com');
});

test('confirmation code is generated on booking', function () {
    $date = Carbon::tomorrow()->toDateString();

    $component = Livewire::test('pages::book')
        ->set('reservation_date', $date)
        ->set('party_size', 2)
        ->call('searchSlots')
        ->call('selectSlot', '18:00')
        ->set('guest_name', 'A')
        ->set('guest_email', 'a@a.com')
        ->call('confirmBooking');

    $code = $component->get('confirmation_code');
    expect($code)->toBeString()->toHaveLength(8);
    $this->assertDatabaseHas('reservations', ['confirmation_code' => $code]);
});

test('race-condition re-check sends user back to slot selection', function () {
    $table = RestaurantTable::first();
    $date = Carbon::tomorrow()->toDateString();

    $component = Livewire::test('pages::book')
        ->set('reservation_date', $date)
        ->set('party_size', 2)
        ->call('searchSlots')
        ->call('selectSlot', '18:00')
        ->set('guest_name', 'Race')
        ->set('guest_email', 'race@a.com');

    Reservation::factory()->for($table, 'table')->confirmed()->create([
        'reservation_date' => $date,
        'start_time' => '18:00',
    ]);

    $component->call('confirmBooking')->assertSet('step', 2);
});

test('cannot book with invalid party size', function () {
    Livewire::test('pages::book')
        ->set('party_size', 0)
        ->call('searchSlots')
        ->assertHasErrors('party_size');
});

test('cannot book a past date', function () {
    Livewire::test('pages::book')
        ->set('reservation_date', Carbon::yesterday()->toDateString())
        ->set('party_size', 2)
        ->call('searchSlots')
        ->assertHasErrors('reservation_date');
});

test('back from step 2 returns to step 1', function () {
    $date = Carbon::tomorrow()->toDateString();

    Livewire::test('pages::book')
        ->set('reservation_date', $date)
        ->set('party_size', 2)
        ->call('searchSlots')
        ->assertSet('step', 2)
        ->call('back')
        ->assertSet('step', 1);
});

test('back from step 3 returns to step 2 and clears selected_time', function () {
    $date = Carbon::tomorrow()->toDateString();

    Livewire::test('pages::book')
        ->set('reservation_date', $date)
        ->set('party_size', 2)
        ->call('searchSlots')
        ->call('selectSlot', '18:00')
        ->assertSet('step', 3)
        ->assertSet('selected_time', '18:00')
        ->call('back')
        ->assertSet('step', 2)
        ->assertSet('selected_time', '');
});

test('back clears validation errors from the previous step', function () {
    $date = Carbon::tomorrow()->toDateString();

    Livewire::test('pages::book')
        ->set('reservation_date', $date)
        ->set('party_size', 2)
        ->call('searchSlots')
        ->call('selectSlot', '18:00')
        ->set('guest_name', '')
        ->set('guest_email', '')
        ->call('confirmBooking')
        ->assertHasErrors(['guest_name', 'guest_email'])
        ->call('back')
        ->assertHasNoErrors();
});

test('back on step 1 does nothing', function () {
    Livewire::test('pages::book')
        ->assertSet('step', 1)
        ->call('back')
        ->assertSet('step', 1);
});
