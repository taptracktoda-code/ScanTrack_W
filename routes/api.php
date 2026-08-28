<?php

use App\Http\Controllers\Api\DriverApiController;
use App\Http\Controllers\NfcScanController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [DriverApiController::class, 'login']);
Route::post('/register', [DriverApiController::class, 'register']);
Route::post('/nfc/scan', [NfcScanController::class, 'scan']);

Route::middleware('auth:sanctum')->prefix('driver')->group(function () {
    Route::post('/logout', [DriverApiController::class, 'logout']);
    Route::get('/profile', [DriverApiController::class, 'profile']);
    Route::get('/dashboard', [DriverApiController::class, 'dashboard']);
    Route::get('/attendance/today', [DriverApiController::class, 'todayAttendance']);
    Route::get('/attendance/{date}', [DriverApiController::class, 'attendanceByDate']);
    Route::get('/attendance', [DriverApiController::class, 'attendanceHistory']);
    Route::get('/attendance/monthly', [DriverApiController::class, 'monthlyAttendance']);
    Route::get('/attendance/summary', [DriverApiController::class, 'attendanceSummary']);
    Route::get('/trips/today', [DriverApiController::class, 'emptyTrips']);
    Route::get('/trips/history', [DriverApiController::class, 'emptyTrips']);
    Route::get('/trips/summary', [DriverApiController::class, 'emptyTripSummary']);
    Route::get('/schedule/{date}', [DriverApiController::class, 'emptySchedule']);
    Route::get('/schedule/upcoming', [DriverApiController::class, 'emptySchedule']);
});
