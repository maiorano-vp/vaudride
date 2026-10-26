<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $email
 * @property-read CarbonInterface|null $email_verified_at
 * @property-read string $password
 * @property-read bool $is_admin
 * @property-read string|null $app_authentication_secret
 * @property-read list<string>|null $app_authentication_recovery_codes
 * @property-read string|null $remember_token
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Hidden([
    'password',
    'remember_token',
    'is_admin',
    'app_authentication_secret',
    'app_authentication_recovery_codes',
])]
final class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuids;
    use Notifiable;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'email' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'remember_token' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->forceFill(['app_authentication_secret' => $secret])->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>|null
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  list<string>|null  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->forceFill(['app_authentication_recovery_codes' => $codes])->save();
    }

    /**
     * @return HasMany<Ride, $this>
     */
    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }

    /**
     * @return HasOne<StravaConnection, $this>
     */
    public function stravaConnection(): HasOne
    {
        return $this->hasOne(StravaConnection::class);
    }

    /**
     * @return HasMany<CourseImport, $this>
     */
    public function courseImports(): HasMany
    {
        return $this->hasMany(CourseImport::class);
    }
}
