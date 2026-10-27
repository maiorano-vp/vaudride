<?php

declare(strict_types=1);

namespace App\Filament\Resources\CourseImports\Pages;

use App\Filament\Resources\CourseImports\CourseImportResource;
use Filament\Resources\Pages\ListRecords;

final class ListCourseImports extends ListRecords
{
    protected static string $resource = CourseImportResource::class;
}
