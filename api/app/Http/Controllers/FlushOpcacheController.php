<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Lets the deploy reset OPcache inside a real PHP-FPM worker: opcache_reset() from the CLI (SSH) only
 * resets the CLI's own cache, which is not shared with the FPM pool serving web requests.
 */
final readonly class FlushOpcacheController
{
    public function __invoke(Request $request): Response
    {
        $token = (string) config('services.opcache.flush_token');
        abort_if($token === '' || ! hash_equals($token, $request->string('token')->toString()), 403);

        $flushed = function_exists('opcache_reset') && opcache_reset();

        return response($flushed ? 'opcache flushed' : 'opcache_reset unavailable', 200, ['Content-Type' => 'text/plain']);
    }
}
