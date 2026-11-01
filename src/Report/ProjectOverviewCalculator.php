<?php

declare(strict_types=1);

namespace App\Report;

use App\Entity\Project;
use App\Enum\ActivityDuration;
use App\Repository\AchievementRepository;
use App\Repository\ActivityRepository;
use App\Repository\ProgramRepository;
use App\Repository\ProjectRepository;

/**
 * /reports/projects: where every active project stands, for deciding where
 * the next volunteer goes. Which projects are quiet comes from
 * QuietProjectFinder, so this page and the home screen can't disagree.
 *
 * The window runs from the first of the month eleven months back, the same
 * twelve months as the chart. Past figures stop at today, Booked starts at
 * today, as ProgramRepository::countBookedVolunteers() does.
 *
 * @phpstan-type ProjectRow array{id: int, name: string, branch: string, ownership: string, status: string, lastActivity: ?\DateTimeImmutable, volunteers: int, days: float, booked: int, placesToFill: int, openPrograms: int, achievements: int}
 */
final readonly class ProjectOverviewCalculator
{
    public function __construct(
        private QuietProjectFinder $finder,
        private ProjectRepository $projects,
        private ProgramRepository $programs,
        private ActivityRepository $activities,
        private AchievementRepository $achievements,
    ) {}

    /**
     * @return array{rows: list<ProjectRow>, months: list<array{month: \DateTimeImmutable, projects: int}>, from: \DateTimeImmutable, openPrograms: int, withTarget: int}
     */
    public function calculate(\DateTimeImmutable $today): array
    {
        $from = $today->modify('first day of this month')->modify('-11 months');

        $byId = [];
        foreach ($this->projects->findAllOrderedByName() as $project) {
            $byId[(int) $project->getId()] = $project;
        }

        // Quiet first, in the home screen's order, then the latest visited.
        $listed = [];
        foreach ($this->finder->find($today) as $quiet) {
            $listed[] = [$quiet->id, $quiet->hasNeverBeenUsed() ? 'Never visited' : 'Quiet', $quiet->lastActivity];
        }
        foreach ($this->finder->findRecentlyVisited($today) as $recent) {
            $listed[] = [$recent->id, $recent->planned ? 'Planned' : 'Recent', $recent->lastActivity];
        }

        $todayKey = $today->format('Y-m-d');
        $volunteers = $booked = $days = $visitedByMonth = [];
        foreach ($this->activities->findForProjectReport($from) as $row) {
            $id = (int) $row['projectId'];
            $date = substr($row['date'], 0, 10);
            if ($date >= $todayKey) {
                $booked[$id][$row['volunteerId']] = true;
            }
            if ($date > $todayKey) {
                continue;
            }
            $volunteers[$id][$row['volunteerId']] = true;
            $days[$id] = ($days[$id] ?? 0.0) + (null === $row['duration'] ? 0.0 : ActivityDuration::from($row['duration'])->toDays());
            if (isset($byId[$id])) {
                $visitedByMonth[substr($date, 0, 7)][$id] = true;
            }
        }

        $openPrograms = $this->programs->findOpenForMatching($today);
        $bookedCounts = $this->programs->countBookedVolunteers($openPrograms, $today);
        $places = $open = [];
        $withTarget = 0;
        foreach ($openPrograms as $program) {
            $id = (int) $program->getProject()?->getId();
            $open[$id] = ($open[$id] ?? 0) + 1;
            $stillToFind = $program->stillToFind($bookedCounts[(int) $program->getId()] ?? 0);
            $withTarget += null === $stillToFind ? 0 : 1;
            $places[$id] = ($places[$id] ?? 0) + ($stillToFind ?? 0);
        }

        $active = array_values(array_filter(array_map(static fn(array $entry): ?Project => $byId[$entry[0]] ?? null, $listed)));
        $achievementCounts = $this->achievements->countForProjects($active, $from);

        $rows = [];
        foreach ($listed as [$id, $status, $lastActivity]) {
            $project = $byId[$id] ?? null;
            if (null === $project) {
                continue;
            }
            $rows[] = [
                'id' => $id,
                'name' => $project->getName(),
                'branch' => $project->getBranch()?->getName() ?? '—',
                'ownership' => $project->getOwnership()?->label() ?? '—',
                'status' => $status,
                'lastActivity' => $lastActivity,
                'volunteers' => \count($volunteers[$id] ?? []),
                'days' => $days[$id] ?? 0.0,
                'booked' => \count($booked[$id] ?? []),
                'placesToFill' => $places[$id] ?? 0,
                'openPrograms' => $open[$id] ?? 0,
                'achievements' => $achievementCounts[$id] ?? 0,
            ];
        }

        $months = [];
        for ($i = 0; $i < 12; ++$i) {
            $month = $from->modify(sprintf('+%d months', $i));
            $months[] = ['month' => $month, 'projects' => \count($visitedByMonth[$month->format('Y-m')] ?? [])];
        }

        return ['rows' => $rows, 'months' => $months, 'from' => $from, 'openPrograms' => \count($openPrograms), 'withTarget' => $withTarget];
    }
}
