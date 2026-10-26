<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Ride;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** The headline numbers: anonymous totals only, never a name. */
final class ActivityStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $month = now()->subDays(30);

        return [
            Stat::make('Joueurs', number_format(User::query()->where('is_admin', false)->count(), 0, '.', ' ')),
            Stat::make('Actifs (30 jours)', number_format(Ride::query()->where('started_at', '>=', $month)->distinct()->count('user_id'), 0, '.', ' ')),
            Stat::make('Sorties', number_format(Ride::query()->count(), 0, '.', ' '))
                ->description(number_format(Ride::query()->where('started_at', '>=', $month)->count(), 0, '.', ' ').' sur 30 jours'),
            Stat::make('Kilomètres roulés', number_format((int) round(Ride::query()->sum('distance_m') / 1000), 0, '.', ' ')),
            Stat::make('Dénivelé cumulé', number_format((int) Ride::query()->sum('elevation_gain_m'), 0, '.', ' ').' m'),
        ];
    }
}
