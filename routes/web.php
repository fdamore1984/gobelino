<?php

use App\Http\Controllers\ApnsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DeviceCommandController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\IosMdmController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

// Root: if logged in, go to dashboard, otherwise to login. Without this
// route, opening the bare domain (e.g. the Railway URL) gave a 404.
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

// Public endpoint: opened by Safari on the iOS/iPadOS device after
// scanning the QR code, not by an authenticated app user.
Route::get('/ios-mdm/profile/{token}', [IosMdmController::class, 'profile'])
    ->name('ios-mdm.profile');

// Guests: registration and login
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Page shown when the trial has expired and there's no subscription
Route::middleware('auth')->group(function () {
    Route::view('/billing/expired', 'billing.expired')->name('billing.expired');
});

// Protected area: login + active subscription/trial
Route::middleware(['auth', 'subscription.active'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    // APNs push certificate configuration (needed for iPhone/iPad)
    Route::get('/apns/configure', [ApnsController::class, 'show'])
        ->name('apns.configure');
    Route::post('/apns/request-csr', [ApnsController::class, 'requestSignedCsr'])
        ->name('apns.request-csr');
    Route::post('/apns/upload-certificate', [ApnsController::class, 'uploadCertificate'])
        ->name('apns.upload-certificate');

    // Devices
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/devices/status', [DeviceController::class, 'status'])->name('devices.status');
    Route::post('/devices/enroll', [DeviceController::class, 'createEnrollment'])->name('devices.enroll');
    Route::post('/devices/{device}/commands', [DeviceCommandController::class, 'store'])->name('devices.commands.store');

    // User profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.change-password');
    Route::delete('/profile', [ProfileController::class, 'deleteAccount'])->name('profile.delete');

    // Team management: owner and admin only
    Route::middleware('can-manage-users')->group(function () {
        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('/team', [TeamController::class, 'store'])->name('team.store');
        Route::delete('/team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');
    });
});

Route::get('/apk', function () {
    return response()->download(
        public_path('apk/app-debug.apk'),
        'gobelino-agent-debug.apk',
        ['Content-Type' => 'application/vnd.android.package-archive']
    );
})->name('agent.apk.download');
