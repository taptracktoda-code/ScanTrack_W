<?php

use App\Http\Controllers\Api\DriverApiController;
use App\Http\Controllers\QrScanController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Api\ManagerApiController;
use Illuminate\Support\Facades\Route;


Route::post('/login', [DriverApiController::class, 'login']);
Route::post('/admin/login', [\App\Http\Controllers\AuthController::class, 'adminLogin']);
Route::post('/manager/login', [ManagerApiController::class, 'login']);
Route::middleware(['auth:sanctum', 'manager'])->prefix('manager')->group(function () {
    Route::post('/logout', [ManagerApiController::class, 'logout']);
    Route::post('/change-password', [ManagerApiController::class, 'changePassword']);
    Route::middleware('manager.password.changed')->group(function () {
        Route::get('/dashboard', [ManagerApiController::class, 'dashboard']);
        Route::get('/drivers', [ManagerApiController::class, 'drivers']);
        Route::post('/drivers', [ManagerApiController::class, 'registerDriver']);
        Route::get('/trip-destinations', [ManagerApiController::class, 'tripDestinations']);
        Route::post('/trip-destinations', [ManagerApiController::class, 'createTripDestination']);
        Route::delete('/trip-destinations/{destination}', [ManagerApiController::class, 'deactivateTripDestination']);
        Route::get('/schedules', [ManagerApiController::class, 'schedules']);
        Route::post('/schedules', [ManagerApiController::class, 'createSchedule']);
        Route::put('/schedules/{event}', [ManagerApiController::class, 'updateSchedule']);
        Route::delete('/schedules/{event}', [ManagerApiController::class, 'deleteSchedule']);
        Route::get('/queue', [ManagerApiController::class, 'queue']);
        Route::post('/queue', [ManagerApiController::class, 'enqueueDriver']);
        Route::post('/queue/serve-next', [ManagerApiController::class, 'serveNext']);
        Route::post('/queue/{entry}/complete', [ManagerApiController::class, 'completeQueueEntry']);
    });
});
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'apiDashboard']);
    Route::patch('/managers/{manager}/organization', [\App\Http\Controllers\ManagerController::class, 'assignOrganization']);
    Route::post('/qr/scan', [QrScanController::class, 'scan']);
});

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/qr/scan', [QrScanController::class, 'scan']);
    Route::get('/admin/transactions', [ReportController::class, 'transactionsApi']);
});

Route::middleware(['auth:sanctum', 'driver'])->prefix('driver')->group(function () {
    Route::post('/logout', [DriverApiController::class, 'logout']);
    Route::post('/change-password', [DriverApiController::class, 'changePassword']);
    Route::middleware('driver.password.changed')->group(function () {
        Route::post('/shift-status', [DriverApiController::class, 'updateShiftStatus']);
        Route::get('/profile', [DriverApiController::class, 'profile']);
        Route::get('/dashboard', [DriverApiController::class, 'dashboard']);
        Route::get('/attendance/today', [DriverApiController::class, 'todayAttendance']);
        Route::get('/attendance/{date}', [DriverApiController::class, 'attendanceByDate']);
        Route::get('/attendance', [DriverApiController::class, 'attendanceHistory']);
        Route::get('/attendance/monthly', [DriverApiController::class, 'monthlyAttendance']);
        Route::get('/attendance/summary', [DriverApiController::class, 'attendanceSummary']);
        Route::get('/trip-destinations', [DriverApiController::class, 'tripDestinations']);
        Route::post('/trips', [DriverApiController::class, 'startTrip']);
        Route::get('/trips/active', [DriverApiController::class, 'activeTrip']);
        Route::get('/trips/today', [DriverApiController::class, 'todayTrips']);
        Route::get('/trips/history', [DriverApiController::class, 'tripHistory']);
        Route::get('/trips/summary', [DriverApiController::class, 'tripSummary']);
        Route::get('/trips/{tripId}', [DriverApiController::class, 'tripDetails']);
        Route::post('/trips/{tripId}/complete', [DriverApiController::class, 'completeTrip']);
        Route::get('/schedule/upcoming', [DriverApiController::class, 'upcomingSchedule']);
        Route::get('/schedule/{date}', [DriverApiController::class, 'scheduleByDate']);
    });
});
