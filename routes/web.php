<?php

use App\Http\Controllers\AndroidEnterpriseController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

// Ospiti: registrazione e login
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Pagina mostrata quando il trial è scaduto e non c'è abbonamento
Route::middleware('auth')->group(function () {
    Route::view('/billing/expired', 'billing.expired')->name('billing.expired');
});

// Area riservata: login + abbonamento/trial attivo
Route::middleware(['auth', 'subscription.active'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    // Collegamento Android Enterprise
    Route::get('/android-enterprise/connect', [AndroidEnterpriseController::class, 'create'])
        ->name('android-enterprise.connect');
    Route::get('/android-enterprise/callback', [AndroidEnterpriseController::class, 'callback'])
        ->name('android-enterprise.callback');

    // Dispositivi
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::post('/devices/enroll', [DeviceController::class, 'createEnrollment'])->name('devices.enroll');
    Route::post('/devices/sync', [DeviceController::class, 'sync'])->name('devices.sync');

    // Gestione team: solo owner e admin
    Route::middleware('can-manage-users')->group(function () {
        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('/team', [TeamController::class, 'store'])->name('team.store');
        Route::delete('/team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');
    });
});
