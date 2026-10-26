<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\RelationManagers;

use App\Actions\DetectCourseSegments;
use App\Enums\ClimbCategory;
use App\Models\Course;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** The timed stretches of a course (mostly climbs), proposed from its profile and reviewed here. */
final class SegmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'segments';

    protected static ?string $title = 'Segments';

    public function form(Schema $schema): Schema
    {
        /** @var Course $course */
        $course = $this->getOwnerRecord();

        return $schema->components([
            TextInput::make('name.fr')->label('Nom (FR)')->required(),
            TextInput::make('name.de')->label('Nom (DE)'),
            TextInput::make('name.en')->label('Nom (EN)'),
            TextInput::make('start_m')->label('Début (m)')->numeric()->required()->minValue(0)->maxValue($course->distance_m),
            TextInput::make('end_m')->label('Fin (m)')->numeric()->required()->maxValue($course->distance_m)->gt('start_m'),
            Select::make('category')->label('Catégorie')
                ->options(collect(ClimbCategory::cases())->mapWithKeys(fn (ClimbCategory $c): array => [$c->value => $c->label()]))
                ->placeholder('Sans catégorie'),
            Toggle::make('published')->label('Publié')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name.fr')->label('Nom')->searchable(),
                TextColumn::make('start_m')->label('Début')->suffix(' m')->sortable(),
                TextColumn::make('end_m')->label('Fin')->suffix(' m'),
                TextColumn::make('length')->label('Longueur')
                    ->state(fn ($record): string => number_format(($record->end_m - $record->start_m) / 1000, 2).' km'),
                TextColumn::make('category')->label('Catégorie')->formatStateUsing(fn (?ClimbCategory $state): string => $state?->label() ?? '—'),
                IconColumn::make('published')->label('Publié')->boolean(),
            ])
            ->defaultSort('start_m')
            ->headerActions([
                Action::make('detect')->label('Détecter les montées')->icon(Heroicon::OutlinedSparkles)
                    ->action(function (): void {
                        /** @var Course $course */
                        $course = $this->getOwnerRecord();
                        $created = resolve(DetectCourseSegments::class)->handle($course);

                        if ($created === null) {
                            Notification::make()->title('Ce parcours n’a pas encore de profil.')->warning()->send();

                            return;
                        }

                        Notification::make()->title($created.' montée(s) proposée(s), à relire avant publication.')->success()->send();
                    }),
                CreateAction::make(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
