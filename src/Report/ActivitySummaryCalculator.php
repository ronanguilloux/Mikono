<?php

declare(strict_types=1);

namespace App\Report;

use App\Entity\Activity;
use App\Repository\ActivityRepository;

/**
 * The one piece of real domain logic in this app — computing aggregate
 * activity-days per volunteer/project. Duration-to-days conversion lives
 * once, in ActivityDuration::toDays(), not duplicated as SQL that would
 * need to be kept in sync per database dialect.
 */
final class ActivitySummaryCalculator
{
    public function __construct(private readonly ActivityRepository $activities) {}

    /** @return list<array{label: string, count: int, totalDays: float, mostRecent: ?\DateTimeImmutable}> */
    public function summarizeByVolunteer(): array
    {
        return $this->summarize(static fn(Activity $a) => $a->getVolunteer()?->getFullName() ?? 'Unknown');
    }

    /** @return list<array{label: string, count: int, totalDays: float, mostRecent: ?\DateTimeImmutable}> */
    public function summarizeByProject(): array
    {
        return $this->summarize(static fn(Activity $a) => $a->getProject()?->getName() ?? 'Unknown');
    }

    /**
     * @param callable(Activity): string $labelFn
     *
     * @return list<array{label: string, count: int, totalDays: float, mostRecent: ?\DateTimeImmutable}>
     */
    private function summarize(callable $labelFn): array
    {
        $buckets = [];

        foreach ($this->activities->findAllOrderedByDateDesc() as $activity) {
            $label = $labelFn($activity);
            $buckets[$label] ??= ['label' => $label, 'count' => 0, 'totalDays' => 0.0, 'mostRecent' => null];
            ++$buckets[$label]['count'];
            $buckets[$label]['totalDays'] += $activity->getDuration()?->toDays() ?? 0.0;

            $date = $activity->getDate();
            if (null !== $date && (null === $buckets[$label]['mostRecent'] || $date > $buckets[$label]['mostRecent'])) {
                $buckets[$label]['mostRecent'] = $date;
            }
        }

        $result = array_values($buckets);
        usort($result, static fn(array $a, array $b) => $b['totalDays'] <=> $a['totalDays']);

        return $result;
    }
}
