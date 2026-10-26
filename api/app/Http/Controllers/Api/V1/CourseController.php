<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\CourseResource;
use App\Models\Course;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class CourseController
{
    public function index(): AnonymousResourceCollection
    {
        return CourseResource::collection(Course::query()->where('published', true)->orderBy('slug')->get());
    }

    public function show(string $slug): CourseResource
    {
        return new CourseResource(
            Course::query()->where('published', true)->where('slug', $slug)->with('pois')->firstOrFail(),
        );
    }
}
