<?php

use App\Http\Controllers\AndroidEnterpriseController;
use App\Http\Controllers\ApnsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\IosMdmController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

// Root: se loggato vai in dashboard, altrimenti al login. Senza questa
// rotta, aprire il dominio nudo (es. l'URL di Railway) dava 404.
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

// Endpoint pubblico: lo apre Safari sul dispositivo iOS/iPadOS dopo la
// scansione del QR, non un utente autenticato dell'app.
Route::get('/ios-mdm/profile/{token}', [IosMdmController::class, 'profile'])
    ->name('ios-mdm.profile');

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

    // Configurazione certificato push APNs (necessario per gli iPhone/iPad)
    Route::get('/apns/connect', [ApnsController::class, 'create'])
        ->name('apns.connect');
    Route::post('/apns/csr', [ApnsController::class, 'generateCsr'])
        ->name('apns.csr');
    Route::post('/apns/upload', [ApnsController::class, 'upload'])
        ->name('apns.upload');

    // Dispositivi
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::post('/devices/enroll', [DeviceController::class, 'createEnrollment'])->name('devices.enroll');
    Route::post('/devices/sync', [DeviceController::class, 'sync'])->name('devices.sync');

    // Profilo utente
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.change-password');
    Route::delete('/profile', [ProfileController::class, 'deleteAccount'])->name('profile.delete');

    // Gestione team: solo owner e admin
    Route::middleware('can-manage-users')->group(function () {
        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('/team', [TeamController::class, 'store'])->name('team.store');
        Route::delete('/team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');
    });
});

    // Configurazione APNs
    Route::get('/apns/configure', [ApnsConfigController::class, 'show'])->name('apns.configure');
    Route::post('/apns/generate-csr', [ApnsConfigController::class, 'generateCsr'])->name('apns.generate-csr');
    Route::post('/apns/upload-certificate', [ApnsConfigController::class, 'uploadCertificate'])->name('apns.upload-certificate');
    Route::get('/apns/download-csr', [ApnsConfigController::class, 'downloadCsr'])->name('apns.download-csr');
