<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** What a player rode: one line per ride, without the recorded samples. */
final class RidesRelationManager extends RelationManager
{
    protected static string $relationship = 'rides';

    protected static ?string $title = 'Sorties';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->summary()->with('course'))
            ->columns([
                TextColumn::make('started_at')->label('Date')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('course.name.fr')->label('Parcours'),
                TextColumn::make('distance_m')->label('Distance')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 1).' km')->sortable(),
                TextColumn::make('elevation_gain_m')->label('D+')->suffix(' m')->sortable(),
                TextColumn::make('duration_s')->label('Durée')
                    ->formatStateUsing(fn (int $state): string => gmdate('G:i:s', $state))->sortable(),
                TextColumn::make('avg_power_w')->label('Puissance moy.')->suffix(' W')->placeholder('—'),
                TextColumn::make('avg_heart_rate')->label('FC moy.')->suffix(' bpm')->placeholder('—'),
            ])
            ->defaultSort('started_at', 'desc');
    }
}
