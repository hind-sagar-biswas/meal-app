<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceTokenController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', 'login')->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', 'logout')->name('logout');
        Route::get('/me', 'me')->name('me');
    });
});

Route::controller(DeviceTokenController::class)->middleware('auth:sanctum')->prefix('device-tokens')->name('device-tokens.')->group(function () {
    Route::post('/', 'store')->name('store');
    Route::delete('/', 'destroy')->name('destroy');
});
