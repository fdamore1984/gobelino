<?php

use App\Http\Controllers\Api\DeviceAgentController;
use Illuminate\Support\Facades\Route;

// Called by the agent APK, not by the browser: no session/CSRF.
// /enroll authenticates via the one-time enrollment token in the
// payload; /poll authenticates via the permanent device_token
// (X-Device-Token header).
Route::prefix('agent')->group(function () {
    Route::post('/enroll', [DeviceAgentController::class, 'enroll'])->name('api.agent.enroll');
    Route::post('/poll', [DeviceAgentController::class, 'poll'])->name('api.agent.poll');
});
