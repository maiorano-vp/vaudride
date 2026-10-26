<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;

it('redirects guests to the admin login', function (): void {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/login')->assertOk();
});

it('only lets admins into the panel', function (): void {
    $panel = Filament::getPanel('admin');

    expect(User::factory()->create(['is_admin' => true])->canAccessPanel($panel))->toBeTrue()
        ->and(User::factory()->create()->canAccessPanel($panel))->toBeFalse();
});

it('forces an admin without an authenticator app to set up two-factor first', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin->fresh())->get('/admin');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('multi-factor-authentication');
});

it('keeps the two-factor secret and recovery codes encrypted at rest', function (): void {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $user->saveAppAuthenticationRecoveryCodes(['abcd-1234']);

    $raw = DB::table('users')->where('id', $user->id)->first();

    expect($raw->app_authentication_secret)->not->toContain('JBSWY3DPEHPK3PXP')
        ->and($raw->app_authentication_recovery_codes)->not->toContain('abcd-1234')
        ->and($user->fresh()->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP')
        ->and($user->fresh()->getAppAuthenticationRecoveryCodes())->toBe(['abcd-1234']);
});
