<?php

declare(strict_types=1);

namespace App\Report;

use App\Entity\Activity;
use App\Repository\ActivityRepository;
use App\Repository\SourceRepository;

/**
 * The one piece of real domain logic in this app — computing aggregate
 * activity-days per volunteer/project/program/branch/beneficiary group/source. Duration-to-days conversion lives
 * once, in ActivityDuration::toDays(), not duplicated as SQL that would
 * need to be kept in sync per database dialect.
 */
final class ActivitySummaryCalculator
{
    public function __construct(
        private readonly ActivityRepository $activities,
        private readonly SourceRepository $sources,
    ) {}

    /** @return list<array{id: ?int, label: string, count: int, totalDays: float, volunteers: int, parent: ?string, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}> */
    public function summarizeByVolunteer(): array
    {
        return $this->summarize(
            static fn(Activity $a) => $a->getVolunteer()?->getId(),
            static fn(Activity $a) => $a->getVolunteer()?->getFullName() ?? 'Unknown',
        );
    }

    /** @return list<array{id: ?int, label: string, count: int, totalDays: float, volunteers: int, parent: ?string, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}> */
    public function summarizeByProject(): array
    {
        return $this->summarize(
            static fn(Activity $a) => $a->getProject()?->getId(),
            static fn(Activity $a) => $a->getProject()?->getName() ?? 'Unknown',
            static fn(Activity $a) => $a->getProject()?->getBranch()?->getName(),
        );
    }

    /** @return list<array{id: ?int, label: string, count: int, totalDays: float, volunteers: int, parent: ?string, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}> */
    public function summarizeByActivityType(): array
    {
        return $this->summarize(
            static fn(Activity $a) => $a->getActivityType()?->getId(),
            static fn(Activity $a) => $a->getActivityType()?->getName() ?? 'Unknown',
        );
    }

    /**
     * Program names repeat across projects ("School support" runs at three
     * schools), so each row carries its project as `parent`, shown in its own
     * column on /reports/volunteers.
     *
     * @return list<array{id: ?int, label: string, count: int, totalDays: float, volunteers: int, parent: ?string, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}>
     */
    public function summarizeByProgram(): array
    {
        return $this->summarize(
            static fn(Activity $a) => $a->getProgram()?->getId(),
            static fn(Activity $a) => $a->getProgram()?->getName() ?? 'Unknown',
            static fn(Activity $a) => $a->getProject()?->getName(),
        );
    }

    /**
     * Counted by the stay's branch, where ADR 0026 anchors an activity's
     * branch — the same rule as the `/activities?branch=` filter. A branch
     * with no activity has no row, like every other breakdown.
     *
     * @return list<array{id: ?int, label: string, count: int, totalDays: float, volunteers: int, parent: ?string, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}>
     */
    public function summarizeByBranch(): array
    {
        return $this->summarize(
            static fn(Activity $a) => $a->getStay()?->getBranch()?->getId(),
            static fn(Activity $a) => $a->getStay()?->getBranch()?->getName() ?? 'Unknown',
        );
    }

    /**
     * Who the activity served, through its program's beneficiary groups. Like
     * escorts (ADR 0013), an activity counts in full under every group its
     * program serves, so the column totals exceed the real activity-days. An
     * untagged program's activities land in the id-less 'No group recorded'
     * bucket. See ADR 0030.
     *
     * @return list<array{id: ?int, label: string, count: int, totalDays: float, volunteers: int, parent: ?string, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}>
     */
    public function summarizeByBeneficiaryGroup(): array
    {
        // ponytail: lazy-loads each program's groups once; programs number in the tens. Fetch-join in findAllOrderedByDateDesc if that grows.
        return $this->summarizeMany(static function (Activity $a): array {
            $targets = [];
            foreach ($a->getProgram()?->getBeneficiaryGroups() ?? [] as $group) {
                $targets[] = ['id' => $group->getId(), 'label' => $group->getName()];
            }

            return [] === $targets ? [['id' => null, 'label' => 'No group recorded']] : $targets;
        });
    }

