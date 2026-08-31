<?php

declare(strict_types=1);

namespace App\Report;

use App\Repository\ProjectRepository;

/**
 * Finds the projects that have gone quiet, so the Volunteer Manager can put
 * arriving volunteers where they're most needed.
 *
 * This deliberately covers projects only, never volunteers. UCESCO's
 * volunteers come for a few weeks and then leave, often for good, so a
 * volunteer who has stopped appearing has usually finished rather than lapsed
 * — chasing them is not work worth surfacing, and a long list of people who
 * are never coming back would bury the signal that is actionable. A quiet
 * project always is: it's somewhere the next volunteer could be assigned.
 *
 * A project with nothing ever logged against it counts from the day it was
 * created rather than being skipped — that's the site most in need of
 * attention, not the one to hide.
 */
final class QuietProjectFinder
{
    public const int WARN_AFTER_DAYS = 30;
    public const int CRITICAL_AFTER_DAYS = 50;

    public function __construct(private readonly ProjectRepository $projects) {}

    /** @return list<QuietProject> */
    public function find(\DateTimeImmutable $today): array
    {
        $quiet = [];

        foreach ($this->projects->findActiveWithLastActivityDate() as $row) {
            $project = $row['project'];
            $id = $project->getId();
            // A project read back from the database always has an id.
            if (null === $id) {
                continue;
            }

            $lastActivity = $this->toDate($row['lastActivity']);
            $days = $this->daysBetween($lastActivity ?? $project->getCreatedAt(), $today);

            if ($days >= self::WARN_AFTER_DAYS) {
                $quiet[] = new QuietProject(
                    $id,
                    $project->getName(),
                    $days,
                    QuietProjectSeverity::forDays($days),
                    $lastActivity,
                );
            }
        }

        usort($quiet, static fn(QuietProject $a, QuietProject $b) => [$b->days, $a->name] <=> [$a->days, $b->name]);

        return $quiet;
    }

    /**
     * Whole days between two dates, counting anything dated today or later as
     * zero — a visit already planned for tomorrow means nothing is stale.
     */
    private function daysBetween(\DateTimeImmutable $since, \DateTimeImmutable $today): int
    {
        $diff = $since->setTime(0, 0)->diff($today->setTime(0, 0));

        return 1 === $diff->invert ? 0 : $diff->days;
    }

    private function toDate(\DateTimeImmutable|string|null $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        // MAX() bypasses Doctrine's type conversion, so the aggregate comes
        // back as the raw database scalar rather than a date object.
        return null === $value || '' === $value ? null : new \DateTimeImmutable($value);
    }
}
