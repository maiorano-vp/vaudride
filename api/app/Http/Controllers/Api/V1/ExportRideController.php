<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\ExportRideAsFit;
use App\Actions\ExportRideAsTcx;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final readonly class ExportRideController
{
    public function __invoke(Request $request, string $ride, string $format, ExportRideAsFit $fit, ExportRideAsTcx $tcx): Response
    {
        $user = $request->user();
        assert($user instanceof User);
        $model = $user->rides()->findOrFail($ride);

        [$content, $mime] = match ($format) {
            'fit' => [$fit->handle($model), 'application/vnd.ant.fit'],
            default => [$tcx->handle($model), 'application/vnd.garmin.tcx+xml'],
        };

        return response($content, Response::HTTP_OK, [
            'Content-Type' => $mime,
            'Content-Disposition' => sprintf('attachment; filename="vaud-ride-%s.%s"', $model->started_at->format('Y-m-d-His'), $format === 'fit' ? 'fit' : 'tcx'),
        ]);
    }
}
