<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\StravaConnection;
use App\Models\User;
use App\Services\StravaClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

final readonly class ConnectStrava
{
    public function __construct(private StravaClient $strava) {}

    /**
     * Exchanges the OAuth code and stores (or replaces) the user's Strava tokens.
     */
    public function handle(User $user, string $code, ?string $scope): StravaConnection
    {
        $token = $this->strava->exchangeCode($code);

        return StravaConnection::query()->updateOrCreate(['user_id' => $user->id], [
            'athlete_id' => Arr::integer($token, 'athlete.id'),
            'access_token' => Arr::string($token, 'access_token'),
            'refresh_token' => Arr::string($token, 'refresh_token'),
            'expires_at' => Carbon::createFromTimestamp(Arr::integer($token, 'expires_at')),
            'scope' => $scope,
        ]);
    }
}
