<?php

declare(strict_types=1);

namespace App\Filament\Reports\Widgets;

use App\Actions\BuildActivityReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** The headline numbers of the chosen period: anonymous totals only, never a name. */
final class ReportStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        $totals = app(BuildActivityReport::class)->forFilters($this->pageFilters)['totals'];
        $int = fn (int|float $n): string => number_format($n, 0, '.', ' ');
        $minutes = fn (int $s): string => $int(round($s / 60)).' min';

        return [
            Stat::make('Sorties', $int($totals['rides'])),
            Stat::make('Joueurs actifs', $int($totals['active_players']))
                ->description($int($totals['returning_players']).' avec au moins 2 sorties'),
            Stat::make('Nouveaux joueurs', $int($totals['new_players'])),
            Stat::make('Kilomètres roulés', $int($totals['km'])),
            Stat::make('Dénivelé cumulé', $int($totals['elevation_gain_m']).' m'),
            Stat::make('Durée moyenne d’une sortie', $minutes($totals['avg_duration_s']))
                ->description($int(round($totals['duration_s'] / 3600)).' h au total'),
            Stat::make('Sorties terminées', $totals['finished_share'] === null ? '–' : $int(round($totals['finished_share'] * 100)).' %')
                ->description('parcours roulé jusqu’à l’arrivée'),
        ];
    }
}
