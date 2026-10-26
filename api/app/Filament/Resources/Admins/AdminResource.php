<?php

declare(strict_types=1);

namespace App\Filament\Resources\Admins;

use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\Admins\Pages\EditAdmin;
use App\Filament\Resources\Admins\Pages\ListAdmins;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/** The staff who can sign in to this panel (users flagged `is_admin`). Players live in the "Joueurs" resource. */
final class AdminResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'admins';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $modelLabel = 'administrateur';

    protected static ?string $pluralModelLabel = 'administrateurs';

    protected static ?int $navigationSort = 99;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_admin', true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(255),
            TextInput::make('email')->label('E-mail')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
            TextInput::make('password')->label('Mot de passe')->password()->revealable()->minLength(12)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Laisser vide pour ne pas le changer.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('email')->label('E-mail')->searchable(),
                IconColumn::make('app_authentication_secret')->label('2FA')->boolean()
                    ->state(fn (User $record): bool => filled($record->getAppAuthenticationSecret())),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                Action::make('reset2fa')->label('Réinitialiser le 2FA')->icon(Heroicon::OutlinedKey)->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('L’administrateur devra configurer à nouveau son application d’authentification à sa prochaine connexion.')
                    ->visible(fn (User $record): bool => filled($record->getAppAuthenticationSecret()))
                    ->action(function (User $record): void {
                        $record->saveAppAuthenticationSecret(null);
                        $record->saveAppAuthenticationRecoveryCodes(null);
                    }),
                // never lock yourself out
                DeleteAction::make()->hidden(fn (User $record): bool => $record->is(Auth::user())),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmins::route('/'),
            'create' => CreateAdmin::route('/create'),
            'edit' => EditAdmin::route('/{record}/edit'),
        ];
    }
}
