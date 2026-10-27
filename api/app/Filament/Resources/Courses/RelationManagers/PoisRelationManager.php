<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\RelationManagers;

use App\Actions\ImportLocalhubPois;
use App\Actions\ImportOsmPois;
use App\Actions\ImportTerroirPois;
use App\Enums\PoiCategory;
use App\Models\Course;
use App\Services\TerroirClient;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

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
            TextInput::make('image_url')->label('Image (URL)')->url(),
            TextInput::make('image_credit')->label('Crédit de l’image'),
            Select::make('category')->label('Catégorie')
                ->options(collect(PoiCategory::cases())->mapWithKeys(fn (PoiCategory $c): array => [$c->value => $c->label()]))
                ->placeholder('Sans catégorie'),
            Toggle::make('published')->label('Publié')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')->label('Image')->square()->size(40),
                TextColumn::make('distance_m')->label('Distance')->suffix(' m')->sortable(),
                TextColumn::make('title.fr')->label('Titre')->searchable(),
                TextColumn::make('category')->label('Catégorie')->formatStateUsing(fn (?PoiCategory $state): string => $state?->label() ?? '—'),
                TextColumn::make('source')->label('Origine')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'osm' => 'OpenStreetMap',
                    'localhub' => 'Local Hub',
                    'terroir' => 'Terroir Vaud',
                    default => 'Vaud Promotion',
                }),
                IconColumn::make('published')->label('Publié')->boolean(),
            ])
            ->defaultSort('distance_m')
            ->headerActions([
                Action::make('importOsm')->label('Importer depuis OpenStreetMap')->icon(Heroicon::OutlinedGlobeAlt)
                    ->requiresConfirmation()
                    ->modalDescription('Les lieux trouvés à moins de 120 m du tracé sont ajoutés non publiés, à relire. Données © OpenStreetMap contributors.')
                    ->action(function (): void {
                        /** @var Course $course */
                        $course = $this->getOwnerRecord();

                        try {
                            $created = resolve(ImportOsmPois::class)->handle($course);
                        } catch (Throwable) {
                            Notification::make()->title('OpenStreetMap ne répond pas, réessayez dans quelques minutes.')->danger()->send();

                            return;
                        }

                        if ($created === null) {
                            Notification::make()->title('Ce parcours n’a pas encore de profil.')->warning()->send();

                            return;
                        }

                        Notification::make()->title($created.' lieu(x) importé(s), à relire avant publication.')->success()->send();
                    }),
                Action::make('importLocalhub')->label('Importer depuis Local Hub')->icon(Heroicon::OutlinedCloudArrowDown)
                    ->requiresConfirmation()
                    ->modalDescription('Depuis la copie locale du catalogue Local Hub (mise à jour par « php artisan localhub:sync »): les lieux à moins de 300 m du tracé et les hébergements à moins de 3 km du départ ou de l’arrivée sont ajoutés et publiés tout de suite (vous pouvez ensuite en masquer dans la liste).')
                    ->action(function (): void {
                        /** @var Course $course */
                        $course = $this->getOwnerRecord();

                        $result = resolve(ImportLocalhubPois::class)->handle($course);

                        if ($result === null) {
                            Notification::make()->title('Rien à importer: le parcours n’a pas de profil, ou le catalogue Local Hub n’a jamais été synchronisé (php artisan localhub:sync).')->warning()->send();

                            return;
                        }

                        Notification::make()->title($result['created'].' lieu(x) importé(s), '.$result['skipped'].' ignoré(s). Publiés: masquez ceux que vous ne voulez pas.')->success()->send();
                    }),
                Action::make('importTerroir')->label('Importer les partenaires Terroir')->icon(Heroicon::OutlinedShoppingBag)
                    ->requiresConfirmation()
                    ->modalDescription('Les partenaires Terroir (agritourisme, boulangeries, épiceries) à moins de 300 m du tracé sont ajoutés non publiés, à relire avant publication.')
                    ->action(function (): void {
                        /** @var Course $course */
                        $course = $this->getOwnerRecord();
                        $import = resolve(ImportTerroirPois::class);

                        if (! resolve(TerroirClient::class)->configured()) {
                            Notification::make()->title('TERROIR_TOKEN n’est pas configuré sur ce serveur.')->warning()->send();

                            return;
                        }

                        try {
                            $result = $import->handle($course, $import->partners());
                        } catch (Throwable) {
                            Notification::make()->title('L’API Terroir ne répond pas, réessayez dans quelques minutes.')->danger()->send();

                            return;
                        }

                        if ($result === null) {
                            Notification::make()->title('Ce parcours n’a pas encore de profil.')->warning()->send();

                            return;
                        }

                        Notification::make()->title($result['created'].' lieu(x) importé(s), '.$result['skipped'].' déjà présent(s). À relire avant publication.')->success()->send();
                    }),
                CreateAction::make(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
