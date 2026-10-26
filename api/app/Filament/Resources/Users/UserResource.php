<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\RidesRelationManager;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only view of the players: name and what they ride. Players' e-mail addresses are deliberately
 * never shown, searched or exposed here.
 */
final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'joueur';

    protected static ?string $pluralModelLabel = 'joueurs';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select(['id', 'name', 'created_at'])
            ->withCount('rides')
            ->withSum('rides', 'distance_m')
            ->withSum('rides', 'elevation_gain_m')
            ->withMax('rides', 'started_at');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label('Nom'),
            TextEntry::make('created_at')->label('Inscrit le')->dateTime('d.m.Y'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('rides_count')->label('Sorties')->sortable(),
                TextColumn::make('rides_sum_distance_m')->label('Distance')
                    ->formatStateUsing(fn (?int $state): string => number_format(($state ?? 0) / 1000, 1).' km')->sortable(),
                TextColumn::make('rides_sum_elevation_gain_m')->label('D+')->suffix(' m')->default(0)->sortable(),
                TextColumn::make('rides_max_started_at')->label('Dernière sortie')->dateTime('d.m.Y H:i')->placeholder('—')->sortable(),
            ])
            ->defaultSort('rides_max_started_at', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [RidesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
