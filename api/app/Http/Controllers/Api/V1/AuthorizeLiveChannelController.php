<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pusher\Pusher;

/** Signs the subscription to `presence-course.{slug}` so riders on the same course can see each other. */
final readonly class AuthorizeLiveChannelController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'socket_id' => ['required', 'string', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'string', 'regex:/^presence-course\.[a-z0-9-]+$/'],
        ]);

        $slug = mb_substr($data['channel_name'], mb_strlen('presence-course.'));
        abort_unless(Course::query()->where('slug', $slug)->exists(), 403);

        /** @var User $user */
        $user = $request->user();
        $config = config('services.pusher');
        $pusher = new Pusher($config['key'], $config['secret'], $config['app_id'], ['cluster' => $config['cluster']]);

        // only the display name is shared, never the e-mail
        $auth = $pusher->authorizePresenceChannel($data['channel_name'], $data['socket_id'], (string) $user->id, ['name' => $user->name]);

        return response()->json(json_decode($auth, true));
    }
}
