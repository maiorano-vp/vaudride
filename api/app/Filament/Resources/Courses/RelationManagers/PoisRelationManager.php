<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Tourist points of interest along the course (Vaud Promotion offer). */
final class PoisRelationManager extends RelationManager
{
    protected static string $relationship = 'pois';

    protected static ?string $title = 'Points d’intérêt';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title.fr')->label('Titre (FR)')->required(),
            TextInput::make('title.de')->label('Titre (DE)'),
            TextInput::make('title.en')->label('Titre (EN)'),
            Textarea::make('description.fr')->label('Description (FR)'),
            Textarea::make('description.de')->label('Description (DE)'),
            Textarea::make('description.en')->label('Description (EN)'),
            TextInput::make('distance_m')->label('Distance sur le parcours (m)')->numeric()->required()->minValue(0),
            TextInput::make('lat')->numeric()->required()->minValue(-90)->maxValue(90),
            TextInput::make('lon')->numeric()->required()->minValue(-180)->maxValue(180),
            TextInput::make('url')->url(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('distance_m')->label('Distance')->suffix(' m')->sortable(),
                TextColumn::make('title.fr')->label('Titre')->searchable(),
            ])
            ->defaultSort('distance_m')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
