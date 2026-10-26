<?php

declare(strict_types=1);

use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\Admins\Pages\EditAdmin;
use App\Filament\Resources\Admins\Pages\ListAdmins;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->me = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->me);
});

it('lists only the admins', function (): void {
    $player = User::factory()->create();

    Livewire::test(ListAdmins::class)
        ->assertCanSeeTableRecords([$this->me])
        ->assertCanNotSeeTableRecords([$player]);
});

it('creates an admin who can sign in to the panel', function (): void {
    Livewire::test(CreateAdmin::class)
        ->fillForm(['nickname' => 'NewAdmin', 'email' => 'new@vaud-promotion.ch', 'password' => 'a-long-password-123'])
        ->call('create')
        ->assertHasNoFormErrors();

    $admin = User::query()->where('email', 'new@vaud-promotion.ch')->firstOrFail();
    expect($admin->is_admin)->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-long-password-123', $admin->password))->toBeTrue();
});

it('rejects a short password', function (): void {
    Livewire::test(CreateAdmin::class)
        ->fillForm(['nickname' => 'NewAdmin', 'email' => 'new@vaud-promotion.ch', 'password' => 'short'])
        ->call('create')
        ->assertHasFormErrors(['password']);
});

it('keeps the password when the field is left empty on edit', function (): void {
    $admin = User::factory()->create(['is_admin' => true, 'password' => 'original-password-1']);

    Livewire::test(EditAdmin::class, ['record' => $admin->getKey()])
        ->fillForm(['nickname' => 'Renamed', 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->refresh()->nickname)->toBe('Renamed')
        ->and(Hash::check('original-password-1', $admin->password))->toBeTrue();
});

it('resets an admin’s two-factor setup', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $admin->saveAppAuthenticationRecoveryCodes(['abcd-1234']);

    Livewire::test(ListAdmins::class)->callAction(TestAction::make('reset2fa')->table($admin));

    $admin = $admin->fresh();
    expect($admin->getAppAuthenticationSecret())->toBeNull()
        ->and($admin->getAppAuthenticationRecoveryCodes())->toBeNull();
});

it('does not let admins delete themselves', function (): void {
    Livewire::test(ListAdmins::class)->assertActionHidden(TestAction::make('delete')->table($this->me));
});
