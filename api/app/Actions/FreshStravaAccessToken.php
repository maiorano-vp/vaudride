<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\StravaConnection;
use App\Services\StravaClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

final readonly class FreshStravaAccessToken
{
    public function __construct(private StravaClient $strava) {}

    /**
     * Returns a valid access token, refreshing (and persisting) it when it expires within 5 minutes.
     */
    public function handle(StravaConnection $connection): string
    {
        if ($connection->expires_at->isAfter(now()->addMinutes(5))) {
            return $connection->access_token;
        }

        $token = $this->strava->refresh($connection->refresh_token);

        $connection->update([
            'access_token' => Arr::string($token, 'access_token'),
            'refresh_token' => Arr::string($token, 'refresh_token'),
            'expires_at' => Carbon::createFromTimestamp(Arr::integer($token, 'expires_at')),
        ]);

        return $connection->access_token;
    }
}
