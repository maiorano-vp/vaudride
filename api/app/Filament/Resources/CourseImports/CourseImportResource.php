<?php

declare(strict_types=1);

namespace App\Filament\Resources\CourseImports;

use App\Actions\ExportCourseImport;
use App\Enums\ImportSource;
use App\Enums\LicenceStatus;
use App\Exceptions\InvalidGpxException;
use App\Filament\Resources\CourseImports\Pages\EditCourseImport;
use App\Filament\Resources\CourseImports\Pages\ListCourseImports;
use App\Models\CourseImport;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Routes waiting for review (Local Hub tours, uploaded GPX): set the rights and the slug, then export to courses/. */
final class CourseImportResource extends Resource
{
    protected static ?string $model = CourseImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $modelLabel = 'import';

    protected static ?string $pluralModelLabel = 'imports';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        // the gpx and preview columns are large and not needed to list or edit
        return parent::getEloquentQuery()->listing();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Titre')->required()->maxLength(255),
            TextInput::make('author')->label('Auteur')->maxLength(255),
            Select::make('licence_status')->label('Droits')->options(self::licenceOptions())->required(),
            TextInput::make('course_slug')->label('Slug')->maxLength(100)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/'),
            TextInput::make('trim_start_m')->label('Couper le début (m)')->numeric()->minValue(0),
            TextInput::make('trim_end_m')->label('Couper la fin à (m)')->numeric()->minValue(0)->gt('trim_start_m'),
            Toggle::make('reversed')->label('Inverser le sens'),
            TextInput::make('source_url')->label('Source')->disabled()->dehydrated(false)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Titre')->searchable()->limit(50),
                TextColumn::make('summary.distance_m')->label('Distance')->formatStateUsing(fn (mixed $state): string => number_format((float) $state / 1000, 1).' km'),
                TextColumn::make('summary.elevation_gain_m')->label('D+')->suffix(' m')->formatStateUsing(fn (mixed $state): string => (string) round((float) $state)),
                TextColumn::make('licence_status')->label('Droits')->badge()
                    ->formatStateUsing(fn (LicenceStatus $state): string => self::licenceOptions()[$state->value])
                    ->color(fn (LicenceStatus $state): string => $state === LicenceStatus::Unknown ? 'warning' : 'success'),
                TextColumn::make('course_slug')->label('Slug')->searchable(),
                TextColumn::make('exported_at')->label('Exporté')->dateTime('d.m.Y H:i')->placeholder('—')->sortable(),
            ])
            ->defaultSort('title')
            ->filters([
                SelectFilter::make('licence_status')->label('Droits')->options(self::licenceOptions()),
                SelectFilter::make('source')->label('Origine')->options(collect(ImportSource::cases())->mapWithKeys(fn (ImportSource $s): array => [$s->value => $s->value])->all()),
                TernaryFilter::make('exported')->label('Exporté')->queries(
                    true: fn (Builder $query): Builder => $query->whereNotNull('exported_at'),
                    false: fn (Builder $query): Builder => $query->whereNull('exported_at'),
                ),
            ])
            ->recordActions([
                Action::make('export')->label('Exporter')->icon(Heroicon::OutlinedArrowUpTray)
                    ->requiresConfirmation()
                    ->modalDescription('Écrit courses/<slug>/ pour le pipeline. Lancez ensuite pipeline/build_course.py <slug>.')
                    ->action(fn (CourseImport $record) => self::export($record)),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('licence')->label('Définir les droits')->icon(Heroicon::OutlinedScale)
                        ->schema([Select::make('licence_status')->label('Droits')->options(self::licenceOptions())->required()])
                        ->action(fn (Collection $records, array $data) => $records->each->update(['licence_status' => $data['licence_status']]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourseImports::route('/'),
            'edit' => EditCourseImport::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function licenceOptions(): array
    {
        return [
            LicenceStatus::Unknown->value => 'Inconnus',
            LicenceStatus::PermissionGranted->value => 'Autorisation obtenue',
            LicenceStatus::OpenLicence->value => 'Licence ouverte',
            LicenceStatus::OwnWork->value => 'Travail propre',
        ];
    }

    private static function export(CourseImport $listed): void
    {
        // the listing leaves the gpx out: the export needs the whole row
        $import = CourseImport::query()->findOrFail($listed->id);

        try {
            $directory = resolve(ExportCourseImport::class)->handle($import, config()->string('courses.source_path'));
        } catch (DomainException|InvalidGpxException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Exporté vers '.basename($directory))->body('Lancez: pipeline/build_course.py '.$import->course_slug)->success()->send();
    }
}
