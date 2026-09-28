<?php

declare(strict_types=1);

namespace App\Report;

use App\Repository\StayRepository;

/**
 * Finds the stays starting exactly a week, three days or one day from today,
 * so an arrival is expected rather than noticed at the gate. A stay that
 * starts the day after the same volunteer's previous stay ends is a
 * continuation, not an arrival, and is left out.
 */
final class ArrivalReminderFinder
{
    /** @var list<int> */
    public const array DAYS_AHEAD = [1, 3, 7];

    public function __construct(private readonly StayRepository $stays) {}

    /** @return list<ArrivalReminder> */
    public function find(\DateTimeImmutable $today): array
    {
        $today = $today->setTime(0, 0);
        $reminders = [];

        foreach ($this->stays->findStartingBetween($today->modify('+1 day'), $today->modify('+' . max(self::DAYS_AHEAD) . ' days')) as $stay) {
            $volunteer = $stay->getVolunteer();
            $id = $volunteer?->getId();
            $start = $stay->getStartDate();
            $end = $stay->getEndDate();
            if (null === $volunteer || null === $id || null === $start || null === $end) {
                continue;
            }

            $daysAway = $today->diff($start)->days;
            if (!\in_array($daysAway, self::DAYS_AHEAD, true)) {
                continue;
            }

            $dayBefore = $start->modify('-1 day')->format('Y-m-d');
            foreach ($volunteer->getStays() as $other) {
                if ($dayBefore === $other->getEndDate()?->format('Y-m-d')) {
                    continue 2;
                }
            }

            $reminders[] = new ArrivalReminder($id, $volunteer->getFullName(), $stay->getBranch()?->getName() ?? '', $daysAway, $start, $end);
        }

        usort($reminders, static fn(ArrivalReminder $a, ArrivalReminder $b) => [$a->daysAway, $a->fullName] <=> [$b->daysAway, $b->fullName]);

        return $reminders;
    }
}
