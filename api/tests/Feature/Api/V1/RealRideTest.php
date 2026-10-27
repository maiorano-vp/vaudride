<?php

declare(strict_types=1);

use App\Enums\CourseActionType;
use App\Models\Course;
use App\Models\CourseAction;
use App\Models\RealRide;
use App\Models\User;

it('lets a player declare that they rode a course for real, once a day', function (): void {
    $user = User::factory()->create()->fresh();
    $course = Course::factory()->create(['slug' => 'lavaux', 'name' => ['fr' => 'Lavaux']]);
    $day = now()->subDays(3)->toDateString();

    $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/real-rides/lavaux', ['ridden_on' => $day])
        ->assertCreated()
        ->assertJsonPath('data.ridden_on', $day)
        ->assertJsonPath('data.course.slug', 'lavaux');
    $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/real-rides/lavaux', ['ridden_on' => $day])->assertOk(); // same day: nothing new

    expect($user->realRides()->count())->toBe(1);
    $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/real-rides/lavaux', ['ridden_on' => now()->subDay()->toDateString()])->assertCreated();
    expect($user->realRides()->where('course_id', $course->id)->count())->toBe(2);
});

it('counts a declaration for the reports, once, without the person', function (): void {
    $user = User::factory()->create()->fresh();
    $course = Course::factory()->create(['slug' => 'lavaux']);
    $day = now()->subDay()->toDateString();

    $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/real-rides/lavaux', ['ridden_on' => $day]);
    $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/real-rides/lavaux', ['ridden_on' => $day]); // repeated

    $counted = CourseAction::query()->where('course_id', $course->id)->where('action', CourseActionType::DeclaredReal->value)->first();
    expect($counted->count)->toBe(1);
});

it('gives the passport back, most recent first, with the course names', function (): void {
    $user = User::factory()->create()->fresh();
    RealRide::factory()->for($user)->for(Course::factory()->create(['slug' => 'old', 'name' => ['fr' => 'Ancien']]))->create(['ridden_on' => now()->subDays(40)->toDateString()]);
    RealRide::factory()->for($user)->for(Course::factory()->create(['slug' => 'new', 'name' => ['fr' => 'Récent']]))->create(['ridden_on' => now()->subDays(2)->toDateString()]);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/real-rides')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.course.slug', 'new')
        ->assertJsonPath('data.0.course.name.fr', 'Récent')
        ->assertJsonPath('data.1.course.slug', 'old');
});

it('shows a player only their own passport, and lets them remove only their own', function (): void {
    $user = User::factory()->create()->fresh();
    $other = User::factory()->create()->fresh();
    $mine = RealRide::factory()->for($user)->create();
    $theirs = RealRide::factory()->for($other)->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/real-rides')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
    $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/me/real-rides/{$theirs->id}")->assertNotFound();
    $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/me/real-rides/{$mine->id}")->assertNoContent();

    expect(RealRide::query()->count())->toBe(1)->and(RealRide::query()->first()->id)->toBe($theirs->id);
});

it('keeps the declarations out of the way when the account goes', function (): void {
    $user = User::factory()->create()->fresh();
    RealRide::factory()->for($user)->create();

    $user->delete();

    expect(RealRide::query()->count())->toBe(0);
});

it('refuses a day in the future, a wrong format and a missing one', function (array $body): void {
    Course::factory()->create(['slug' => 'lavaux']);

    $this->actingAs(User::factory()->create()->fresh(), 'sanctum')->putJson('/api/v1/me/real-rides/lavaux', $body)
        ->assertUnprocessable()->assertJsonValidationErrors('ridden_on');
})->with([
    'future' => [fn (): array => ['ridden_on' => now()->addDay()->toDateString()]],
    'format' => [['ridden_on' => '12.10.2026']],
    'too old' => [['ridden_on' => '1990-01-01']],
    'missing' => [[]],
]);

it('answers 404 for an unknown or unpublished course', function (): void {
    Course::factory()->unpublished()->create(['slug' => 'hidden']);
    $user = User::factory()->create()->fresh();

    $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/real-rides/nope', ['ridden_on' => now()->toDateString()])->assertNotFound();
    $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/real-rides/hidden', ['ridden_on' => now()->toDateString()])->assertNotFound();
});

it('needs a signed-in player', function (): void {
    $this->getJson('/api/v1/me/real-rides')->assertUnauthorized();
    $this->putJson('/api/v1/me/real-rides/lavaux', ['ridden_on' => now()->toDateString()])->assertUnauthorized();
    $this->deleteJson('/api/v1/me/real-rides/'.RealRide::factory()->create()->id)->assertUnauthorized();
});
