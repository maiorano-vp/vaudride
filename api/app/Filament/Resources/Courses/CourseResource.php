<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses;

use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\Courses\RelationManagers\PoisRelationManager;
use App\Models\Course;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $modelLabel = 'parcours';

    protected static ?string $pluralModelLabel = 'parcours';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // the slug names the folder of the course package (profile.json): fixed once created
            TextInput::make('slug')->required()->unique(ignoreRecord: true)->alphaDash()->disabledOn('edit'),
            TextInput::make('name.fr')->label('Nom (FR)')->required(),
            TextInput::make('name.de')->label('Nom (DE)'),
            TextInput::make('name.en')->label('Nom (EN)'),
            TextInput::make('version')->numeric()->required()->default(1)->minValue(1)
                ->helperText('À incrémenter quand le profil.json change (invalide le cache du CDN).'),
            TextInput::make('distance_m')->label('Distance (m)')->numeric()->required()->minValue(1),
            TextInput::make('elevation_gain_m')->label('Dénivelé + (m)')->numeric()->required()->minValue(0),
            TextInput::make('attribution')->required()->default('© swisstopo'),
            Toggle::make('published')->label('Publié'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')->searchable()->sortable(),
                TextColumn::make('name.fr')->label('Nom')->searchable(),
                TextColumn::make('distance_m')->label('Distance')->formatStateUsing(fn (int $state): string => number_format($state / 1000, 1).' km')->sortable(),
                TextColumn::make('elevation_gain_m')->label('D+')->suffix(' m')->sortable(),
                IconColumn::make('published')->label('Publié')->boolean(),
            ])
            ->defaultSort('slug')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getRelations(): array
    {
        return [PoisRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourses::route('/'),
            'create' => CreateCourse::route('/create'),
            'edit' => EditCourse::route('/{record}/edit'),
        ];
    }
}
