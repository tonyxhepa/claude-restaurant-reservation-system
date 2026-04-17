<?php

use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin can view tables page', function () {
    $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);

    $this->actingAs($admin)->get(route('admin.tables.index'))->assertOk();
});

test('admin tables page renders the floor overview shell', function () {
    $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.tables.index'))
        ->assertOk()
        ->assertSee('Dining Room')
        ->assertSee('Floor Overview')
        ->assertSee('Table Registry');
});

test('non-admin cannot view tables page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get(route('admin.tables.index'))->assertForbidden();
});

test('guest is redirected from tables page', function () {
    $this->get(route('admin.tables.index'))->assertRedirect(route('login'));
});

test('admin can create a table', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test('pages::admin.tables')
        ->set('table_number', 'X1')
        ->set('capacity', 4)
        ->set('section', 'indoor')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('restaurant_tables', [
        'table_number' => 'X1',
        'capacity' => 4,
        'section' => 'indoor',
    ]);
});

test('admin can edit a table', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $table = RestaurantTable::factory()->create(['table_number' => 'OLD']);

    Livewire::actingAs($admin)
        ->test('pages::admin.tables')
        ->call('openEdit', $table->id)
        ->set('table_number', 'NEW')
        ->call('save')
        ->assertHasNoErrors();

    expect($table->fresh()->table_number)->toBe('NEW');
});

test('table_number must be unique', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    RestaurantTable::factory()->create(['table_number' => 'DUPE']);

    Livewire::actingAs($admin)
        ->test('pages::admin.tables')
        ->set('table_number', 'DUPE')
        ->set('capacity', 4)
        ->set('section', 'indoor')
        ->call('save')
        ->assertHasErrors('table_number');
});

test('admin can toggle table active status', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $table = RestaurantTable::factory()->create(['is_active' => true]);

    Livewire::actingAs($admin)
        ->test('pages::admin.tables')
        ->call('toggleActive', $table->id);

    expect($table->fresh()->is_active)->toBeFalse();
});
