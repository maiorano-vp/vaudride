<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\StoreCourseProfile;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final readonly class CourseProfileController
{
    public function __invoke(Request $request, string $slug): Response
    {
        $path = StoreCourseProfile::path($slug);
        $disk = Storage::disk(StoreCourseProfile::DISK);
        abort_unless(Course::query()->where('published', true)->where('slug', $slug)->exists() && $disk->exists($path), 404);

        $response = response($disk->get($path), 200, ['Content-Type' => 'application/json'])
            ->setEtag((string) $disk->checksum($path))
            ->setCache(['public' => true, 'max_age' => 300]);
        $response->isNotModified($request);

        return $response;
    }
}
