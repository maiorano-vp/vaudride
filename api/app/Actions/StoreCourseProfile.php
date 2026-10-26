<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;

final readonly class StoreCourseProfile
{
    public const string DISK = 'local';

    public static function path(string $slug): string
    {
        return "courses/{$slug}/profile.json";
    }

    /**
     * Validates a profile.json built by the pipeline and stores it as the course's profile.
     *
     * @return array{distance_m: int, elevation_gain_m: int}
     *
     * @throws ValidationException
     */
    public function handle(string $slug, string $json): array
    {
        try {
            $profile = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['data.profile_file' => 'Ce fichier n’est pas un JSON valide.']);
        }

        $validator = Validator::make(is_array($profile) ? $profile : [], [
            'slug' => ['required', 'in:'.$slug],
            'distance_m' => ['required', 'numeric', 'gt:0'],
            'elevation_gain_m' => ['required', 'numeric', 'gte:0'],
            'points' => ['required', 'array', 'min:2'],
            'points.*.d' => ['required', 'numeric', 'gte:0'],
            'points.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'points.*.lon' => ['required', 'numeric', 'between:-180,180'],
            'points.*.ele' => ['required', 'numeric'],
            'points.*.grade' => ['required', 'numeric'],
        ], attributes: ['slug' => 'slug du profil']);

        if ($validator->fails()) {
            throw ValidationException::withMessages(['data.profile_file' => 'Profil invalide: '.$validator->errors()->first()]);
        }

        Storage::disk(self::DISK)->put(self::path($slug), $json);

        return [
            'distance_m' => (int) round($profile['distance_m']),
            'elevation_gain_m' => (int) round($profile['elevation_gain_m']),
        ];
    }
}
