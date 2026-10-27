<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\PoiResource;
use App\Models\Poi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final readonly class FavoritePoiController
{
    /** The player's favourite places, each with the course it belongs to. */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $pois = $user->favoritePois()
            ->where('pois.published', true)
            ->whereHas('course', fn ($query) => $query->where('published', true))
            ->with('course:id,slug,name')
            ->orderBy('pois.distance_m')
            ->get();

        return PoiResource::collection($pois);
    }

    public function store(Request $request, Poi $poi): Response
    {
        abort_unless($poi->published, 404);

        /** @var User $user */
        $user = $request->user();
        $user->favoritePois()->syncWithoutDetaching([$poi->id]);

        return response()->noContent();
    }

    public function destroy(Request $request, Poi $poi): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->favoritePois()->detach($poi->id);

        return response()->noContent();
    }
}
