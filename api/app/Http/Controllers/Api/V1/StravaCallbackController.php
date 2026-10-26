<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\ConnectStrava;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use JsonException;

final readonly class StravaCallbackController
{
    /**
     * Browser redirect target after Strava's consent screen; always sends the user back to the web app.
     */
    public function __invoke(Request $request, ConnectStrava $connectStrava): RedirectResponse
    {
        $frontend = mb_rtrim(config()->string('services.frontend.url'), '/');
        $failed = redirect()->away($frontend.'/?strava=error');
        $code = $request->string('code')->toString();

        if ($request->has('error') || $code === '') {
            return $failed;
        }

        try {
            $state = json_decode(Crypt::decryptString($request->string('state')->toString()), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($state) || Arr::integer($state, 'exp') < now()->getTimestamp()) {
                return $failed;
            }

            $user = User::query()->findOrFail(Arr::string($state, 'user'));
            $connectStrava->handle($user, $code, $request->string('scope')->toString() ?: null);
        } catch (DecryptException|JsonException|InvalidArgumentException|ModelNotFoundException|RequestException) {
            return $failed;
        }

        return redirect()->away($frontend.'/?strava=connected');
    }
}
