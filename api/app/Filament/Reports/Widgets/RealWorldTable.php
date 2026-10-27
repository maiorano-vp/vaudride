<?php

declare(strict_types=1);

namespace App\Filament\Reports\Widgets;

use App\Actions\BuildActivityReport;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/** From virtual to real: for each course, the anonymous steps visitors took towards riding it for real. */
final class RealWorldTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Du virtuel au réel';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => collect(app(BuildActivityReport::class)->forFilters($this->pageFilters)['real_world']['rows'])->keyBy('slug')->all())
            ->columns([
                TextColumn::make('name')->label('Parcours'),
                TextColumn::make('rides')->label('Sorties virtuelles'),
                TextColumn::make('gpx')->label('GPX'),
                TextColumn::make('transit')->label('Transports publics'),
                TextColumn::make('map')->label('Départ sur la carte'),
                TextColumn::make('places')->label('Liens vers des lieux'),
                TextColumn::make('declared')->label('Déclarations'),
                TextColumn::make('steps')->label('Total')->weight('bold'),
            ])
            ->description('Totaux anonymes : ni nom, ni appareil, ni adresse. « Déclarations » : cyclistes ayant dit avoir roulé le parcours.')
            ->emptyStateHeading('Aucune activité sur cette période')
            ->paginated(false);
    }
}
