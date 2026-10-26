<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Course;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** How much each course is ridden. */
final class CourseActivityTable extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Parcours les plus roulés';

    public function table(Table $table): Table
    {
        return $table
            ->query(Course::query()->withCount('rides')->withSum('rides', 'distance_m')->withCount('pois'))
            ->columns([
                TextColumn::make('name.fr')->label('Parcours'),
                TextColumn::make('rides_count')->label('Sorties')->sortable(),
                TextColumn::make('rides_sum_distance_m')->label('Kilomètres')
                    ->formatStateUsing(fn (?int $state): string => number_format(($state ?? 0) / 1000, 0, '.', ' '))->sortable(),
                TextColumn::make('pois_count')->label('Lieux à découvrir')->sortable(),
            ])
            ->defaultSort('rides_count', 'desc')
            ->paginated(false);
    }
}
