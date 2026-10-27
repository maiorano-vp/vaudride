<?php

declare(strict_types=1);

namespace App\Filament\Reports\Widgets;

use App\Actions\BuildActivityReport;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** When people ride: the rides of the period by day of the week (Swiss time). */
final class WeekdaysChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Sorties par jour de la semaine';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $days = app(BuildActivityReport::class)->forFilters($this->pageFilters)['weekdays'];

        return [
            'datasets' => [['label' => 'Sorties', 'data' => array_column($days, 'rides'), 'backgroundColor' => '#0a4069']],
            'labels' => array_column($days, 'label'),
        ];
    }
}
