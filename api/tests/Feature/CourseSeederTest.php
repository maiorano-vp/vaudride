<?php

declare(strict_types=1);

use App\Models\Course;
use Database\Seeders\CourseSeeder;

it('inserts the launch courses and keeps later admin edits', function (): void {
    $this->seed(CourseSeeder::class);
    expect(Course::query()->pluck('slug')->sort()->values()->all())->toBe(['corniche-de-lavaux', 'marchairuz-biere']);

    Course::query()->where('slug', 'corniche-de-lavaux')->update(['published' => false]);
    $this->seed(CourseSeeder::class);

    expect(Course::query()->where('slug', 'corniche-de-lavaux')->firstOrFail()->published)->toBeFalse()
        ->and(Course::query()->count())->toBe(2);
});
