<?php

use Illuminate\Support\Facades\Route;
use Tallcms\Registration\Http\Controllers\RegisterController;

/*
|--------------------------------------------------------------------------
| Registration Public Routes
|--------------------------------------------------------------------------
|
| These routes are loaded at the root level by the plugin system.
| The host adds ['web', 'throttle:60,1'] middleware and the name prefix
| plugin.tallcms.registration.* automatically.
|
*/

Route::get('/register', [RegisterController::class, 'showForm'])->name('form');
Route::post('/register/submit', [RegisterController::class, 'register'])->name('submit');
Route::post('/register/resend-verification', [RegisterController::class, 'resendVerification'])
    ->middleware('auth')
    ->name('resend-verification');
Route::get('/registered', [RegisterController::class, 'registered'])->name('success');
