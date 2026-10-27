<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\FindMissingTranslations;
use App\Filament\Resources\Courses\CourseResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** The courses with the most texts still missing in German or English, to translate first. */
final class IncompleteTranslationsTable extends TableWidget
{
    private const int SHOWN = 20;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Parcours à traduire';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => collect(app(FindMissingTranslations::class)->handle()['courses'])->take(self::SHOWN)->keyBy('id')->all())
            ->columns([
                TextColumn::make('name')->label('Parcours')->url(fn (array $record): string => CourseResource::getUrl('edit', ['record' => $record['id']])),
                TextColumn::make('total')->label('Textes'),
                TextColumn::make('missing.de')->label('Manquants en allemand'),
                TextColumn::make('missing.en')->label('Manquants en anglais'),
            ])
            ->emptyStateHeading('Tout est traduit')
            ->paginated(false);
    }
}
