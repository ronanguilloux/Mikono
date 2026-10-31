<?php

declare(strict_types=1);

namespace App\Report;

use App\Entity\Branch;
use App\Repository\BranchRepository;
use App\Repository\StayRepository;

/**
 * How many volunteers each active branch hosts per week over the next twelve,
 * against its capacity, for the home screen (ADR 0049). A week counts its
 * busiest day, because beds run out on a day, not on an average. Stays alone
 * decide it: a volunteer's stays never overlap (ADR 0026), so stays covering
 * a day are people there that day.
 *
 * @phpstan-type BranchWeeks array{branch: Branch, capacity: ?int, weeks: list<array{start: \DateTimeImmutable, peak: int}>, firstOver: ?\DateTimeImmutable}
 */
final readonly class OccupancyLookAhead
{
    public const int WEEKS = 12;

    public function __construct(
        private BranchRepository $branches,
        private StayRepository $stays,
    ) {}

    /**
     * Active branches by name, each with all twelve weeks from this week's
     * Monday. A branch with no capacity and nobody booked is left out: there
     * is nothing to plan there.
     *
     * @return list<BranchWeeks>
     */
    public function calculate(\DateTimeImmutable $today): array
    {
        $monday = $today->setTime(0, 0)->modify('monday this week');
        $last = $monday->modify(sprintf('+%d days', self::WEEKS * 7 - 1));

        $staysByBranch = [];
        foreach ($this->stays->findOverlappingAtActiveBranches($monday, $last) as $stay) {
            $staysByBranch[(int) $stay->getBranch()?->getId()][] = $stay;
        }

        $result = [];
        foreach ($this->branches->findBy(['isActive' => true], ['name' => 'ASC']) as $branch) {
            $stays = $staysByBranch[(int) $branch->getId()] ?? [];
            $capacity = $branch->getCapacity();
            if ([] === $stays && null === $capacity) {
                continue;
            }

            $weeks = [];
            $firstOver = null;
            for ($week = 0; $week < self::WEEKS; ++$week) {
                $start = $monday->modify(sprintf('+%d weeks', $week));
                $peak = 0;
                for ($day = 0; $day < 7; ++$day) {
                    $date = $start->modify(sprintf('+%d days', $day));
                    $peak = max($peak, count(array_filter($stays, static fn($stay) => $stay->covers($date))));
                }
                $weeks[] = ['start' => $start, 'peak' => $peak];
                if (null === $firstOver && null !== $capacity && $peak > $capacity) {
                    $firstOver = $start;
                }
            }

            $result[] = ['branch' => $branch, 'capacity' => $capacity, 'weeks' => $weeks, 'firstOver' => $firstOver];
        }

        return $result;
    }
}
