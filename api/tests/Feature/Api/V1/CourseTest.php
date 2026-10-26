<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Poi;

it('lists only published courses, publicly', function (): void {
    Course::factory()->create(['slug' => 'a-course']);
    Course::factory()->unpublished()->create(['slug' => 'hidden']);

    $this->getJson('/api/v1/courses')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'a-course')
        ->assertJsonPath('data.0.profile_url', 'http://localhost:3000/courses/a-course/profile.json?v=1');
});

it('shows a course with its POIs ordered by distance', function (): void {
    $course = Course::factory()->create(['slug' => 'marchairuz']);
    Poi::factory()->for($course)->create(['distance_m' => 900, 'title' => ['fr' => 'Col']]);
    Poi::factory()->for($course)->create(['distance_m' => 100, 'title' => ['fr' => 'Bière']]);

    $this->getJson('/api/v1/courses/marchairuz')
        ->assertOk()
        ->assertJsonPath('data.pois.0.title.fr', 'Bière')
        ->assertJsonPath('data.pois.1.title.fr', 'Col');
});

it('returns 404 for unknown or unpublished courses', function (): void {
    Course::factory()->unpublished()->create(['slug' => 'hidden']);

    $this->getJson('/api/v1/courses/hidden')->assertNotFound();
    $this->getJson('/api/v1/courses/nope')->assertNotFound();
});
