<?php

declare(strict_types=1);

namespace App\Filament\Reports\Widgets;

use App\Actions\BuildActivityReport;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/** How much each course was ridden over the chosen period, most ridden first. */
final class CoursesReportTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Parcours les plus roulés';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => collect(app(BuildActivityReport::class)->forFilters($this->pageFilters)['courses'])->keyBy('slug')->all())
            ->columns([
                TextColumn::make('name')->label('Parcours'),
                TextColumn::make('rides')->label('Sorties'),
                TextColumn::make('players')->label('Joueurs'),
                TextColumn::make('km')->label('Kilomètres')->formatStateUsing(fn (float $state): string => number_format($state, 0, '.', ' ')),
                TextColumn::make('elevation_gain_m')->label('Dénivelé (m)')->formatStateUsing(fn (int $state): string => number_format($state, 0, '.', ' ')),
                TextColumn::make('finished_share')->label('Terminées')
                    ->formatStateUsing(fn (?float $state): string => $state === null ? '–' : round($state * 100).' %'),
            ])
            ->emptyStateHeading('Aucune sortie sur cette période')
            ->paginated(false);
    }
}
