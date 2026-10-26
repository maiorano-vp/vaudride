<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Segment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** The segments riders go for the most. */
final class PopularSegmentsTable extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Segments les plus roulés';

    public function table(Table $table): Table
    {
        return $table
            ->query(Segment::query()->with('course:id,slug')->withCount('efforts')->where('published', true))
            ->columns([
                TextColumn::make('name.fr')->label('Segment'),
                TextColumn::make('course.slug')->label('Parcours'),
                TextColumn::make('efforts_count')->label('Passages')->sortable(),
            ])
            ->defaultSort('efforts_count', 'desc')
            ->paginated([10]);
    }
}
