<?php

declare(strict_types=1);

namespace App\Filament\Resources\SegmentEfforts\Pages;

use App\Filament\Resources\SegmentEfforts\SegmentEffortResource;
use Filament\Resources\Pages\ListRecords;

final class ListSegmentEfforts extends ListRecords
{
    protected static string $resource = SegmentEffortResource::class;
}
