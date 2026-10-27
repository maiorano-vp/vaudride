<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\ActivityReportCsv;
use App\Actions\BuildActivityReport;
use App\Filament\Reports\Widgets\ActivityOverTimeChart;
use App\Filament\Reports\Widgets\CoursesReportTable;
use App\Filament\Reports\Widgets\ReportStats;
use App\Filament\Reports\Widgets\SeasonalityChart;
use App\Filament\Reports\Widgets\WeekdaysChart;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Activity reports for Vaud Promotion over a chosen period, with CSV exports of the tables.
 * Anonymous aggregates only: nothing here names a player, and the app keeps no information on where they come from.
 */
final class Reports extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'reports';

    protected static ?string $title = 'Rapports';

    protected static ?string $navigationLabel = 'Rapports';

    protected static ?int $navigationSort = -1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public function getColumns(): int|array
    {
        return 2;
    }

    public function getWidgets(): array
    {
        return [ReportStats::class, ActivityOverTimeChart::class, CoursesReportTable::class, WeekdaysChart::class, SeasonalityChart::class];
    }

    public function filtersForm(Schema $schema): Schema
    {
        $custom = fn (Get $get): bool => $get('preset') === 'custom';

        return $schema->components([
            Section::make()->schema([
                Select::make('preset')->label('Période')->options(BuildActivityReport::PRESETS)->default('90d')
                    ->native(false)->selectablePlaceholder(false)->live(),
                DatePicker::make('from')->label('Du')->visible($custom)->maxDate(now())->live(),
                DatePicker::make('to')->label('Au')->visible($custom)->maxDate(now())->live(),
            ])->columns(3),
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCourses')->label('Exporter les parcours (CSV)')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(fn (): StreamedResponse => $this->download('parcours', fn (ActivityReportCsv $csv, array $report): string => $csv->courses($report))),
            Action::make('exportTimeline')->label('Exporter l’évolution (CSV)')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(fn (): StreamedResponse => $this->download('evolution', fn (ActivityReportCsv $csv, array $report): string => $csv->timeline($report))),
        ];
    }

    /**
     * @param  callable(ActivityReportCsv, array<string, mixed>): string  $render
     */
    private function download(string $name, callable $render): StreamedResponse
    {
        $report = app(BuildActivityReport::class)->forFilters($this->filters);
        $content = $render(app(ActivityReportCsv::class), $report);
        $file = sprintf('vaud-ride-%s-%s_%s.csv', $name, $report['from']->format('Y-m-d'), $report['to']->format('Y-m-d'));

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
