<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Ride;
use Filament\Widgets\ChartWidget;

final class RidesPerWeekChart extends ChartWidget
{
    private const int WEEKS = 12;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Sorties par semaine';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $first = now()->startOfWeek()->subWeeks(self::WEEKS - 1);

        // grouped here, not in SQL, to behave the same on SQLite and MariaDB; plenty for a few thousand rides
        $perWeek = Ride::query()->where('started_at', '>=', $first)->pluck('started_at')
            ->countBy(fn ($date): string => $date->copy()->startOfWeek()->toDateString());

        $labels = [];
        $counts = [];

        for ($i = 0; $i < self::WEEKS; $i++) {
            $week = $first->copy()->addWeeks($i);
            $labels[] = $week->format('d.m');
            $counts[] = $perWeek[$week->toDateString()] ?? 0;
        }

        return [
            'datasets' => [['label' => 'Sorties', 'data' => $counts, 'backgroundColor' => '#0a4069']],
            'labels' => $labels,
        ];
    }
}
