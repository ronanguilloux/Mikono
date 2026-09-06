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

    /** @return list<array{id: ?int, label: string, count: int, totalDays: float, mostRecent: ?\DateTimeImmutable}> */
    public function summarizeByVolunteer(): array
    {
        return $this->summarize(
            static fn(Activity $a) => $a->getVolunteer()?->getId(),
            static fn(Activity $a) => $a->getVolunteer()?->getFullName() ?? 'Unknown',
        );
    }

    /** @return list<array{id: ?int, label: string, count: int, totalDays: float, mostRecent: ?\DateTimeImmutable}> */
    public function summarizeByProject(): array
    {
        return $this->summarize(
            static fn(Activity $a) => $a->getProject()?->getId(),
            static fn(Activity $a) => $a->getProject()?->getName() ?? 'Unknown',
        );
    }

    /**
     * Buckets by id, not by label: two volunteers sharing a full name are two
     * rows, not one merged row with double the days. The id is carried out so
     * a caller can link a row back to the thing it summarizes — `/reports`
     * links volunteer rows to `/activities?volunteer=<id>`.
     *
     * Activities with no volunteer (or no project) share one 'unknown' bucket
     * with a null id, which is what makes such a row unlinkable rather than
     * pointing somewhere wrong.
     *
     * @param callable(Activity): ?int   $idFn
     * @param callable(Activity): string $labelFn
     *
     * @return list<array{id: ?int, label: string, count: int, totalDays: float, mostRecent: ?\DateTimeImmutable}>
     */
    private function summarize(callable $idFn, callable $labelFn): array
    {
        $buckets = [];

        foreach ($this->activities->findAllOrderedByDateDesc() as $activity) {
            $id = $idFn($activity);
            $key = $id ?? 'unknown';
            $buckets[$key] ??= ['id' => $id, 'label' => $labelFn($activity), 'count' => 0, 'totalDays' => 0.0, 'mostRecent' => null];
            ++$buckets[$key]['count'];
            $buckets[$key]['totalDays'] += $activity->getDuration()?->toDays() ?? 0.0;

            $date = $activity->getDate();
            if (null !== $date && (null === $buckets[$key]['mostRecent'] || $date > $buckets[$key]['mostRecent'])) {
                $buckets[$key]['mostRecent'] = $date;
            }
        }

        $result = array_values($buckets);
        usort($result, static fn(array $a, array $b) => $b['totalDays'] <=> $a['totalDays']);

        return $result;
    }
}
