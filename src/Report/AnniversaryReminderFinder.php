<?php

declare(strict_types=1);

namespace App\Report;

use App\Entity\Achievement;
use App\Repository\AchievementRepository;

/**
 * Finds the achievements whose anniversary — one or more whole years on — is
 * today or tomorrow, as a reason to get back in touch with the volunteer.
 * Month/day matching is done in PHP, like BirthdayReminderFinder.
 *
 * A 29 February achievement falls on 28 February in a non-leap year.
 */
final class AnniversaryReminderFinder
{
    /** @var list<int> */
    public const array DAYS_AHEAD = [0, 1];

    public function __construct(private readonly AchievementRepository $achievements) {}

    /** @return list<AnniversaryReminder> */
    public function find(\DateTimeImmutable $today): array
    {
        $today = $today->setTime(0, 0);
        $reminders = [];

        // ponytail: loads every achievement; fine for hundreds, add a month/day column if it grows.
        /** @var list<Achievement> $achievements */
        $achievements = $this->achievements->createNewestFirstQueryBuilder()->getQuery()->getResult();

        foreach ($achievements as $achievement) {
            $achievedOn = $achievement->getAchievedOn();
            $stay = $achievement->getStay();
            $volunteer = $stay?->getVolunteer();
            $volunteerId = $volunteer?->getId();
            $project = $achievement->getProject();
            $projectId = $project?->getId();
            if (null === $achievedOn || null === $volunteer || null === $volunteerId || null === $project || null === $projectId) {
                continue;
            }

            foreach (self::DAYS_AHEAD as $daysAway) {
                $day = $today->modify(sprintf('+%d days', $daysAway));
                $year = (int) $day->format('Y');
                $years = $year - (int) $achievedOn->format('Y');
                if ($years >= 1 && $this->anniversaryIn($year, $achievedOn)->format('Y-m-d') === $day->format('Y-m-d')) {
                    $reminders[] = new AnniversaryReminder(
                        $volunteerId,
                        $volunteer->getFirstName(),
                        $achievement->getTitle(),
                        $projectId,
                        $project->getName(),
                        $stay->getBranch()?->getName() ?? '',
                        $years,
                        $daysAway,
                    );
                }
            }
        }

        usort($reminders, static fn(AnniversaryReminder $a, AnniversaryReminder $b) => [$a->daysAway, $a->firstName, $a->title] <=> [$b->daysAway, $b->firstName, $b->title]);

        return $reminders;
    }

    private function anniversaryIn(int $year, \DateTimeImmutable $achievedOn): \DateTimeImmutable
    {
        $month = (int) $achievedOn->format('n');
        $day = (int) $achievedOn->format('j');
        if (2 === $month && 29 === $day && !checkdate(2, 29, $year)) {
            $day = 28;
        }

        return $achievedOn->setDate($year, $month, $day)->setTime(0, 0);
    }
}
