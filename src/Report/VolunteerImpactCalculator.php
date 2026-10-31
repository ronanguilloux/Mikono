<?php

declare(strict_types=1);

namespace App\Report;

use App\Entity\Activity;
use App\Repository\ActivityRepository;

/**
 * Totals one volunteer's activities for the volunteer page and the stay page:
 * the caller passes the activities (all of them, or one stay's), so both pages
 * count by the same rules. Past activities only, but for the two counts —
 * unless $withPlanned, as on the stay page, which shows a stay's whole
 * expected outcome before it ends.
 * Every activity counts as one day whatever its duration: the VM counts days
 * given, not hours spent, and a half day is still a day on site.
 */
final readonly class VolunteerImpactCalculator
{
    /** Categorical colour slots (twig.yaml's chart_palette); beyond this, the smallest fold into "Other". */
    public const int MAX_SHARES = 8;

    public function __construct(
        private ActivityRepository $activities,
    ) {}

    /** @param Activity[] $activities */
    public function calculate(array $activities, \DateTimeImmutable $today, bool $withPlanned = false): VolunteerImpact
    {
        $planned = 0;
        $loggedDates = [];
        $impact = ['programs' => [], 'projects' => []];
        $byProgram = [];
        $byType = [];

        foreach ($activities as $activity) {
            $date = $activity->getDate();
            if (null === $date) {
                continue;
            }
            if ($date > $today) {
                ++$planned;
                if (!$withPlanned) {
                    continue;
                }
            }

            $loggedDates[$date->format('Y-m-d')] = true;
            $program = $activity->getProgram();
            $project = $program?->getProject();
            if (null === $program || null === $project) {
                continue;
            }
            foreach (['programs' => $program, 'projects' => $project] as $level => $subject) {
                $id = (int) $subject->getId();
                $impact[$level][$id] ??= ['subject' => $subject, 'count' => 0, 'dates' => []];
                ++$impact[$level][$id]['count'];
                $impact[$level][$id]['dates'][$date->format('Y-m-d')] = true;
            }

            $programLabel = $project->getName() . ' — ' . $program->getName();
            $type = (string) $activity->getActivityType()?->getName();
            $byProgram[$programLabel] = ($byProgram[$programLabel] ?? 0) + 1;
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }

        $engaged = $this->activities->countVolunteersEngaged(array_keys($impact['programs']), array_keys($impact['projects']), $today);
        $impactRows = ['programs' => [], 'projects' => []];
        foreach ($impact as $level => $buckets) {
            foreach ($buckets as $id => $bucket) {
                $impactRows[$level][] = ['subject' => $bucket['subject'], 'count' => $bucket['count'], 'days' => \count($bucket['dates']), 'engaged' => $engaged[$level][$id] ?? 0];
            }
            usort($impactRows[$level], static fn(array $a, array $b) => [$b['days'], $b['count']] <=> [$a['days'], $a['count']]);
        }

        return new VolunteerImpact(
            \count($activities),
            $planned,
            \count($loggedDates),
            $impactRows,
            self::shares($byProgram),
            self::shares($byType),
        );
    }

    /**
     * Largest first; past MAX_SHARES rows the smallest fold into one "Other", so a
     * categorical chart never runs out of colours.
     *
     * @param array<array-key, int> $counts label => activities
     *
     * @return list<array{label: string, count: int}>
     */
    private static function shares(array $counts): array
    {
        arsort($counts);
        $rows = [];
        foreach ($counts as $label => $count) {
            $rows[] = ['label' => (string) $label, 'count' => $count];
        }
        if (\count($rows) > self::MAX_SHARES) {
            $other = array_sum(array_column(\array_slice($rows, self::MAX_SHARES - 1), 'count'));
            $rows = [...\array_slice($rows, 0, self::MAX_SHARES - 1), ['label' => 'Other', 'count' => $other]];
        }

        return $rows;
    }
}
