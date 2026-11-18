<?php

use App\Http\Controllers\Api\BikeController;
use App\Http\Controllers\Api\ActiveLockLocationController;
use App\Http\Controllers\Api\DamageReportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceTelemetryController;
use App\Http\Controllers\Api\MapboxController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\RideController;
use App\Http\Controllers\Api\RideLocationController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\TelemetryController;
use App\Http\Controllers\Api\LockEventController;
use App\Http\Controllers\Api\LockAccessController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::post('lock/events', [LockEventController::class, 'store'])
        ->middleware(['gateway', 'throttle:gateway-webhooks'])
        ->name('lock.events.store');

    Route::post('devices/telemetry', [DeviceTelemetryController::class, 'store'])
        ->middleware(['gateway', 'throttle:gateway-webhooks'])
        ->name('devices.telemetry.store');

    Route::post('payments/webhooks/{provider}', [PaymentWebhookController::class, 'store'])
        ->middleware(['payment.webhook', 'throttle:payment-webhooks'])
        ->name('payments.webhooks.store');

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('fyda/authorize', [AuthController::class, 'authorizeFyda'])
            ->middleware('throttle:auth')
            ->name('fyda.authorize');
        Route::get('fyda/callback', [AuthController::class, 'handleFydaCallback'])
            ->middleware('throttle:auth')
            ->name('fyda.callback');
        Route::get('fyda/result/{state}', [AuthController::class, 'fydaResult'])
            ->middleware('throttle:auth')
            ->name('fyda.result');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
            Route::delete('delete', [AuthController::class, 'deleteAccount'])->name('delete');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('locks/scan', [LockAccessController::class, 'scan'])
            ->middleware('throttle:lock-commands')
            ->name('locks.scan');
        Route::post('locks/{imei}/unlock', [LockAccessController::class, 'unlock'])
            ->middleware('throttle:lock-commands')
            ->name('locks.unlock');
        Route::get('locks/active-locations', [ActiveLockLocationController::class, 'index'])->name('locks.active-locations');

        Route::get('map/config', [MapboxController::class, 'config'])->name('map.config');
        Route::post('map/routes', [MapboxController::class, 'route'])
            ->middleware('fyda.verified')
            ->name('map.routes');

        Route::get('bikes', [BikeController::class, 'index'])->name('bikes.index');
        Route::post('bikes', [BikeController::class, 'store'])
            ->middleware('role:admin,mechanic')
            ->name('bikes.store');
        Route::get('bikes/{bike}', [BikeController::class, 'show'])->name('bikes.show');
        Route::patch('bikes/{bike}', [BikeController::class, 'update'])
            ->middleware('role:admin,mechanic')
            ->name('bikes.update');
        Route::delete('bikes/{bike}', [BikeController::class, 'destroy'])
            ->middleware('role:admin,mechanic')
            ->name('bikes.destroy');

        Route::get('stations', [StationController::class, 'index'])->name('stations.index');
        Route::post('stations', [StationController::class, 'store'])
            ->middleware('role:admin')
            ->name('stations.store');
        Route::get('stations/{station}', [StationController::class, 'show'])->name('stations.show');
        Route::patch('stations/{station}', [StationController::class, 'update'])
            ->middleware('role:admin')
            ->name('stations.update');
        Route::delete('stations/{station}', [StationController::class, 'destroy'])
            ->middleware('role:admin')
            ->name('stations.destroy');

        Route::get('rides', [RideController::class, 'index'])
            ->middleware('role:rider,admin')
            ->name('rides.index');
        Route::post('rides', [RideController::class, 'store'])
            ->middleware(['role:rider,admin', 'fyda.verified'])
            ->name('rides.store');
        Route::get('rides/{ride}', [RideController::class, 'show'])
            ->middleware('role:rider,admin')
            ->name('rides.show');
        Route::post('rides/{ride}/complete', [RideController::class, 'complete'])
            ->middleware(['role:rider,admin', 'fyda.verified'])
            ->name('rides.complete');
        Route::post('rides/{ride}/location', [RideLocationController::class, 'store'])
            ->middleware(['role:rider,admin'])
            ->name('rides.location');

        Route::post('rides/{ride}/payments', [PaymentController::class, 'store'])
            ->middleware(['role:rider,admin'])
            ->name('rides.payments.store');

        Route::get('notifications', [NotificationController::class, 'index'])
            ->middleware('role:rider,admin')
            ->name('notifications.index');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])
            ->middleware('role:rider,admin')
            ->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])
            ->middleware('role:rider,admin')
            ->name('notifications.read-all');

        Route::post('bikes/{bike}/telemetry', [TelemetryController::class, 'store'])
            ->middleware(['role:rider,admin', 'fyda.verified'])
            ->name('bikes.telemetry.store');
        Route::post('bikes/{bike}/damage-reports', [DamageReportController::class, 'store'])
            ->middleware(['role:rider,admin', 'fyda.verified'])
            ->name('bikes.damage-reports.store');

        Route::get('dashboard/summary', [DashboardController::class, 'summary'])
            ->middleware('role:admin')
            ->name('dashboard.summary');
    });
});
