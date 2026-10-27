<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Poi;
use App\Models\User;

function publishedPoi(): Poi
{
    return Poi::factory()->for(Course::factory()->create(['published' => true]))->create(['published' => true]);
}

it('saves a favourite place, once', function (): void {
    $user = User::factory()->create();
    $poi = publishedPoi();

    $this->actingAs($user, 'sanctum')->putJson("/api/v1/me/favorites/{$poi->id}")->assertNoContent();
    $this->actingAs($user, 'sanctum')->putJson("/api/v1/me/favorites/{$poi->id}")->assertNoContent();

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/favorites')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $poi->id)
        ->assertJsonPath('data.0.course.slug', $poi->course->slug);
});

it('removes a favourite', function (): void {
    $user = User::factory()->create();
    $poi = publishedPoi();
    $user->favoritePois()->attach($poi);

    $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/me/favorites/{$poi->id}")->assertNoContent();

    expect($user->favoritePois()->count())->toBe(0);
});

it('keeps each player’s favourites to themselves', function (): void {
    $poi = publishedPoi();
    User::factory()->create()->favoritePois()->attach($poi);

    $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/me/favorites')->assertJsonCount(0, 'data');
});

it('does not favourite an unpublished place', function (): void {
    $poi = Poi::factory()->create(['published' => false]);

    $this->actingAs(User::factory()->create(), 'sanctum')->putJson("/api/v1/me/favorites/{$poi->id}")->assertNotFound();
});

it('needs a signed-in player', function (): void {
    $this->getJson('/api/v1/me/favorites')->assertUnauthorized();
});
