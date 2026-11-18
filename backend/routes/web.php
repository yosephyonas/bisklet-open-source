<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BikeController as AdminBikeController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FydaVerificationController as AdminFydaVerificationController;
use App\Http\Controllers\Admin\MaintenanceLogController as AdminMaintenanceLogController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\RideController as AdminRideController;
use App\Http\Controllers\Admin\SearchController as AdminSearchController;
use App\Http\Controllers\Admin\ServiceAreaController as AdminServiceAreaController;
use App\Http\Controllers\Admin\StationController as AdminStationController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\SystemHealthController as AdminSystemHealthController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\LockOpsController as AdminLockOpsController;
use App\Http\Controllers\Admin\LockDeviceController as AdminLockDeviceController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application.
|
*/

// Admin URL prefix (changed from /admin).
$adminPrefix = 'control';

// Public-friendly named `login` route so calls to route('login') don't crash.
// This redirects to your admin login page.
Route::get('login', function () {
    return redirect()->route('admin.login');
})->name('login');

Route::get('/language/{locale}', function (string $locale) {
    $supportedLocales = ['en', 'am'];

    if (! in_array($locale, $supportedLocales, true)) {
        $locale = config('app.fallback_locale', 'en');
    }

    session(['locale' => $locale]);

    return redirect()->back();
})->name('language.switch');

// Public landing pages
Route::view('/', 'welcome')->name('home');
Route::view('/why', 'why')->name('why');
Route::view('/vehicles', 'vehicles')->name('vehicles');
Route::get('/sitemap.xml', function () {
    return response()
        ->view('sitemap')
        ->header('Content-Type', 'application/xml');
})->name('sitemap');

Route::prefix($adminPrefix)->name('admin.')->group(function () {
    Route::middleware('guest:web')->group(function () {
        Route::get('login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('login', [AdminAuthController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth:web')->group(function () {
        Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');

        Route::middleware(['role:admin', 'admin.audit'])->group(function () {
            // dashboard (single action controller)
            Route::get('/', AdminDashboardController::class)->name('dashboard');
            Route::get('search', AdminSearchController::class)->name('search');
            Route::get('system-health', AdminSystemHealthController::class)->name('system-health');
            Route::get('audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');
            Route::get('service-area', [AdminServiceAreaController::class, 'edit'])->name('service-area.edit');
            Route::put('service-area', [AdminServiceAreaController::class, 'update'])->name('service-area.update');

            Route::prefix('locks')->name('locks.')->group(function () {
                Route::get('/', [AdminLockOpsController::class, 'index'])->name('dashboard');
                Route::get('alerts', [AdminLockOpsController::class, 'alerts'])->name('alerts');
                Route::get('telemetry', [AdminLockOpsController::class, 'telemetry'])->name('telemetry');
                Route::get('terminal', [AdminLockOpsController::class, 'listenerTerminal'])->name('terminal');
                Route::get('listener/logs', [AdminLockOpsController::class, 'listenerLogs'])->name('listener.logs');
                Route::post('listener/restart', [AdminLockOpsController::class, 'restartListenerConnections'])->name('listener.restart');

                Route::get('devices/create', [AdminLockDeviceController::class, 'create'])->name('devices.create');
                Route::post('devices', [AdminLockDeviceController::class, 'store'])->name('devices.store');
                Route::get('devices', [AdminLockDeviceController::class, 'index'])->name('devices.index');
                Route::get('devices/{device:imei}/logs', [AdminLockDeviceController::class, 'logs'])->name('devices.logs');
                Route::get('devices/{device:imei}', [AdminLockDeviceController::class, 'show'])->name('devices.show');
                Route::put('devices/{device:imei}', [AdminLockDeviceController::class, 'update'])->name('devices.update');
                Route::post('devices/{device:imei}/restart-connection', [AdminLockDeviceController::class, 'restartConnection'])->name('devices.restart-connection');
                Route::post('devices/{device:imei}/reboot', [AdminLockDeviceController::class, 'reboot'])->name('devices.reboot');
                Route::post('devices/{device:imei}/power-off', [AdminLockDeviceController::class, 'powerOff'])->name('devices.power-off');
                Route::post('devices/{device:imei}/wifi-positioning', [AdminLockDeviceController::class, 'wifiPositioning'])->name('devices.wifi-positioning');
                Route::post('devices/{device:imei}/cable-lock-info', [AdminLockDeviceController::class, 'cableLockInfo'])->name('devices.cable-lock-info');
                Route::post('devices/{device:imei}/external-device', [AdminLockDeviceController::class, 'externalDevice'])->name('devices.external-device');
                Route::post('devices/{device:imei}/beacon-validation', [AdminLockDeviceController::class, 'beaconValidation'])->name('devices.beacon-validation');
                Route::post('devices/{device:imei}/rfid-admin-card', [AdminLockDeviceController::class, 'rfidAdminCard'])->name('devices.rfid-admin-card');
                Route::post('devices/{device:imei}/ota/detect', [AdminLockDeviceController::class, 'otaDetect'])->name('devices.ota-detect');
            });

            // resources
            Route::resource('users', AdminUserController::class);
            Route::resource('stations', AdminStationController::class);
            Route::get('bikes/lock-lookup', [AdminBikeController::class, 'lockLookup'])->name('bikes.lock-lookup');
            Route::resource('bikes', AdminBikeController::class);
            Route::resource('maintenance', AdminMaintenanceLogController::class);
            Route::resource('rides', AdminRideController::class)->only(['index', 'show', 'edit', 'update']);
            Route::resource('payments', AdminPaymentController::class)->only(['index', 'show', 'edit', 'update']);
            Route::resource('fyda-verifications', AdminFydaVerificationController::class)->only(['index', 'show']);
        });
    });
});
