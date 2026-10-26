<?php

declare(strict_types=1);

use App\Models\User;

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
