<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', 'login')->name('login')->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', 'logout')->name('logout');
        Route::get('/me', 'me')->name('me');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::controller(DeviceTokenController::class)->prefix('device-tokens')->name('device-tokens.')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::delete('/', 'destroy')->name('destroy');
    });

    Route::get('/members', MemberController::class)->name('members.index');
    Route::get('/audit-logs', AuditLogController::class)->name('audit-logs.index');

    Route::controller(NotificationController::class)->prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::patch('/{id}/read', 'markAsRead')->name('read');
        Route::post('/read-all', 'markAllAsRead')->name('read-all')->middleware('throttle:read-all');
        Route::post('/broadcast', 'broadcast')->name('broadcast')->middleware('throttle:broadcast');
    });
});
