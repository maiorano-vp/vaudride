<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\FindMissingTranslations;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** How much of what is published is translated, for the German- and English-speaking visitors. */
final class TranslationStats extends StatsOverviewWidget
{
    /** @var array<string, string> */
    private const array NAMES = ['de' => 'Allemand', 'en' => 'Anglais'];

    protected static ?int $sort = 5;

    protected ?string $heading = 'Traductions';

    protected function getStats(): array
    {
        $locales = app(FindMissingTranslations::class)->handle()['locales'];

        return array_map(function (string $locale) use ($locales): Stat {
            ['total' => $total, 'missing' => $missing] = $locales[$locale];
            $done = $total === 0 ? 100 : (int) floor(($total - $missing) / $total * 100);

            return Stat::make(self::NAMES[$locale], $done.' %')
                ->description($missing === 0 ? 'Tout est traduit' : $missing.' textes encore en français')
                ->color($missing === 0 ? 'success' : 'warning');
        }, FindMissingTranslations::LOCALES);
    }
}
