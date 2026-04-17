<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::livewire('book', 'pages::book')->name('reservations.book');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('reservations', 'pages::reservations')->name('reservations.index');
    Route::livewire('admin/reservations', 'pages::admin.reservations')->name('admin.reservations.index');
    Route::livewire('admin/tables', 'pages::admin.tables')->name('admin.tables.index');
});

require __DIR__.'/settings.php';
