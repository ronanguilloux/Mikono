<?php

declare(strict_types=1);

namespace App\Report;

use App\Repository\VolunteerRepository;

/**
 * Finds the volunteers whose birthday is today, or exactly a week, three days
 * or one day away, so the team can send a message. Past volunteers are
 * included on purpose: recognising them is the point. The age is never
 * computed.
 *
 * A 29 February birthday falls on 28 February in a non-leap year.
 */
final class BirthdayReminderFinder
{
    /** @var list<int> */
    public const array DAYS_AHEAD = [0, 1, 3, 7];

    public function __construct(private readonly VolunteerRepository $volunteers) {}

    /** @return list<BirthdayReminder> */
    public function find(\DateTimeImmutable $today): array
    {
        $today = $today->setTime(0, 0);
        $reminders = [];

        foreach ($this->volunteers->findWithDateOfBirth() as $volunteer) {
            $id = $volunteer->getId();
            $dateOfBirth = $volunteer->getDateOfBirth();
            if (null === $id || null === $dateOfBirth) {
                continue;
            }

            $next = $this->birthdayIn((int) $today->format('Y'), $dateOfBirth);
            if ($next < $today) {
                $next = $this->birthdayIn((int) $today->format('Y') + 1, $dateOfBirth);
            }

            $daysAway = $today->diff($next)->days;
            if (\in_array($daysAway, self::DAYS_AHEAD, true)) {
                $reminders[] = new BirthdayReminder($id, $volunteer->getFirstName(), $volunteer->getFullName(), $daysAway, $next);
            }
        }

        usort($reminders, static fn(BirthdayReminder $a, BirthdayReminder $b) => [$a->daysAway, $a->fullName] <=> [$b->daysAway, $b->fullName]);

        return $reminders;
    }

    private function birthdayIn(int $year, \DateTimeImmutable $dateOfBirth): \DateTimeImmutable
    {
        $month = (int) $dateOfBirth->format('n');
        $day = (int) $dateOfBirth->format('j');
        if (2 === $month && 29 === $day && !checkdate(2, 29, $year)) {
            $day = 28;
        }

        return $dateOfBirth->setDate($year, $month, $day)->setTime(0, 0);
    }
}
