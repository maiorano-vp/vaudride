<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Course;
use App\Models\Ride;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * The figures of the admin reports for a period: anonymous totals only, never a name. Admins' own rides do not count.
 * One instance serves a whole request (scoped binding), so the page's widgets and the CSV exports share one computation.
 */
final class BuildActivityReport
{
    public const string TIMEZONE = 'Europe/Zurich';

    public const array PRESETS = [
        '30d' => '30 derniers jours',
        '90d' => '90 derniers jours',
        '12m' => '12 derniers mois',
        'year' => 'Cette année',
        'all' => 'Depuis le début',
        'custom' => 'Dates personnalisées',
    ];

    public const array WEEKDAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

    public const array MONTHS = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

    /** A ride that stops within this distance of the end counts as finished, as for the ghosts. */
    private const int FINISH_TOLERANCE_M = 20;

    /** Longer periods are charted by month instead of by week. */
    private const int WEEKLY_UP_TO_DAYS = 190;

    private const int DEFAULT_DAYS = 90;

    /** @var array<string, array<string, mixed>> */
    private array $memo = [];

    /**
     * The period chosen in the page's filters, in Swiss time (a day is a Swiss day); the last 90 days by default.
     *
     * @param  array<string, mixed>|null  $filters  preset ('30d', '90d', '12m', 'year', 'all', 'custom') and, for 'custom', from / to dates
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function period(?array $filters): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $end = $now->endOfDay();
        $preset = (string) ($filters['preset'] ?? '90d');

        $period = match ($preset) {
            '30d' => [$now->subDays(29)->startOfDay(), $end],
            '12m' => [$now->subMonths(12)->addDay()->startOfDay(), $end],
            'year' => [$now->startOfYear(), $end],
            'all' => [$this->firstRideDay() ?? $now->subDays(self::DEFAULT_DAYS - 1)->startOfDay(), $end],
            'custom' => $this->customPeriod($filters['from'] ?? null, $filters['to'] ?? null) ?? [$now->subDays(self::DEFAULT_DAYS - 1)->startOfDay(), $end],
            default => [$now->subDays(self::DEFAULT_DAYS - 1)->startOfDay(), $end],
        };

        return $period;
    }

    /**
     * @return array{
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     *     totals: array{rides: int, active_players: int, new_players: int, returning_players: int, km: float, elevation_gain_m: int, duration_s: int, avg_duration_s: int, finished_share: float|null},
     *     courses: list<array{slug: string, name: string, rides: int, players: int, km: float, elevation_gain_m: int, finished_share: float|null}>,
     *     timeline: array{unit: string, rows: list<array{label: string, rides: int, players: int, new_players: int}>},
     *     weekdays: list<array{label: string, rides: int}>,
     *     months: list<array{label: string, rides: int}>
     * }
     */
    public function handle(CarbonInterface $from, CarbonInterface $to): array
    {
        $from = CarbonImmutable::instance($from)->setTimezone(self::TIMEZONE);
        $to = CarbonImmutable::instance($to)->setTimezone(self::TIMEZONE);

        return $this->memo[$from->toIso8601String().'/'.$to->toIso8601String()] ??= $this->build($from, $to);
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return array<string, mixed>
     */
    public function forFilters(?array $filters): array
    {
        return $this->handle(...$this->period($filters));
    }

    /**
     * @return array<string, mixed>
     */
    private function build(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $admins = User::query()->where('is_admin', true)->select('id');
        $rides = Ride::query()
            ->whereBetween('started_at', [$from, $to])
            ->whereNotIn('user_id', $admins)
            ->get(['user_id', 'course_id', 'started_at', 'duration_s', 'distance_m', 'elevation_gain_m']);
        $courses = Course::query()->get(['id', 'slug', 'name', 'distance_m'])->keyBy('id');
        $newPlayers = User::query()->where('is_admin', false)->whereBetween('created_at', [$from, $to])->pluck('created_at');

        $finished = fn (Ride $ride): bool => ($course = $courses->get($ride->course_id)) !== null && $ride->distance_m >= $course->distance_m - self::FINISH_TOLERANCE_M;
        $share = fn ($group): ?float => $group->isEmpty() ? null : round($group->filter($finished)->count() / $group->count(), 3);
        $perUser = $rides->groupBy('user_id');

        $durationS = (int) $rides->sum('duration_s');

        $courseRows = $rides->groupBy('course_id')
            ->map(fn ($group, $courseId): array => [
                'slug' => $courses->get($courseId)->slug ?? '?',
                'name' => $courses->get($courseId)->name['fr'] ?? '?',
                'rides' => $group->count(),
                'players' => $group->pluck('user_id')->unique()->count(),
                'km' => round($group->sum('distance_m') / 1000, 1),
                'elevation_gain_m' => (int) $group->sum('elevation_gain_m'),
                'finished_share' => $share($group),
            ])
            ->sortByDesc('rides')
            ->values()
            ->all();

        return [
            'from' => $from,
            'to' => $to,
            'totals' => [
                'rides' => $rides->count(),
                'active_players' => $perUser->count(),
                'new_players' => $newPlayers->count(),
                'returning_players' => $perUser->filter(fn ($group): bool => $group->count() >= 2)->count(),
                'km' => round($rides->sum('distance_m') / 1000, 1),
                'elevation_gain_m' => (int) $rides->sum('elevation_gain_m'),
                'duration_s' => $durationS,
                'avg_duration_s' => $rides->isEmpty() ? 0 : intdiv($durationS, $rides->count()),
                'finished_share' => $share($rides),
            ],
            'courses' => $courseRows,
            'timeline' => $this->timeline($from, $to, $rides, $newPlayers),
            'weekdays' => $this->weekdays($rides),
            'months' => $this->months($rides),
        ];
    }

    /**
     * Rides, active players and new players per week (or per month for a long period), every bucket present even when empty.
     *
     * @param  \Illuminate\Support\Collection<int, Ride>  $rides
     * @param  \Illuminate\Support\Collection<int, mixed>  $newPlayers  creation dates
     * @return array{unit: string, rows: list<array{label: string, rides: int, players: int, new_players: int}>}
     */
    private function timeline(CarbonImmutable $from, CarbonImmutable $to, $rides, $newPlayers): array
    {
        $weekly = $from->diffInDays($to) <= self::WEEKLY_UP_TO_DAYS;
        $bucket = fn (mixed $date): string => $this->local($date)->{$weekly ? 'startOfWeek' : 'startOfMonth'}()->toDateString();

        $ridesPer = $rides->groupBy(fn (Ride $ride): string => $bucket($ride->started_at));
        $newPer = $newPlayers->countBy(fn (mixed $date): string => $bucket($date));

        $rows = [];

        for ($cursor = $from->{$weekly ? 'startOfWeek' : 'startOfMonth'}(); $cursor <= $to; $cursor = $weekly ? $cursor->addWeek() : $cursor->addMonth()) {
            $group = $ridesPer->get($cursor->toDateString());
            $rows[] = [
                'label' => $cursor->format($weekly ? 'd.m' : 'm.Y'),
                'rides' => $group?->count() ?? 0,
                'players' => $group?->pluck('user_id')->unique()->count() ?? 0,
                'new_players' => (int) ($newPer[$cursor->toDateString()] ?? 0),
            ];
        }

        return ['unit' => $weekly ? 'week' : 'month', 'rows' => $rows];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Ride>  $rides
     * @return list<array{label: string, rides: int}>
     */
    private function weekdays($rides): array
    {
        $counts = $rides->countBy(fn (Ride $ride): int => $this->local($ride->started_at)->dayOfWeekIso);

        return array_map(fn (string $label, int $i): array => ['label' => $label, 'rides' => (int) ($counts[$i + 1] ?? 0)], self::WEEKDAYS, array_keys(self::WEEKDAYS));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Ride>  $rides
     * @return list<array{label: string, rides: int}>
     */
    private function months($rides): array
    {
        $counts = $rides->countBy(fn (Ride $ride): int => $this->local($ride->started_at)->month);

        return array_map(fn (string $label, int $i): array => ['label' => $label, 'rides' => (int) ($counts[$i + 1] ?? 0)], self::MONTHS, array_keys(self::MONTHS));
    }

    private function local(mixed $date): CarbonImmutable
    {
        return CarbonImmutable::instance($date)->setTimezone(self::TIMEZONE);
    }

    private function firstRideDay(): ?CarbonImmutable
    {
        $first = Ride::query()->min('started_at');

        return $first === null ? null : CarbonImmutable::parse($first, 'UTC')->setTimezone(self::TIMEZONE)->startOfDay();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function customPeriod(mixed $from, mixed $to): ?array
    {
        if (! is_string($from) || ! is_string($to) || $from === '' || $to === '') {
            return null;
        }

        try {
            $start = CarbonImmutable::parse($from, self::TIMEZONE)->startOfDay();
            $end = CarbonImmutable::parse($to, self::TIMEZONE)->endOfDay();
        } catch (Throwable) {
            return null;
        }

        return $start <= $end ? [$start, $end] : null;
    }
}
