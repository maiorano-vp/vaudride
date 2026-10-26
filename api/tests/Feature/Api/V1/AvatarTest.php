<?php

declare(strict_types=1);

use App\Models\User;

function validKit(array $override = []): array
{
    return [
        'jersey' => '#d62828', 'accent' => '#ffffff', 'shorts' => '#111111', 'helmet' => '#ffffff', 'bike' => '#0a4069',
        'jerseyStyle' => 'stripes', 'helmetStyle' => 'aero', 'wheels' => 'disc',
        ...$override,
    ];
}

it('saves the player’s kit and returns it with the profile', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/avatar', validKit())
        ->assertOk()
        ->assertJsonPath('data.avatar.jersey', '#d62828')
        ->assertJsonPath('data.avatar.wheels', 'disc');

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/me')->assertJsonPath('data.avatar.helmetStyle', 'aero');
    expect($user->fresh()->avatar)->toBe(validKit());
});

it('has no kit until the player picks one', function (): void {
    $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/me')->assertJsonPath('data.avatar', null);
});

it('rejects colours and styles it does not know', function (array $override, string $field): void {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->putJson('/api/v1/me/avatar', validKit($override))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'named colour' => [['jersey' => 'red'], 'jersey'],
    'short hex' => [['bike' => '#fff'], 'bike'],
    'injected markup' => [['accent' => '#12345<'], 'accent'],
    'unknown jersey' => [['jerseyStyle' => 'camo'], 'jerseyStyle'],
    'unknown helmet' => [['helmetStyle' => 'hat'], 'helmetStyle'],
    'unknown wheels' => [['wheels' => 'square'], 'wheels'],
    'missing field' => [['helmet' => null], 'helmet'],
]);

it('ignores extra fields', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/avatar', validKit(['is_admin' => true]));

    expect($user->fresh()->is_admin)->toBeFalse()->and($user->fresh()->avatar)->not->toHaveKey('is_admin');
});

it('needs a signed-in player', function (): void {
    $this->putJson('/api/v1/me/avatar', validKit())->assertUnauthorized();
});
