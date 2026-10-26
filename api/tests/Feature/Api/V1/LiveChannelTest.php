<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\User;

beforeEach(function (): void {
    config(['services.pusher' => ['key' => 'k', 'secret' => 's', 'app_id' => '1', 'cluster' => 'eu']]);
});

it('signs a presence channel for a known course and shares only the name', function (): void {
    $course = Course::factory()->create();
    $user = User::factory()->create()->fresh();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => 'presence-course.'.$course->slug,
    ]);

    $response->assertOk()->assertJsonStructure(['auth', 'channel_data']);
    $info = json_decode((string) $response->json('channel_data'), true);
    expect($info['user_id'])->toBe((string) $user->id)
        ->and($info['user_info'])->toBe(['name' => $user->name, 'kit' => null]);
});

it('refuses unknown courses and other channels', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'presence-course.nowhere'])->assertForbidden();
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-admin'])->assertUnprocessable();
});

it('refuses guests', function (): void {
    $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'presence-course.x'])->assertUnauthorized();
});

it('shares the player’s kit with the other riders', function (): void {
    $kit = ['jersey' => '#ff0000', 'accent' => '#ffffff', 'shorts' => '#111111', 'helmet' => '#ffffff', 'bike' => '#0a4069', 'jerseyStyle' => 'band', 'helmetStyle' => 'cap', 'wheels' => 'disc'];
    $course = Course::factory()->create();
    $user = User::factory()->create(['avatar' => $kit])->fresh();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => 'presence-course.'.$course->slug,
    ]);

    $info = json_decode((string) $response->json('channel_data'), true);
    expect($info['user_info']['kit'])->toBe($kit)->and(json_encode($info))->not->toContain($user->email);
});
