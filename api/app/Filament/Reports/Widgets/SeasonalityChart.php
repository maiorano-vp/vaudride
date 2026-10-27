<?php

declare(strict_types=1);

namespace App\Filament\Reports\Widgets;

use App\Actions\BuildActivityReport;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** The seasons: the rides of the period by month of the year, to plan campaigns around the winter indoor season. */
final class SeasonalityChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Sorties par mois de l’année';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $months = app(BuildActivityReport::class)->forFilters($this->pageFilters)['months'];

        return [
            'datasets' => [['label' => 'Sorties', 'data' => array_column($months, 'rides'), 'backgroundColor' => '#2f6b45']],
            'labels' => array_column($months, 'label'),
        ];
    }
}
