<?php

declare(strict_types=1);

namespace App\Filament\Resources\SegmentEfforts;

use App\Filament\Resources\SegmentEfforts\Pages\ListSegmentEfforts;
use App\Models\SegmentEffort;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Times set on segments. Read-only except for removal: a suspicious time can be deleted from the leaderboard. */
final class SegmentEffortResource extends Resource
{
    protected static ?string $model = SegmentEffort::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $modelLabel = 'temps de segment';

    protected static ?string $pluralModelLabel = 'temps de segments';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['segment.course', 'user:id,nickname']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('segment.course.slug')->label('Parcours')->searchable(),
                TextColumn::make('segment.name.fr')->label('Segment'),
                TextColumn::make('user.nickname')->label('Joueur')->searchable(),
                TextColumn::make('time_s')->label('Temps')
                    ->formatStateUsing(fn (float $state): string => sprintf('%d:%05.2f', intdiv((int) $state, 60), fmod($state, 60)))->sortable(),
                TextColumn::make('avg_power_w')->label('Puissance moy.')->suffix(' W')->placeholder('—'),
                TextColumn::make('started_at')->label('Date')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([SelectFilter::make('segment_id')->label('Segment')->relationship('segment', 'id')->getOptionLabelFromRecordUsing(fn ($record): string => $record->name['fr'])])
            ->recordActions([DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSegmentEfforts::route('/')];
    }
}
