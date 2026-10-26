<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\CreateCourseImport;
use App\Enums\ImportSource;
use App\Exceptions\InvalidGpxException;
use App\Http\Requests\Api\V1\Admin\StoreCourseImportRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCourseImportRequest;
use App\Http\Resources\Api\V1\Admin\CourseImportResource;
use App\Models\CourseImport;
use App\Models\User;
use App\Services\GpxRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

final readonly class CourseImportController
{
    public function index(): AnonymousResourceCollection
    {
        return CourseImportResource::collection(CourseImport::query()->listing()->latest()->paginate(20));
    }

    public function store(StoreCourseImportRequest $request, CreateCourseImport $createCourseImport): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $file = $request->file('file');
        $gpx = $file !== null ? $file->getContent() : $request->string('gpx')->toString();

        try {
            $import = $createCourseImport->handle(
                $user,
                $gpx,
                ImportSource::tryFrom($request->string('source')->toString()) ?? ImportSource::GpxUpload,
                $request->filled('source_url') ? $request->string('source_url')->toString() : null,
                $request->filled('title') ? $request->string('title')->toString() : null,
                $request->filled('author') ? $request->string('author')->toString() : null,
            );
        } catch (InvalidGpxException $exception) {
            throw ValidationException::withMessages([$file !== null ? 'file' : 'gpx' => [$exception->getMessage()]]);
        }

        return new CourseImportResource($import)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $courseImport): CourseImportResource
    {
        return new CourseImportResource(CourseImport::query()->findOrFail($courseImport));
    }

    public function update(UpdateCourseImportRequest $request, string $courseImport): CourseImportResource
    {
        $import = CourseImport::query()->findOrFail($courseImport);
        $import->update($request->validated());

        try {
            GpxRoute::parse($import->gpx)->trim($import->trim_start_m, $import->trim_end_m, $import->reversed);
        } catch (InvalidGpxException $exception) {
            throw ValidationException::withMessages(['trim_start_m' => [$exception->getMessage()]]);
        }

        return new CourseImportResource($import);
    }
}
