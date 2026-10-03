<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The step /reports?tab=month counts volunteers by: one row per calendar
 * month or per calendar year, in Nairobi time (ADR 0024). Read from `?step=`.
 */
enum PeriodStep: string
{
    case Month = 'month';
    case Year = 'year';

    /** The period's key, which also sorts chronologically as a string. */
    public function key(\DateTimeImmutable $date): string
    {
        return $date->format(self::Month === $this ? 'Y-m' : 'Y');
    }

    public function startOf(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->modify(self::Month === $this ? 'first day of this month midnight' : 'first day of january this year midnight');
    }

    public function next(\DateTimeImmutable $periodStart): \DateTimeImmutable
    {
        return $periodStart->modify('+1 ' . $this->value);
    }

    public function previous(\DateTimeImmutable $periodStart): \DateTimeImmutable
    {
        return $periodStart->modify('-1 ' . $this->value);
    }

    /** How a period is shown: `Oct 2026`, or `2026`. */
    public function label(\DateTimeImmutable $periodStart): string
    {
        return $periodStart->format(self::Month === $this ? 'M Y' : 'Y');
    }
}
