<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\DisconnectStravaController;
use App\Http\Controllers\Api\V1\ExportRideController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\LogoutController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\RideController;
use App\Http\Controllers\Api\V1\ShowStravaController;
use App\Http\Controllers\Api\V1\StravaCallbackController;
use App\Http\Controllers\Api\V1\StravaConnectController;
use App\Http\Controllers\Api\V1\UploadRideToStravaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/{slug}', [CourseController::class, 'show'])->name('courses.show');

    Route::middleware('throttle:10,1')->group(function (): void {
        Route::post('auth/register', RegisterController::class)->name('auth.register');
        Route::post('auth/login', LoginController::class)->name('auth.login');
    });

    // Strava redirects the browser here, so it cannot carry a bearer token; the signed state identifies the user
    Route::get('strava/callback', StravaCallbackController::class)->middleware('throttle:20,1')->name('strava.callback');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', LogoutController::class)->name('auth.logout');
        Route::get('me', MeController::class)->name('me');
        Route::apiResource('rides', RideController::class)->only(['index', 'store', 'show']);
        Route::get('rides/{ride}/export/{format}', ExportRideController::class)->whereIn('format', ['fit', 'tcx'])->name('rides.export');
        Route::post('rides/{ride}/strava', UploadRideToStravaController::class)->name('rides.strava');

        Route::get('strava', ShowStravaController::class)->name('strava.show');
        Route::get('strava/connect', StravaConnectController::class)->name('strava.connect');
        Route::delete('strava', DisconnectStravaController::class)->name('strava.disconnect');
    });
});
