<?php

declare(strict_types=1);

namespace App\Report;

use App\Repository\AchievementRepository;

/**
 * What achievements counted (ADR 0047), per indicator and calendar month,
 * over the twelve months ending with the current one — for the charts on
 * /reports/achievements. Indicators are never added together: 40 meals and
 * 12 children make no total. Months are Nairobi calendar ones (ADR 0024).
 *
 * @phpstan-type IndicatorMonths array{indicator: string, total: int, months: list<array{month: \DateTimeImmutable, total: int}>}
 */
final readonly class ImpactByMonthCalculator
{
    public const int MONTHS = 12;

    public function __construct(
        private AchievementRepository $achievements,
    ) {}

    /**
     * Only indicators counted in the window, the largest total first. Every
     * one carries all twelve months, oldest first, zeros included, so their
     * charts line up.
     *
     * @return list<IndicatorMonths>
     */
    public function calculate(\DateTimeImmutable $today): array
    {
        $first = $today->modify('first day of this month')->setTime(0, 0)->modify(sprintf('-%d months', self::MONTHS - 1));
        $last = $today->modify('last day of this month')->setTime(0, 0);

        // An indicator named "100" becomes an int key, hence the cast below.
        /** @var array<int|string, array<string, int>> $totals indicator => 'Y-m' => total */
        $totals = [];
        foreach ($this->achievements->findCountedBetween($first, $last) as $achievement) {
            $indicator = $achievement->getIndicator()?->getName();
            $day = $achievement->getAchievedOn();
            if (null === $indicator || null === $day) {
                continue;
            }
            $month = $day->format('Y-m');
            $totals[$indicator][$month] = ($totals[$indicator][$month] ?? 0) + (int) $achievement->getQuantity();
        }

        $result = [];
        foreach ($totals as $indicator => $byMonth) {
            $months = [];
            for ($i = 0; $i < self::MONTHS; ++$i) {
                $month = $first->modify(sprintf('+%d months', $i));
                $months[] = ['month' => $month, 'total' => $byMonth[$month->format('Y-m')] ?? 0];
            }
            $result[] = ['indicator' => (string) $indicator, 'total' => array_sum($byMonth), 'months' => $months];
        }
        usort($result, static fn(array $a, array $b) => [$b['total'], $a['indicator']] <=> [$a['total'], $b['indicator']]);

        return $result;
    }
}
