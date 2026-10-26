<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('registers a user and returns a token', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Léa',
        'email' => 'lea@example.com',
        'password' => 'correct-horse-battery',
        'device_name' => 'laptop',
    ]);

    $response->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
    expect(User::query()->where('email', 'lea@example.com')->exists())->toBeTrue();
    $this->assertDatabaseHas('personal_access_tokens', ['name' => 'laptop']);
});

it('rejects registration with a duplicate email or weak password', function (): void {
    User::factory()->create(['email' => 'lea@example.com']);

    $this->postJson('/api/v1/auth/register', ['name' => 'X', 'email' => 'lea@example.com', 'password' => 'short'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('logs in with valid credentials', function (): void {
    User::factory()->create(['email' => 'lea@example.com', 'password' => 'correct-horse-battery']);

    $this->postJson('/api/v1/auth/login', ['email' => 'lea@example.com', 'password' => 'correct-horse-battery'])
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id']]);
});

it('rejects wrong credentials', function (): void {
    User::factory()->create(['email' => 'lea@example.com', 'password' => 'correct-horse-battery']);

    $this->postJson('/api/v1/auth/login', ['email' => 'lea@example.com', 'password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns the current user for a valid token and 401 without one', function (): void {
    $this->getJson('/api/v1/me')->assertUnauthorized();

    $token = User::factory()->create(['name' => 'Léa'])->createToken('t')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.name', 'Léa');
});

it('revokes the token on logout', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('t')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});

it('throttles repeated login attempts', function (): void {
    foreach (range(1, 10) as $_) {
        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'x']);
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'x'])->assertTooManyRequests();
});

it('mails a reset link with the token only, and answers the same for unknown emails', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'lea@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'lea@example.com'])->assertNoContent();
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertNoContent();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user): bool {
        $url = ResetPassword::$createUrlCallback;

        return str_starts_with($url($user, $n->token), config('app.frontend_url').'/reset-password?token=')
            && ! str_contains($url($user, $n->token), 'email');
    });
    Notification::assertCount(1);
});

it('resets the password and revokes existing tokens', function (): void {
    $user = User::factory()->create(['email' => 'lea@example.com']);
    $user->createToken('laptop');
    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => 'lea@example.com', 'password' => 'new-correct-horse-battery'])
        ->assertNoContent();

    expect(Hash::check('new-correct-horse-battery', $user->refresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);
});

it('rejects an invalid reset token', function (): void {
    User::factory()->create(['email' => 'lea@example.com']);

    $this->postJson('/api/v1/auth/reset-password', ['token' => 'bad', 'email' => 'lea@example.com', 'password' => 'new-correct-horse-battery'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
