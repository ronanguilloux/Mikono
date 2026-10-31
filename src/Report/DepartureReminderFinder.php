<?php

declare(strict_types=1);

namespace App\Report;

use App\Repository\StayRepository;

/**
 * Finds the stays about to end, so the VM has time to write each volunteer a
 * personal thank-you before they leave. A stay is reminded from WORKING_DAYS
 * working days before its last day through that day itself: one ending on a
 * Monday shows from the Thursday before. A stay the volunteer continues
 * straight into another (a branch move) is no departure and is left out.
 * Sorted by days away, then name.
 */
final readonly class DepartureReminderFinder
{
    public const int WORKING_DAYS = 2;

    public function __construct(private StayRepository $stays) {}

    /** @return list<DepartureReminder> */
    public function find(\DateTimeImmutable $today): array
    {
        $today = $today->setTime(0, 0);
        $reminders = [];

        // Two working days back from an end date is never more than four
        // calendar days (Monday → Thursday), so that is the widest window.
        foreach ($this->stays->findEndingBetween($today, $today->modify('+4 days')) as $stay) {
            $volunteer = $stay->getVolunteer();
            $volunteerId = $volunteer?->getId();
            $stayId = $stay->getId();
            $end = $stay->getEndDate();
            if (null === $volunteer || null === $volunteerId || null === $stayId || null === $end
                || $today < self::workingDaysBefore($end, self::WORKING_DAYS)
                || null !== $volunteer->getStayCovering($end->modify('+1 day'))) {
                continue;
            }

            $reminders[] = new DepartureReminder($volunteerId, $volunteer->getFirstName(), $stayId, $stay->getBranch()?->getName() ?? '', $today->diff($end)->days, $end);
        }

        usort($reminders, static fn(DepartureReminder $a, DepartureReminder $b) => [$a->daysAway, $a->firstName] <=> [$b->daysAway, $b->firstName]);

        return $reminders;
    }

    /**
     * The day $count working days (Monday to Friday) before $day.
     * ponytail: weekends only, not Kenya's public holidays; a reminder before
     * a holiday comes a day late. Add a holiday list here if that bites.
     */
    private static function workingDaysBefore(\DateTimeImmutable $day, int $count): \DateTimeImmutable
    {
        while ($count > 0) {
            $day = $day->modify('-1 day');
            if ((int) $day->format('N') <= 5) {
                --$count;
            }
        }

        return $day;
    }
}
