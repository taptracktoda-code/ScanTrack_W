<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SystemHealthController;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'statsJson'])->name('dashboard.stats');
    Route::post('/dashboard/shift-status', [DashboardController::class, 'updateShiftStatus'])->name('dashboard.shift_status');

    Route::middleware('admin')->group(function () {
        Route::get('/drivers', [DriverController::class, 'index'])->name('drivers');
        Route::get('/qr-management', [DriverController::class, 'qrManagement'])->name('qr.management');
        Route::post('/drivers/register', [DriverController::class, 'register'])->name('drivers.register');
        Route::post('/drivers/{driver}/resend-credentials', [DriverController::class, 'resendCredentials'])->name('drivers.resendCredentials');
        Route::get('/drivers/{driver}/qr', [DriverController::class, 'qrImage'])->name('drivers.qr');
        Route::post('/drivers/{driver}/qr/regenerate', [DriverController::class, 'regenerateQr'])->name('drivers.qr.regenerate');
        Route::post('/drivers/{driver}/qr/activate', [DriverController::class, 'activateQr'])->name('drivers.qr.activate');
        Route::post('/drivers/{driver}/qr/deactivate', [DriverController::class, 'deactivateQr'])->name('drivers.qr.deactivate');
        Route::post('/drivers', [DriverController::class, 'store'])->name('drivers.store');
        Route::put('/drivers/{driver}', [DriverController::class, 'update'])->name('drivers.update');
        Route::delete('/drivers/{driver}', [DriverController::class, 'destroy'])->name('drivers.destroy');
        Route::post('/drivers/{driver}/sms', [DriverController::class, 'sendSms'])->name('drivers.sendSms');
        Route::get('/drivers/options', [DriverController::class, 'options'])->name('drivers.options');
        Route::get('/drivers/search', [DriverController::class, 'search'])->name('drivers.search');
        Route::get('/notifications/feed', [DashboardController::class, 'notificationFeed'])->name('notifications.feed');
        Route::get('/attendance', [ReportController::class, 'attendance'])->name('attendance');
        Route::get('/terminal-activities', [ReportController::class, 'terminalActivities'])->name('terminal.activities');
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
        Route::get('/analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');
    });
    Route::get('/reports', [ReportController::class, 'index'])->name('reports');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/pending-logs', [ReportController::class, 'pendingLogs'])->name('pending.logs');
    Route::get('/vehicles', [\App\Http\Controllers\VehicleController::class, 'index'])->name('vehicles');
    Route::post('/vehicles', [\App\Http\Controllers\VehicleController::class, 'store'])->name('vehicles.store');
    Route::put('/vehicles/{vehicle}', [\App\Http\Controllers\VehicleController::class, 'update'])->name('vehicles.update');
    Route::delete('/vehicles/{vehicle}', [\App\Http\Controllers\VehicleController::class, 'destroy'])->name('vehicles.destroy');
    Route::get('/settings', [SettingController::class, 'index'])->name('settings');
    Route::get('/settings/profile', [SettingController::class, 'profile'])->name('settings.profile');
    Route::post('/settings/profile', [SettingController::class, 'updateProfile'])->name('settings.profile.update');
    Route::get('/settings/toda-organizations', [SettingController::class, 'editTodaOrganizations'])->name('settings.toda_organizations');
    Route::post('/settings/toda-organizations/organizations', [SettingController::class, 'storeTodaOrganization'])->name('settings.toda_organizations.organizations.store');
    Route::get('/system-health', [SystemHealthController::class, 'index'])->name('system-health');
    Route::get('/api/system-health', [SystemHealthController::class, 'api'])->name('system-health.api');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
