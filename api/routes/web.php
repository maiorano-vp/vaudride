<?php

declare(strict_types=1);

use App\Http\Controllers\FlushOpcacheController;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (): View => view('welcome'));

// Called by the deploy (see deploy.php: opcache:flush), guarded by OPCACHE_FLUSH_TOKEN
Route::get('/opcache-flush', FlushOpcacheController::class)->middleware('throttle:10,1');
