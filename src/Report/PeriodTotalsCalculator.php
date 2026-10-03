<?php

declare(strict_types=1);

namespace App\Report;

use App\Entity\Branch;
use App\Enum\PeriodStep;
use App\Repository\ActivityRepository;
use App\Repository\StayRepository;

/**
 * Volunteers per calendar month or year, for /reports?tab=month. Shares
 * nothing with ActivitySummaryCalculator: it reads stays and activity dates,
 * not the per-activity breakdowns. Each total counts distinct volunteers:
 *
 * - present: a stay overlaps the period — it starts in it, ends in it, spans
 *   it, or both starts and ends in it;
 * - arrived: a stay starts in the period;
 * - engaged: an activity is dated in the period, planned ones included.
 *
 * A branch narrows all three to stays there, an activity going by its stay
 * (ADR 0026), so moving branch is an arrival at the new one. Periods are
 * Nairobi calendar ones (ADR 0024). Activities always lie within a stay, so
 * the stays alone set the range.
 *
 * @phpstan-type Totals array{present: int, arrived: int, engaged: int}
 * @phpstan-type PeriodRow array{period: \DateTimeImmutable, present: int, arrived: int, engaged: int, presentVsPrevious: ?int, presentVsLastYear: ?int, arrivedVsPrevious: ?int, arrivedVsLastYear: ?int, engagedVsPrevious: ?int, engagedVsLastYear: ?int}
 */
final class PeriodTotalsCalculator
{
    public function __construct(
        private readonly StayRepository $stays,
        private readonly ActivityRepository $activities,
    ) {}

    /**
     * Newest first, from the first stay's period to the last stay's, always
     * including the current one. Each total is compared with the period
     * before and with the same period a year before — the same thing by
     * year. A comparison reaching before the first period is null rather
     * than a jump from nothing.
     *
     * @return list<PeriodRow>
     */
    public function calculate(\DateTimeImmutable $today, ?Branch $branch = null, PeriodStep $step = PeriodStep::Month): array
    {
        $first = $last = $today;
        // Keyed by PeriodStep::key(); PHP turns a year key into an int.
        /** @var array<int|string, array<string, array<int, true>>> $seen period => total => volunteer ids */
        $seen = [];

        foreach ($this->stays->findBy(null === $branch ? [] : ['branch' => $branch]) as $stay) {
            $start = $stay->getStartDate();
            $end = $stay->getEndDate();
            if (null === $start || null === $end) {
                continue;
            }
            $volunteerId = (int) $stay->getVolunteer()?->getId();

            $seen[$step->key($start)]['arrived'][$volunteerId] = true;
            for ($period = $step->startOf($start); $period <= $end; $period = $step->next($period)) {
                $seen[$step->key($period)]['present'][$volunteerId] = true;
            }
            $first = min($first, $start);
            $last = max($last, $end);
        }

        foreach ($this->activities->findVolunteerDates($branch) as $activity) {
            $seen[$step->key(new \DateTimeImmutable($activity['date']))]['engaged'][(int) $activity['volunteerId']] = true;
        }

        /** @var array<int|string, Totals> $totals */
        $totals = [];
        /** @var array<int|string, \DateTimeImmutable> $starts */
        $starts = [];
        for ($period = $step->startOf($first); $period <= $last; $period = $step->next($period)) {
            $key = $step->key($period);
            $starts[$key] = $period;
            $totals[$key] = [
                'present' => \count($seen[$key]['present'] ?? []),
                'arrived' => \count($seen[$key]['arrived'] ?? []),
                'engaged' => \count($seen[$key]['engaged'] ?? []),
            ];
        }

        $rows = [];
        foreach ($totals as $key => $now) {
            $period = $starts[$key];
            $previous = $totals[$step->key($step->previous($period))] ?? null;
            $lastYear = $totals[$step->key($period->modify('-1 year'))] ?? null;

            $rows[] = [
                'period' => $period,
                'present' => $now['present'],
                'arrived' => $now['arrived'],
                'engaged' => $now['engaged'],
                'presentVsPrevious' => self::change($now, $previous, 'present'),
                'presentVsLastYear' => self::change($now, $lastYear, 'present'),
                'arrivedVsPrevious' => self::change($now, $previous, 'arrived'),
                'arrivedVsLastYear' => self::change($now, $lastYear, 'arrived'),
                'engagedVsPrevious' => self::change($now, $previous, 'engaged'),
                'engagedVsLastYear' => self::change($now, $lastYear, 'engaged'),
            ];
        }

        return array_reverse($rows);
    }

    /**
     * @param Totals                        $now
     * @param Totals|null                   $then
     * @param 'present'|'arrived'|'engaged' $total
     */
    private static function change(array $now, ?array $then, string $total): ?int
    {
        return null === $then ? null : $now[$total] - $then[$total];
    }
}
