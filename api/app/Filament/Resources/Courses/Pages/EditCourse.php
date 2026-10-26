<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Resources\Courses\CourseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditCourse extends EditRecord
{
    protected static string $resource = CourseResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CourseResource::withProfile($data, $this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
