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
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\ManagerDashboardController;
use App\Http\Controllers\ManagerPortalController;
use App\Http\Controllers\ManagerSessionController;
use App\Http\Controllers\DriverVerificationController;
use App\Http\Controllers\QueueMonitoringController;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

Route::get('/manager/login', [ManagerSessionController::class, 'showLogin'])->name('manager.login');
Route::post('/manager/login', [ManagerSessionController::class, 'login'])->middleware('throttle:5,1')->name('manager.login.store');
Route::middleware(['auth', 'manager'])->prefix('manager')->name('manager.')->group(function () {
    Route::post('/logout', [ManagerSessionController::class, 'logout'])->name('logout');
    Route::get('/password/change', [ManagerSessionController::class, 'showPasswordChange'])->name('password.change');
    Route::post('/password/change', [ManagerSessionController::class, 'updatePassword'])->name('password.update');
    Route::middleware('manager.password.changed')->group(function () {
        Route::get('/dashboard', [ManagerDashboardController::class, 'index'])->name('dashboard');
        Route::get('/drivers', [ManagerPortalController::class, 'drivers'])->name('drivers');
        Route::post('/drivers', [ManagerPortalController::class, 'storeDriver'])->name('drivers.store');
        Route::put('/drivers/{driver}', [ManagerPortalController::class, 'updateDriver'])->name('drivers.update');
        Route::get('/vehicles', [ManagerPortalController::class, 'vehicles'])->name('vehicles');
        Route::post('/vehicles', [ManagerPortalController::class, 'storeVehicle'])->name('vehicles.store');
        Route::put('/vehicles/{vehicle}', [ManagerPortalController::class, 'updateVehicle'])->name('vehicles.update');
        Route::delete('/vehicles/{vehicle}', [ManagerPortalController::class, 'destroyVehicle'])->name('vehicles.destroy');
        Route::get('/qr-management', [ManagerPortalController::class, 'qrManagement'])->name('qr.management');
        Route::post('/drivers/{driver}/qr/regenerate', [ManagerPortalController::class, 'regenerateQr'])->name('drivers.qr.regenerate');
        Route::post('/drivers/{driver}/qr/{status}', [ManagerPortalController::class, 'setQrStatus'])->whereIn('status', ['activate', 'deactivate'])->name('drivers.qr.status');
        Route::get('/attendance', [ManagerPortalController::class, 'attendance'])->name('attendance');
        Route::get('/reports', [ManagerPortalController::class, 'reports'])->name('reports');
        Route::get('/reports/export', [ManagerPortalController::class, 'exportReports'])->name('reports.export');
        Route::get('/organization', [ManagerPortalController::class, 'organization'])->name('organization');
        Route::get('/profile', [ManagerPortalController::class, 'profile'])->name('profile');
        Route::put('/profile', [ManagerPortalController::class, 'updateProfile'])->name('profile.update');
        Route::get('/notifications/feed', [DashboardController::class, 'notificationFeed'])->name('notifications.feed');
        Route::post('/schedules', [ManagerDashboardController::class, 'store'])->name('schedules.store');
        Route::put('/schedules/{event}', [ManagerDashboardController::class, 'update'])->name('schedules.update');
        Route::delete('/schedules/{event}', [ManagerDashboardController::class, 'destroy'])->name('schedules.destroy');
    });
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'statsJson'])->name('dashboard.stats');

    Route::middleware('admin')->group(function () {
        Route::get('/drivers/verification', [DriverVerificationController::class, 'index'])->name('drivers.verification');
        Route::post('/drivers/{driver}/assign', [DriverVerificationController::class, 'assign'])->name('drivers.assign');
        Route::post('/drivers/{driver}/approve', [DriverVerificationController::class, 'approve'])->name('drivers.approve');
        Route::post('/drivers/{driver}/reject', [DriverVerificationController::class, 'reject'])->name('drivers.reject');
        Route::get('/managers', [ManagerController::class, 'index'])->name('managers');
        Route::get('/queues', [QueueMonitoringController::class, 'index'])->name('queues.monitoring');
        Route::post('/managers', [ManagerController::class, 'store'])->name('managers.store');
        Route::put('/managers/{manager}', [ManagerController::class, 'update'])->name('managers.update');
        Route::post('/managers/{manager}/status', [ManagerController::class, 'toggleStatus'])->name('managers.status');
        Route::post('/managers/{manager}/password/reset', [ManagerController::class, 'resetPassword'])->name('managers.password.reset');
        Route::get('/drivers', [DriverController::class, 'index'])->name('drivers');
        Route::get('/qr-management', [DriverController::class, 'qrManagement'])->name('qr.management');
        Route::post('/drivers/register', [DriverController::class, 'register'])->name('drivers.register');
        Route::post('/drivers/{driver}/resend-credentials', [DriverController::class, 'resendCredentials'])->name('drivers.resendCredentials');
        Route::get('/drivers/{driver}/qr', [DriverController::class, 'qrImage'])->name('drivers.qr');
        Route::post('/drivers/{driver}/qr/regenerate', [DriverController::class, 'regenerateQr'])->name('drivers.qr.regenerate');
        Route::post('/drivers/{driver}/qr/activate', [DriverController::class, 'activateQr'])->name('drivers.qr.activate');
        Route::post('/drivers/{driver}/qr/deactivate', [DriverController::class, 'deactivateQr'])->name('drivers.qr.deactivate');
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
    Route::put('/settings/toda-organizations/{organization}', [SettingController::class, 'updateTodaOrganization'])->name('settings.toda_organizations.organizations.update');
    Route::post('/settings/toda-organizations/{organization}/status', [SettingController::class, 'toggleTodaOrganization'])->name('settings.toda_organizations.organizations.status');
    Route::get('/system-health', [SystemHealthController::class, 'index'])->name('system-health');
    Route::get('/api/system-health', [SystemHealthController::class, 'api'])->name('system-health.api');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
