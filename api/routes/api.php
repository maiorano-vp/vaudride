<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\CourseImportController;
use App\Http\Controllers\Api\V1\AuthorizeLiveChannelController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\CourseGhostsController;
use App\Http\Controllers\Api\V1\CourseProfileController;
use App\Http\Controllers\Api\V1\DeleteRiderSettingsController;
use App\Http\Controllers\Api\V1\DisconnectStravaController;
use App\Http\Controllers\Api\V1\ExportRideController;
use App\Http\Controllers\Api\V1\FavoritePoiController;
use App\Http\Controllers\Api\V1\ForgotPasswordController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\LogoutController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\ResetPasswordController;
use App\Http\Controllers\Api\V1\RideController;
use App\Http\Controllers\Api\V1\SegmentGhostController;
use App\Http\Controllers\Api\V1\SegmentLeaderboardController;
use App\Http\Controllers\Api\V1\ShowFtpEstimateController;
use App\Http\Controllers\Api\V1\ShowRiderSettingsController;
use App\Http\Controllers\Api\V1\ShowStravaController;
use App\Http\Controllers\Api\V1\StravaCallbackController;
use App\Http\Controllers\Api\V1\StravaConnectController;
use App\Http\Controllers\Api\V1\UpdateAvatarController;
use App\Http\Controllers\Api\V1\UpdateRiderSettingsController;
use App\Http\Controllers\Api\V1\UploadRideToStravaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/{slug}', [CourseController::class, 'show'])->name('courses.show');
    Route::get('courses/{slug}/profile', CourseProfileController::class)->name('courses.profile');

    Route::middleware('throttle:10,1')->group(function (): void {
        Route::post('auth/register', RegisterController::class)->name('auth.register');
        Route::post('auth/login', LoginController::class)->name('auth.login');
        Route::post('auth/forgot-password', ForgotPasswordController::class)->name('auth.forgot-password');
        Route::post('auth/reset-password', ResetPasswordController::class)->name('auth.reset-password');
    });

    // Strava redirects the browser here, so it cannot carry a bearer token; the signed state identifies the user
    Route::get('strava/callback', StravaCallbackController::class)->middleware('throttle:20,1')->name('strava.callback');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', LogoutController::class)->name('auth.logout');
        Route::get('me', MeController::class)->name('me');
        Route::get('segments/{segment}/leaderboard', SegmentLeaderboardController::class)->name('segments.leaderboard');
        Route::get('segments/{segment}/ghost', SegmentGhostController::class)->name('segments.ghost');
        Route::get('courses/{slug}/ghosts', CourseGhostsController::class)->name('courses.ghosts');
        Route::get('me/favorites', [FavoritePoiController::class, 'index'])->name('me.favorites.index');
        Route::put('me/favorites/{poi}', [FavoritePoiController::class, 'store'])->name('me.favorites.store');
        Route::delete('me/favorites/{poi}', [FavoritePoiController::class, 'destroy'])->name('me.favorites.destroy');
        Route::put('me/avatar', UpdateAvatarController::class)->name('me.avatar');
        Route::get('me/settings', ShowRiderSettingsController::class)->name('me.settings.show');
        Route::put('me/settings', UpdateRiderSettingsController::class)->name('me.settings.update');
        Route::delete('me/settings', DeleteRiderSettingsController::class)->name('me.settings.destroy');
        Route::get('me/ftp-estimate', ShowFtpEstimateController::class)->middleware('throttle:30,1')->name('me.ftp-estimate');
        Route::post('broadcasting/auth', AuthorizeLiveChannelController::class)->middleware('throttle:30,1')->name('broadcasting.auth');
        Route::apiResource('rides', RideController::class)->only(['index', 'store', 'show']);
        Route::get('rides/{ride}/export/{format}', ExportRideController::class)->whereIn('format', ['fit', 'tcx'])->name('rides.export');
        Route::post('rides/{ride}/strava', UploadRideToStravaController::class)->name('rides.strava');

        Route::get('strava', ShowStravaController::class)->name('strava.show');
        Route::get('strava/connect', StravaConnectController::class)->name('strava.connect');
        Route::delete('strava', DisconnectStravaController::class)->name('strava.disconnect');
    });

    // Editorial endpoints for reviewing imported routes (admin users only)
    Route::middleware(['auth:sanctum', 'can:manage-courses'])->prefix('admin')->name('admin.')->group(function (): void {
        Route::apiResource('course-imports', CourseImportController::class)->only(['index', 'store', 'show', 'update']);
    });
});
