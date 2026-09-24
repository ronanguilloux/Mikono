<?php

declare(strict_types=1);

namespace App\Report;

use App\Entity\Activity;
use App\Repository\ActivityRepository;

/**
 * The one piece of real domain logic in this app — computing aggregate
 * activity-days per volunteer/project/program/branch.Duration-to-days conversion lives
 * once, in ActivityDuration::toDays(), not duplicated as SQL that would
 * need to be kept in sync per database dialect.
 */
final class ActivitySummaryCalculator
{
    public function __construct(private readonly ActivityRepository $activities) {}

    /** @return list<array{id: ?int, label: string, count: int, totalDays: float, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}> */
    public function summarizeByVolunteer(): array
    {
        return $this->summarize(
            static fn(Activity $a) => $a->getVolunteer()?->getId(),
            static fn(Activity $a) => $a->getVolunteer()?->getFullName() ?? 'Unknown',
        );
    }

    /** @return list<array{id: ?int, label: string, count: int, totalDays: float, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}> */
    public function summarizeByProject(): array
    {
        return $this->summarize(
            static fn(Activity $a) => $a->getProject()?->getId(),
            static fn(Activity $a) => $a->getProject()?->getName() ?? 'Unknown',
        );
    }

    /**
     * Labelled with the project too: program names repeat across projects
     * ("School support" runs at three schools), so the name alone can't tell
     * the rows apart.
     *
     * @return list<array{id: ?int, label: string, count: int, totalDays: float, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}>
     */
    public function summarizeByProgram(): array
    {
        return $this->summarize(
            static fn(Activity $a) => $a->getProgram()?->getId(),
            static function (Activity $a): string {
                $program = $a->getProgram();

                return null === $program ? 'Unknown' : ($program->getProject()?->getName() ?? '?') . ' — ' . $program->getName();
            },
        );
    }

    /**
     * Counted by the stay's branch, where ADR 0026 anchors an activity's
     * branch — the same rule as the `/activities?branch=` filter. A branch
     * with no activity has no row, like every other breakdown.
     *
     * @return list<array{id: ?int, label: string, count: int, totalDays: float, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}>
     */
    public function summarizeByBranch(): array
    {
        return $this->summarize(
            static fn(Activity $a) => $a->getStay()?->getBranch()?->getId(),
            static fn(Activity $a) => $a->getStay()?->getBranch()?->getName() ?? 'Unknown',
        );
    }

    /**
     * Escort workload. `days` is distinct dates on duty, never summed
     * durations: an activity row is one volunteer, so summing would credit an
     * escort who took four volunteers out for a day with four days, and a
     * volunteer's half or full day says nothing about how long the escort
     * stayed — the app doesn't record escort time, so it can't derive it.
     * `outings` is distinct date + project (one escort covers several sites
     * on some days), `count` the activity rows, as on the other breakdowns.
     *
     * An activity with two escorts counts for both (ADR 0013). One with none
     * lands in the id-less 'No escort recorded' bucket — not "unaccompanied":
     * in the real archive most empty lists are messages cut off before the
     * escort line, so the row is what to follow up, not a finding.
     *
     * @return list<array{id: ?int, label: string, count: int, days: int, outings: int, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}>
     */
    public function summarizeByEscort(): array
    {
        $buckets = [];
        $days = [];
        $outings = [];

        foreach ($this->activities->findAllWithEscorts() as $activity) {
            $targets = [];
            foreach ($activity->getEscorts() as $escort) {
                $targets[(string) $escort->getId()] = ['id' => $escort->getId(), 'label' => $escort->getName()];
            }
            if ([] === $targets) {
                $targets['unknown'] = ['id' => null, 'label' => 'No escort recorded'];
            }

            $date = $activity->getDate();
            $day = (string) $date?->format('Y-m-d');
            $outing = $day . '|' . $activity->getProject()?->getId();

            foreach ($targets as $key => $target) {
                $buckets[$key] ??= $target + ['count' => 0, 'days' => 0, 'outings' => 0, 'mostRecent' => null, 'mostRecentActivityId' => null];
                ++$buckets[$key]['count'];
                $days[$key][$day] = true;
                $outings[$key][$outing] = true;

                if (null !== $date && (null === $buckets[$key]['mostRecent'] || $date > $buckets[$key]['mostRecent'])) {
                    $buckets[$key]['mostRecent'] = $date;
                    $buckets[$key]['mostRecentActivityId'] = $activity->getId();
                }
            }
        }

        foreach ($buckets as $key => $bucket) {
            $buckets[$key]['days'] = count($days[$key]);
            $buckets[$key]['outings'] = count($outings[$key]);
        }

        $result = array_values($buckets);
        usort($result, static fn(array $a, array $b) => [$b['days'], $b['outings']] <=> [$a['days'], $a['outings']]);

        return $result;
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
     * @return list<array{id: ?int, label: string, count: int, totalDays: float, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}>
     */
    private function summarize(callable $idFn, callable $labelFn): array
    {
        $buckets = [];

        foreach ($this->activities->findAllOrderedByDateDesc() as $activity) {
            $id = $idFn($activity);
            $key = $id ?? 'unknown';
            $buckets[$key] ??= ['id' => $id, 'label' => $labelFn($activity), 'count' => 0, 'totalDays' => 0.0, 'mostRecent' => null, 'mostRecentActivityId' => null];
            ++$buckets[$key]['count'];
            $buckets[$key]['totalDays'] += $activity->getDuration()?->toDays() ?? 0.0;

            $date = $activity->getDate();
            if (null !== $date && (null === $buckets[$key]['mostRecent'] || $date > $buckets[$key]['mostRecent'])) {
                $buckets[$key]['mostRecent'] = $date;
                $buckets[$key]['mostRecentActivityId'] = $activity->getId();
            }
        }

        $result = array_values($buckets);
        usort($result, static fn(array $a, array $b) => $b['totalDays'] <=> $a['totalDays']);

        return $result;
    }
}
