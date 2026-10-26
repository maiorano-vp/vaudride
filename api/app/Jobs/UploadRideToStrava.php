<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\ExportRideAsFit;
use App\Actions\FreshStravaAccessToken;
use App\Models\Course;
use App\Models\Ride;
use App\Models\StravaConnection;
use App\Services\StravaClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

final class UploadRideToStrava implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Strava processes uploads asynchronously: ~12 checks, 15 s apart. */
    public int $tries = 12;

    public function __construct(public Ride $ride) {}

    public function handle(StravaClient $strava, FreshStravaAccessToken $freshToken, ExportRideAsFit $exportFit): void
    {
        if ($this->ride->strava_activity_id !== null) {
            return;
        }

        $connection = StravaConnection::query()->where('user_id', $this->ride->user_id)->first();
        if (! $connection instanceof StravaConnection) {
            $this->ride->update(['strava_error' => 'Strava is not connected.']);

            return;
        }

        try {
            $token = $freshToken->handle($connection);

            if ($this->ride->strava_upload_id === null) {
                $courseName = Course::query()->findOrFail($this->ride->course_id)->name['fr'];
                $upload = $strava->upload($token, $exportFit->handle($this->ride), $courseName, $this->ride->id);
                $this->ride->update(['strava_upload_id' => Arr::integer($upload, 'id')]);
            }

            $status = $strava->uploadStatus($token, (int) $this->ride->strava_upload_id);
        } catch (RequestException $exception) {
            if ($exception->response->serverError()) {
                throw $exception; // transient: let the queue retry
            }

            $this->ride->update(['strava_error' => 'Strava answered HTTP '.$exception->response->status().'.']);

            return;
        }

        $error = $status['error'] ?? null;
        if (is_string($error) && $error !== '') {
            $this->ride->update(['strava_error' => $error]);

            return;
        }

        $activityId = $status['activity_id'] ?? null;
        if (is_int($activityId)) {
            $this->ride->update(['strava_activity_id' => $activityId, 'strava_error' => null]);

            return;
        }

        $this->release(15);
    }
}
