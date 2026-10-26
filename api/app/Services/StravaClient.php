<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

final readonly class StravaClient
{
    private const string BASE_URL = 'https://www.strava.com';

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function authorizeUrl(string $state, string $redirectUri): string
    {
        return self::BASE_URL.'/oauth/authorize?'.http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'approval_prompt' => 'auto',
            'scope' => 'read,activity:write',
            'state' => $state,
        ]);
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws RequestException
     */
    public function exchangeCode(string $code): array
    {
        return $this->token(['code' => $code, 'grant_type' => 'authorization_code']);
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws RequestException
     */
    public function refresh(string $refreshToken): array
    {
        return $this->token(['refresh_token' => $refreshToken, 'grant_type' => 'refresh_token']);
    }

    /**
     * Uploads a FIT file as a virtual (trainer) ride; Strava processes it asynchronously.
     *
     * @return array<array-key, mixed>
     *
     * @throws RequestException
     */
    public function upload(string $accessToken, string $fit, string $name, string $externalId): array
    {
        return Http::withToken($accessToken)
            ->attach('file', $fit, 'ride.fit')
            ->post(self::BASE_URL.'/api/v3/uploads', [
                'data_type' => 'fit',
                'name' => $name,
                'sport_type' => 'VirtualRide',
                'trainer' => 1,
                'external_id' => $externalId,
            ])
            ->throw()
            ->collect()
            ->all();
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws RequestException
     */
    public function uploadStatus(string $accessToken, int $uploadId): array
    {
        return Http::withToken($accessToken)
            ->get(self::BASE_URL.'/api/v3/uploads/'.$uploadId)
            ->throw()
            ->collect()
            ->all();
    }

    /**
     * Best effort: the local connection is dropped even when Strava is unreachable.
     */
    public function deauthorize(string $accessToken): void
    {
        Http::asForm()->post(self::BASE_URL.'/oauth/deauthorize', ['access_token' => $accessToken]);
    }

    /**
     * @param  array<string, string>  $grant
     * @return array<array-key, mixed>
     *
     * @throws RequestException
     */
    private function token(array $grant): array
    {
        return Http::asForm()
            ->post(self::BASE_URL.'/oauth/token', [
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                ...$grant,
            ])
            ->throw()
            ->collect()
            ->all();
    }

    private function clientId(): string
    {
        return config()->string('services.strava.client_id');
    }

    private function clientSecret(): string
    {
        return config()->string('services.strava.client_secret');
    }
}
