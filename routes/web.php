<?php

use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\WebhookRouteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('members', MemberController::class)->except('show');
    Route::resource('routes', WebhookRouteController::class)->except('show');

    Route::get('relay-settings', [SettingController::class, 'edit'])->name('relay-settings.edit');
    Route::put('relay-settings', [SettingController::class, 'update'])->name('relay-settings.update');
});

require __DIR__.'/settings.php';
