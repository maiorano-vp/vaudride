<?php

declare(strict_types=1);

namespace App\Actions;

/** The report tables as CSV for Excel in Switzerland: `;` separator, UTF-8 with a BOM so accents survive. */
final readonly class ActivityReportCsv
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function courses(array $report): string
    {
        $rows = array_map(fn (array $course): array => [
            $course['name'],
            $course['slug'],
            $course['rides'],
            $course['players'],
            $this->number($course['km']),
            $course['elevation_gain_m'],
            $course['finished_share'] === null ? '' : $this->number($course['finished_share'] * 100),
        ], $report['courses']);

        return $this->csv(['Parcours', 'Identifiant', 'Sorties', 'Joueurs', 'Kilomètres', 'Dénivelé (m)', 'Sorties terminées (%)'], $rows);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function timeline(array $report): string
    {
        $unit = $report['timeline']['unit'] === 'week' ? 'Semaine du' : 'Mois';
        $rows = array_map(
            fn (array $row): array => [$row['label'], $row['rides'], $row['players'], $row['new_players']],
            $report['timeline']['rows'],
        );

        return $this->csv([$unit, 'Sorties', 'Joueurs actifs', 'Nouveaux joueurs'], $rows);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function realWorld(array $report): string
    {
        $rows = array_map(fn (array $row): array => [
            $row['name'],
            $row['slug'],
            $row['rides'],
            $row['gpx'],
            $row['transit'],
            $row['map'],
            $row['places'],
            $row['declared'],
            $row['steps'],
        ], $report['real_world']['rows']);

        return $this->csv(['Parcours', 'Identifiant', 'Sorties virtuelles', 'GPX', 'Transports publics', 'Départ sur la carte', 'Liens vers des lieux', 'Déclarations', 'Total des pas vers le réel'], $rows);
    }

    /** Decimal comma, as the Swiss spreadsheet expects. */
    private function number(float|int $value): string
    {
        return str_replace('.', ',', (string) round($value, 1));
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<string|int>>  $rows
     */
    private function csv(array $header, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);

        foreach ([$header, ...$rows] as $row) {
            fputcsv($handle, $row, ';', '"', '');
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return "\u{FEFF}".$content;
    }
}
