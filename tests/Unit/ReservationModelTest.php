<?php

use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class, Tests\TestCase::class);

test('status helpers return correct booleans', function () {
    $pending = new Reservation(['status' => 'pending']);
    $confirmed = new Reservation(['status' => 'confirmed']);
    $cancelled = new Reservation(['status' => 'cancelled']);
    $completed = new Reservation(['status' => 'completed']);

    expect($pending->isPending())->toBeTrue();
    expect($confirmed->isConfirmed())->toBeTrue();
    expect($cancelled->isCancelled())->toBeTrue();
    expect($completed->isCompleted())->toBeTrue();
});

test('canBeCancelled is true for pending and confirmed', function () {
    expect((new Reservation(['status' => 'pending']))->canBeCancelled())->toBeTrue();
    expect((new Reservation(['status' => 'confirmed']))->canBeCancelled())->toBeTrue();
    expect((new Reservation(['status' => 'cancelled']))->canBeCancelled())->toBeFalse();
    expect((new Reservation(['status' => 'completed']))->canBeCancelled())->toBeFalse();
});

test('generateConfirmationCode returns 8 uppercase chars', function () {
    $code = Reservation::generateConfirmationCode();

    expect($code)->toHaveLength(8);
    expect($code)->toMatch('/^[A-Z0-9]{8}$/');
});

test('endTime returns start + 90 minutes', function () {
    $r = new Reservation(['start_time' => '18:00']);

    expect($r->endTime())->toBe('19:30');
});
