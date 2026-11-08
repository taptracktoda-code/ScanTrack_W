<?php

use App\Http\Controllers\Api\AdminDriverApiController;
use App\Http\Controllers\Api\TodaQueueController;
use App\Http\Controllers\Api\MobileOperationsController;
use App\Http\Controllers\Api\DriverApiController;
use App\Http\Controllers\Api\DriverPasswordOtpController;
use App\Http\Controllers\Api\ManagerApiController;
use App\Http\Controllers\Api\ManagerPasswordOtpController;
use App\Http\Controllers\Api\MobileNotificationController;
use App\Http\Controllers\Api\PublicDriverRegistrationController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\QrScanController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [DriverApiController::class, 'login'])->middleware('throttle:5,1');
Route::post('/admin/login', [AuthController::class, 'adminLogin'])->middleware('throttle:5,1');
Route::post('/manager/login', [ManagerApiController::class, 'login'])->middleware('throttle:5,1');
Route::post('/drivers/registration', [PublicDriverRegistrationController::class, 'store'])->middleware('throttle:10,1');
Route::middleware('throttle:5,1')->prefix('driver/password/otp')->group(function () {
    Route::post('/request', [DriverPasswordOtpController::class, 'requestCode']);
    Route::post('/verify', [DriverPasswordOtpController::class, 'verifyCode']);
    Route::post('/reset', [DriverPasswordOtpController::class, 'resetPassword']);
});
Route::middleware('throttle:5,1')->prefix('manager/password/otp')->group(function () {
    Route::post('/request', [ManagerPasswordOtpController::class, 'requestCode']);
    Route::post('/verify', [ManagerPasswordOtpController::class, 'verifyCode']);
    Route::post('/reset', [ManagerPasswordOtpController::class, 'resetPassword']);
});
Route::middleware('auth:sanctum')->get('/user', [UserApiController::class, 'show']);
Route::middleware(['auth:sanctum', 'super-admin'])->prefix('admin')->group(function () {
    Route::get('/drivers', [AdminDriverApiController::class, 'index']);
    Route::post('/logout', [AdminDriverApiController::class, 'logout']);
});
Route::middleware(['auth:sanctum', 'manager'])->prefix('manager')->group(function () {
    Route::post('/logout', [ManagerApiController::class, 'logout']);
    Route::post('/change-password', [ManagerApiController::class, 'changePassword']);
    Route::middleware('manager.password.changed')->group(function () {
        Route::get('/notifications', [MobileNotificationController::class, 'index']);
        Route::get('/notifications/unread/count', [MobileNotificationController::class, 'unreadCount']);
        Route::put('/notifications/{notificationId}/read', [MobileNotificationController::class, 'markAsRead']);
        Route::post('/notifications/push-token', [MobileNotificationController::class, 'registerPushToken']);
        Route::delete('/notifications/push-token', [MobileNotificationController::class, 'removePushToken']);
        Route::post('/profile/avatar', [ManagerApiController::class, 'uploadProfileAvatar']);
        Route::delete('/profile/avatar', [ManagerApiController::class, 'removeProfileAvatar']);
        Route::get('/dashboard', [ManagerApiController::class, 'dashboard']);
        Route::post('/qr/scan', [ManagerApiController::class, 'scanQr']);
        Route::get('/drivers', [ManagerApiController::class, 'drivers']);
        Route::post('/drivers', [ManagerApiController::class, 'registerDriver']);
        Route::get('/trip-destinations', [ManagerApiController::class, 'tripDestinations']);
        Route::post('/trip-destinations', [ManagerApiController::class, 'createTripDestination']);
        Route::delete('/trip-destinations/{destination}', [ManagerApiController::class, 'deactivateTripDestination']);
        Route::get('/schedules', [ManagerApiController::class, 'schedules']);
        Route::post('/schedules', [ManagerApiController::class, 'createSchedule']);
        Route::put('/schedules/{event}', [ManagerApiController::class, 'updateSchedule']);
        Route::delete('/schedules/{event}', [ManagerApiController::class, 'deleteSchedule']);
        Route::get('/queue/removals', [TodaQueueController::class, 'activity']);
        Route::get('/reports/daily', [MobileOperationsController::class, 'report']);
        Route::get('/issues', [MobileOperationsController::class, 'issues']);
        Route::post('/issues/{issue}/decision', [MobileOperationsController::class, 'decide']);
        Route::post('/queue/{entry}/remove', [TodaQueueController::class, 'remove']);
        Route::get('/queue', [ManagerApiController::class, 'queue']);
        Route::post('/queue', [ManagerApiController::class, 'enqueueDriver']);
        Route::post('/queue/serve-next', [ManagerApiController::class, 'serveNext']);
        Route::post('/queue/{entry}/complete', [ManagerApiController::class, 'completeQueueEntry']);
    });
});
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'apiDashboard']);
    Route::patch('/managers/{manager}/organization', [ManagerController::class, 'assignOrganization']);
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
        Route::get('/notifications', [MobileNotificationController::class, 'index']);
        Route::get('/notifications/unread/count', [MobileNotificationController::class, 'unreadCount']);
        Route::put('/notifications/{notificationId}/read', [MobileNotificationController::class, 'markAsRead']);
        Route::post('/notifications/push-token', [MobileNotificationController::class, 'registerPushToken']);
        Route::delete('/notifications/push-token', [MobileNotificationController::class, 'removePushToken']);
        Route::post('/profile/avatar', [DriverApiController::class, 'uploadProfileAvatar']);
        Route::delete('/profile/avatar', [DriverApiController::class, 'removeProfileAvatar']);
        Route::post('/shift-status', [DriverApiController::class, 'updateShiftStatus']);
        Route::get('/profile', [DriverApiController::class, 'profile']);
        Route::get('/toda', [TodaQueueController::class, 'myToda']);
        Route::get('/issues', [MobileOperationsController::class, 'issues']);
        Route::post('/issues', [MobileOperationsController::class, 'submit']);
        Route::get('/dashboard', [DriverApiController::class, 'dashboard']);
        Route::get('/attendance/today', [DriverApiController::class, 'todayAttendance']);
        Route::get('/attendance/monthly', [DriverApiController::class, 'monthlyAttendance']);
        Route::get('/attendance/summary', [DriverApiController::class, 'attendanceSummary']);
        Route::get('/attendance', [DriverApiController::class, 'attendanceHistory']);
        Route::get('/attendance/{date}', [DriverApiController::class, 'attendanceByDate']);
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
