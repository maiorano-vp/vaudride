<?php

declare(strict_types=1);

use App\Enums\CourseActionType;
use App\Models\Course;
use App\Models\CourseAction;
use Illuminate\Support\Facades\Schema;

it('counts a step towards riding for real, for anyone, with nothing about them', function (): void {
    $course = Course::factory()->create(['slug' => 'lavaux']);

    $this->postJson('/api/v1/courses/lavaux/actions', ['action' => 'gpx_download'])->assertNoContent();
    $this->postJson('/api/v1/courses/lavaux/actions', ['action' => 'gpx_download'])->assertNoContent();
    $this->postJson('/api/v1/courses/lavaux/actions', ['action' => 'transit_start'])->assertNoContent();

    $rows = CourseAction::query()->where('course_id', $course->id)->get()->keyBy(fn (CourseAction $r): string => $r->action->value);
    expect($rows)->toHaveCount(2)
        ->and($rows['gpx_download']->count)->toBe(2)
        ->and($rows['transit_start']->count)->toBe(1)
        ->and($rows['gpx_download']->day->toDateString())->toBe(now('Europe/Zurich')->toDateString());
});

it('keeps no trace of who took the step', function (): void {
    expect(Schema::getColumnListing('course_actions'))->toEqualCanonicalizing(['id', 'course_id', 'action', 'day', 'count', 'created_at', 'updated_at']);
});

it('counts a day apart from another', function (): void {
    $course = Course::factory()->create(['slug' => 'lavaux']);
    CourseAction::factory()->for($course)->create(['action' => CourseActionType::GpxDownload, 'day' => now()->subDay()->toDateString(), 'count' => 5]);

    $this->postJson('/api/v1/courses/lavaux/actions', ['action' => 'gpx_download'])->assertNoContent();

    expect(CourseAction::query()->count())->toBe(2)
        ->and((int) CourseAction::query()->sum('count'))->toBe(6);
});

it('refuses an unknown step, a missing one, and the declaration, which has its own endpoint', function (array $body): void {
    Course::factory()->create(['slug' => 'lavaux']);

    $this->postJson('/api/v1/courses/lavaux/actions', $body)->assertUnprocessable()->assertJsonValidationErrors('action');
    expect(CourseAction::query()->count())->toBe(0);
})->with([
    'unknown' => [['action' => 'buy_a_bike']],
    'missing' => [[]],
    'the declaration' => [['action' => 'declared_real']],
]);

it('answers 404 for an unknown or unpublished course', function (): void {
    Course::factory()->unpublished()->create(['slug' => 'hidden']);

    $this->postJson('/api/v1/courses/nope/actions', ['action' => 'gpx_download'])->assertNotFound();
    $this->postJson('/api/v1/courses/hidden/actions', ['action' => 'gpx_download'])->assertNotFound();
});

it('limits how often one visitor can count', function (): void {
    Course::factory()->create(['slug' => 'lavaux']);

    foreach (range(1, 60) as $_) {
        $this->postJson('/api/v1/courses/lavaux/actions', ['action' => 'gpx_download'])->assertNoContent();
    }

    $this->postJson('/api/v1/courses/lavaux/actions', ['action' => 'gpx_download'])->assertTooManyRequests();
});
