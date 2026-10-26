<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\LogoutController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\RideController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/{slug}', [CourseController::class, 'show'])->name('courses.show');

    Route::middleware('throttle:10,1')->group(function (): void {
        Route::post('auth/register', RegisterController::class)->name('auth.register');
        Route::post('auth/login', LoginController::class)->name('auth.login');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', LogoutController::class)->name('auth.logout');
        Route::get('me', MeController::class)->name('me');
        Route::apiResource('rides', RideController::class)->only(['index', 'store', 'show']);
    });
});
