<?php

declare(strict_types=1);

namespace App\Report;

use App\Repository\StayRepository;

/**
 * Finds the stays starting within the next week, so an arrival is expected
 * rather than noticed at the gate: each one is reminded every day of the
 * week before. Every stay start counts, including one that continues the
 * volunteer's previous stay — a branch change is an arrival at the new one.
 * Sorted by branch, then days away, for the dashboard's grouping.
 */
final class ArrivalReminderFinder
{
    public const int DAYS_AHEAD = 7;

    public function __construct(private readonly StayRepository $stays) {}

    /** @return list<ArrivalReminder> */
    public function find(\DateTimeImmutable $today): array
    {
        $today = $today->setTime(0, 0);
        $reminders = [];

        foreach ($this->stays->findStartingBetween($today->modify('+1 day'), $today->modify('+' . self::DAYS_AHEAD . ' days')) as $stay) {
            $volunteer = $stay->getVolunteer();
            $id = $volunteer?->getId();
            $start = $stay->getStartDate();
            $end = $stay->getEndDate();
            if (null === $volunteer || null === $id || null === $start || null === $end) {
                continue;
            }

            $reminders[] = new ArrivalReminder($id, $volunteer->getFullName(), $stay->getBranch()?->getName() ?? '', $today->diff($start)->days, $start, $end);
        }

        usort($reminders, static fn(ArrivalReminder $a, ArrivalReminder $b) => [$a->branchName, $a->daysAway, $a->fullName] <=> [$b->branchName, $b->daysAway, $b->fullName]);

        return $reminders;
    }
}
