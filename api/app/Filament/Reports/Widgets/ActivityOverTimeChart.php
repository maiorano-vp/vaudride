<?php

declare(strict_types=1);

namespace App\Filament\Reports\Widgets;

use App\Actions\BuildActivityReport;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Rides, active players and new players over the chosen period, by week or by month. */
final class ActivityOverTimeChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Évolution';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $rows = app(BuildActivityReport::class)->forFilters($this->pageFilters)['timeline']['rows'];

        return [
            'datasets' => [
                ['label' => 'Sorties', 'data' => array_column($rows, 'rides'), 'borderColor' => '#0a4069', 'backgroundColor' => '#0a4069'],
                ['label' => 'Joueurs actifs', 'data' => array_column($rows, 'players'), 'borderColor' => '#2f6b45', 'backgroundColor' => '#2f6b45'],
                ['label' => 'Nouveaux joueurs', 'data' => array_column($rows, 'new_players'), 'borderColor' => '#e8a317', 'backgroundColor' => '#e8a317'],
            ],
            'labels' => array_column($rows, 'label'),
        ];
    }
}
