<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\MealController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MonthController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', 'login')->name('login')->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', 'logout')->name('logout');
        Route::get('/me', 'me')->name('me');
        Route::patch('/profile', 'updateProfile')->name('profile.update')->middleware('throttle:10,1');
        Route::put('/password', 'updatePassword')->name('password.update')->middleware('throttle:5,1');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::controller(DeviceTokenController::class)->prefix('device-tokens')->name('device-tokens.')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::delete('/', 'destroy')->name('destroy');
    });

    Route::get('/members', MemberController::class)->name('members.index');
    Route::get('/audit-logs', AuditLogController::class)->name('audit-logs.index');

    Route::controller(ExpenseController::class)->prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/summary', 'summary')->name('summary');
        Route::get('/{expense}', 'show')->name('show');
        Route::post('/', 'store')->name('store')->middleware('idempotent');
        Route::post('/{expense}/adjust', 'adjust')->name('adjust')->middleware('idempotent');
    });

    Route::controller(MonthController::class)->prefix('months')->name('months.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/current', 'current')->name('current');
        Route::get('/{month}/live-summary', 'liveSummary')->name('live-summary');
        Route::get('/{month}/results', 'results')->name('results');
        Route::post('/{month}/close', 'close')->name('close')->middleware(['idempotent', 'throttle:3,1']);
        Route::post('/{month}/reopen', 'reopen')->name('reopen')->middleware(['idempotent', 'throttle:5,1']);
        Route::patch('/{month}/breakfast-price', 'updateBreakfastPrice')->name('breakfast-price')->middleware('throttle:10,1');
    });

    Route::controller(MealController::class)->prefix('meals')->name('meals.')->group(function () {
        Route::get('/my-today', 'myToday')->name('my-today');
        Route::get('/today-summary', 'todaySummary')->name('today-summary');
        Route::get('/today-members', 'todayMembers')->name('today-members');
        Route::get('/today', 'today')->name('today');
        Route::get('/my-month', 'myMonth')->name('my-month');
        Route::get('/sheet', 'sheet')->name('sheet');
        Route::get('/by-date/{date}', 'byDate')->name('by-date');
        Route::get('/{meal}/history', 'history')->name('history');

        Route::post('/{meal}/opt-in-breakfast', 'optInBreakfast')->name('opt-in-breakfast')->middleware('throttle:30,1');
        Route::post('/{meal}/opt-out-lunch', 'optOutLunch')->name('opt-out-lunch')->middleware('throttle:30,1');
        Route::post('/{meal}/opt-out-dinner', 'optOutDinner')->name('opt-out-dinner')->middleware('throttle:30,1');

        Route::patch('/{meal}', 'update')->name('update')->middleware(['idempotent', 'throttle:30,1']);
        Route::post('/day-tally', 'dayTally')->name('day-tally')->middleware(['idempotent', 'throttle:10,1']);
        Route::post('/day-off', 'dayOff')->name('day-off')->middleware(['idempotent', 'throttle:10,1']);
        Route::post('/date-range-off', 'dateRangeOff')->name('date-range-off')->middleware(['idempotent', 'throttle:5,1']);
    });

    Route::controller(NotificationController::class)->prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::patch('/{id}/read', 'markAsRead')->name('read');
        Route::post('/read-all', 'markAllAsRead')->name('read-all')->middleware('throttle:10,1');
        Route::post('/broadcast', 'broadcast')->name('broadcast')->middleware('throttle:3,1');
    });
});