    /**
     * Recruitment channels over one Nairobi calendar year (ADR 0041): the
     * year's activities, each credited in full to every source of its
     * volunteer, so like beneficiary groups the totals exceed the real ones.
     * Every source has a row, zeros included — a channel that brought nobody
     * is the finding. Volunteers with no source share the id-less 'Not
     * recorded' bucket, shown only when it has any.
     *
     * @return list<array{id: ?int, label: string, count: int, totalDays: float, volunteers: int, parent: ?string, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}>
     */
    public function summarizeBySource(int $year): array
    {
        $from = new \DateTimeImmutable($year . '-01-01');
        $to = new \DateTimeImmutable($year . '-12-31');
        $empty = ['count' => 0, 'totalDays' => 0.0, 'volunteers' => 0, 'parent' => null, 'mostRecent' => null, 'mostRecentActivityId' => null];

        $buckets = [];
        foreach ($this->sources->findAllOrderedByName() as $source) {
            $buckets[(int) $source->getId()] = ['id' => $source->getId(), 'label' => $source->getName()] + $empty;
        }
        $worked = $this->summarizeMany(static function (Activity $a): array {
            $targets = [];
            foreach ($a->getVolunteer()?->getSources() ?? [] as $source) {
                $targets[] = ['id' => $source->getId(), 'label' => $source->getName()];
            }

            return [] === $targets ? [['id' => null, 'label' => 'Not recorded']] : $targets;
        }, activities: $this->activities->findDatedBetweenWithSources($from, $to));
        foreach ($worked as $row) {
            $buckets[$row['id'] ?? 'unknown'] = $row;
        }

        $rows = array_values($buckets);
        usort($rows, static fn(array $a, array $b): int => [$b['volunteers'], $b['totalDays']] <=> [$a['volunteers'], $a['totalDays']]);

        return $rows;
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
     * a caller can link a row back to the thing it summarizes — `/reports/volunteers`
     * links volunteer rows to `/activities?volunteer=<id>`.
     *
     * `volunteers` is the distinct people in a bucket, planned activities
     * included like every other column here — a project's count is distinct
     * across its programs, never the sum of theirs. Always 1 on the volunteer
     * breakdown, which is why /reports/volunteers doesn't show it there.
     *
     * Activities with no volunteer (or no project) share one 'unknown' bucket
     * with a null id, which is what makes such a row unlinkable rather than
     * pointing somewhere wrong.
     *
     * `parent` is what the row belongs to (a project's branch, a program's
     * project), null where the breakdown has none.
     *
     * @param callable(Activity): ?int           $idFn
     * @param callable(Activity): string         $labelFn
     * @param (callable(Activity): ?string)|null $parentFn
     *
     * @return list<array{id: ?int, label: string, count: int, totalDays: float, volunteers: int, parent: ?string, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}>
     */
    private function summarize(callable $idFn, callable $labelFn, ?callable $parentFn = null): array
    {
        return $this->summarizeMany(
            static fn(Activity $a): array => [['id' => $idFn($a), 'label' => $labelFn($a)]],
            $parentFn,
        );
    }

    /**
     * summarize(), for a breakdown where one activity lands in several
     * buckets: each target gets the activity in full.
     *
     * $activities defaults to every activity; a caller passes its own subset.
     *
     * @param callable(Activity): list<array{id: ?int, label: string}> $targetsFn
     * @param (callable(Activity): ?string)|null                       $parentFn
     * @param Activity[]|null                                          $activities
     *
     * @return list<array{id: ?int, label: string, count: int, totalDays: float, volunteers: int, parent: ?string, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}>
     */
    private function summarizeMany(callable $targetsFn, ?callable $parentFn = null, ?array $activities = null): array
    {
        $buckets = [];
        $volunteers = [];

        foreach ($activities ?? $this->activities->findAllOrderedByDateDesc() as $activity) {
            foreach ($targetsFn($activity) as $target) {
                $id = $target['id'];
                $key = $id ?? 'unknown';
                $buckets[$key] ??= ['id' => $id, 'label' => $target['label'], 'count' => 0, 'totalDays' => 0.0, 'volunteers' => 0, 'parent' => null === $parentFn ? null : $parentFn($activity), 'mostRecent' => null, 'mostRecentActivityId' => null];
                ++$buckets[$key]['count'];
                $volunteers[$key][(int) $activity->getVolunteer()?->getId()] = true;
                $buckets[$key]['totalDays'] += $activity->getDuration()?->toDays() ?? 0.0;

                $date = $activity->getDate();
                if (null !== $date && (null === $buckets[$key]['mostRecent'] || $date > $buckets[$key]['mostRecent'])) {
                    $buckets[$key]['mostRecent'] = $date;
                    $buckets[$key]['mostRecentActivityId'] = $activity->getId();
                }
            }
        }

        foreach ($buckets as $key => $bucket) {
            $buckets[$key]['volunteers'] = count($volunteers[$key]);
        }

        $result = array_values($buckets);
        usort($result, static fn(array $a, array $b) => $b['totalDays'] <=> $a['totalDays']);

        return $result;
    }
}
