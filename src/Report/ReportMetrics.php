<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The four headline figures at the top of /reports, plus the smaller
 * qualifiers printed under each one.
 */
final readonly class ReportMetrics
{
    public function __construct(
        public int $volunteerCount,
        public int $activeVolunteerCount,
        public int $projectCount,
        public int $activeProjectCount,
        public int $activityCount,
        /** Future-dated rows — logged, but not yet happened. */
        public int $plannedActivityCount,
        public float $totalDays,
        /**
         * Activities whose duration is `Other`, and so contribute nothing to
         * $totalDays. Surfaced rather than swallowed — see hasUncounted().
         */
        public int $uncountedDurationCount,
    ) {}

    /**
     * Whether the total-days figure is knowingly short. ActivityDuration::Other
     * carries a free-text duration that is never parsed into a day count
     * (ADR 0008), so those activities are real work that the total cannot
     * include. The tile says so instead of quietly under-reporting.
     */
    public function hasUncounted(): bool
    {
        return $this->uncountedDurationCount > 0;
    }
}
