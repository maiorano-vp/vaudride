<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordRide;
use App\Http\Requests\Api\V1\StoreRideRequest;
use App\Http\Resources\Api\V1\RideResource;
use App\Jobs\UploadRideToStrava;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

final readonly class RideController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return RideResource::collection(
            $this->user($request)->rides()->summary()->with('course')->latest('started_at')->paginate(20),
        );
    }

    public function store(StoreRideRequest $request, RecordRide $recordRide): JsonResponse
    {
        $course = Course::query()->where('slug', $request->string('course')->toString())->firstOrFail();

        /** @var non-empty-list<array{t: int, d: float|int, p: int, c?: int|null, h?: int|null, s: float|int}> $samples */
        $samples = $request->validated('samples');

        $ride = $recordRide->handle(
            $this->user($request),
            $course,
            Carbon::parse($request->string('started_at')->toString()),
            $request->integer('elevation_gain_m'),
            $samples,
        );

        if ($request->boolean('upload_to_strava') && $this->user($request)->stravaConnection()->exists()) {
            UploadRideToStrava::dispatch($ride);
        }

        return new RideResource($ride->load('course'))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Rides are only reachable through the owner's relation, so other users' ids answer 404.
     */
    public function show(Request $request, string $ride): RideResource
    {
        return new RideResource($this->user($request)->rides()->with('course')->findOrFail($ride));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
