<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

function runAdminMigration(): void
{
    (require database_path('migrations/2026_10_26_160000_provision_admin_user.php'))->up();
}

it('creates the admin from the environment password', function (): void {
    config(['users.admin.password' => 'a-strong-secret']);

    runAdminMigration();

    $admin = User::query()->where('email', 'digital@vaud-promotion.ch')->firstOrFail();
    expect($admin->is_admin)->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-strong-secret', $admin->password))->toBeTrue();
});

it('promotes an existing user and does not duplicate it', function (): void {
    User::factory()->create(['email' => 'digital@vaud-promotion.ch']);
    config(['users.admin.password' => 'a-strong-secret']);

    runAdminMigration();

    expect(User::query()->where('email', 'digital@vaud-promotion.ch')->count())->toBe(1)
        ->and(User::query()->where('email', 'digital@vaud-promotion.ch')->firstOrFail()->is_admin)->toBeTrue();
});

it('does nothing without ADMIN_PASSWORD', function (): void {
    config(['users.admin.password' => null]);

    runAdminMigration();

    expect(User::query()->count())->toBe(0);
});
