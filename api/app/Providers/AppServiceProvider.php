<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\BuildActivityReport;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // one report per request, shared by the widgets of the reports page and its exports
        $this->app->scoped(BuildActivityReport::class);
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(fn (object $user, string $token): string => config('app.frontend_url').'/reset-password?token='.$token);
        Gate::define('manage-courses', fn (User $user): bool => $user->is_admin);
    }
}
