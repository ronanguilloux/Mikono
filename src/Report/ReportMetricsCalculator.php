<?php

declare(strict_types=1);

namespace App\Report;

use App\Enum\ActivityDuration;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\VolunteerRepository;

/**
 * Builds the KPI tiles at the top of /reports.
 *
 * Everything is counted in PHP from the finders the app already has, rather
 * than through new COUNT() queries: ActivitySummaryCalculator already walks
 * every activity to produce the tables below the tiles, this app serves one
 * Volunteer Manager over hundreds of rows, and a dedicated repository method
 * per figure would be more code to keep mutation-tested for no gain.
 */
final class ReportMetricsCalculator
{
    public function __construct(
        private readonly VolunteerRepository $volunteers,
        private readonly ProjectRepository $projects,
        private readonly ActivityRepository $activities,
    ) {}

    public function calculate(\DateTimeImmutable $today): ReportMetrics
    {
        $volunteers = $this->volunteers->findAllOrderedByName();
        $projects = $this->projects->findAllOrderedByName();

        $activityCount = 0;
        $plannedCount = 0;
        $totalDays = 0.0;
        $uncounted = 0;

        foreach ($this->activities->findAllOrderedByDateDesc() as $activity) {
            ++$activityCount;

            $date = $activity->getDate();
            // Same rule as the Activities index cards: dated after today means
            // planned rather than done, so "planned" reads the same app-wide.
            if (null !== $date && $date > $today) {
                ++$plannedCount;
            }

            $duration = $activity->getDuration();
            $totalDays += $duration?->toDays() ?? 0.0;

            if (ActivityDuration::Other === $duration) {
                ++$uncounted;
            }
        }

        return new ReportMetrics(
            \count($volunteers),
            \count(array_filter($volunteers, static fn($volunteer) => $volunteer->isActive())),
            \count($projects),
            \count(array_filter($projects, static fn($project) => $project->isActive())),
            $activityCount,
            $plannedCount,
            $totalDays,
            $uncounted,
        );
    }
}
