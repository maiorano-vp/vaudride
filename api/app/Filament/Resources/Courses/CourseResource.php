<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses;

use App\Actions\StoreCourseProfile;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\Courses\RelationManagers\PoisRelationManager;
use App\Models\Course;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
            FileUpload::make('profile_file')->label('Profil (profile.json)')
                ->acceptedFileTypes(['application/json', 'text/plain'])
                ->storeFiles(false)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->helperText('Fichier produit par le pipeline. Le remplacer met à jour distance, dénivelé et version.'),
            // derived from the uploaded profile, shown for information
            TextInput::make('version')->hiddenOn('create')->disabled()->dehydrated(false),
            TextInput::make('distance_m')->label('Distance (m)')->hiddenOn('create')->disabled()->dehydrated(false),
            TextInput::make('elevation_gain_m')->label('Dénivelé + (m)')->hiddenOn('create')->disabled()->dehydrated(false),
            TextInput::make('attribution')->required()->default('© swisstopo'),
            Toggle::make('published')->label('Publié'),
        ]);
    }

    /**
     * Stores an uploaded profile.json and derives distance, climb and version from it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withProfile(array $data, ?Course $course = null): array
    {
        $file = $data['profile_file'] ?? null;
        unset($data['profile_file']);

        if (! $file instanceof TemporaryUploadedFile) {
            return $data;
        }

        $summary = resolve(StoreCourseProfile::class)->handle($course->slug ?? (string) $data['slug'], (string) $file->get());

        return [...$data, ...$summary, 'version' => ($course->version ?? 0) + 1];
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
