<?php

declare(strict_types=1);

namespace App\Report;

use App\Entity\Branch;
use App\Entity\Program;
use App\Entity\Skill;
use App\Entity\Stay;
use App\Enum\VolunteerStatus;
use App\Repository\ActivityRepository;
use App\Repository\ProgramRepository;
use App\Repository\StayRepository;

/**
 * Pairs open programs with the volunteers who could take part, for the
 * Volunteer Manager planning the next activities. See ADR 0042.
 *
 * A volunteer is a candidate through a stay at the program's branch that
 * hasn't ended and overlaps the program's dates, for one of two reasons:
 * they hold one of its skills, or they have already done one of its activity
 * types, in any program. A logged activity is the only evidence there is —
 * nothing records whether it went well. /matches explains this rule in
 * words: change one, change both.
 *
 * Pairing runs in PHP, as Volunteer::getStatus() keeps its rule in PHP.
 * ponytail: programs × volunteers in a loop, a few hundred rows; push it into
 * DQL if it ever shows on /usage timings.
 */
final class ProgramMatchFinder
{
    public function __construct(
        private readonly ProgramRepository $programs,
        private readonly StayRepository $stays,
        private readonly ActivityRepository $activities,
    ) {}

    /**
     * $who narrows to the stays that cover today (Present) or start later
     * (Upcoming) — the stay that matches, not the volunteer's overall status.
     *
     * @return list<ProgramMatches> neediest first
     */
    public function find(\DateTimeImmutable $today, ?Branch $branch = null, ?Program $program = null, ?VolunteerStatus $who = null): array
    {
        $programs = $this->programs->findOpenForMatching($today, $branch, $program);
        if ([] === $programs) {
            return [];
        }

        /** @var array<int, list<Stay>> $staysByVolunteer earliest first */
        $staysByVolunteer = [];
        foreach ($this->stays->findNotEndedWithVolunteerSkills($today, $branch) as $stay) {
            $isWanted = match ($who) {
                VolunteerStatus::Present => $stay->covers($today),
                VolunteerStatus::Upcoming => $stay->getStartDate() > $today,
                default => true,
            };
            if ($isWanted) {
                $staysByVolunteer[(int) $stay->getVolunteer()?->getId()][] = $stay;
            }
        }

        $experience = [];
        foreach ($this->activities->findExperienceOf(array_keys($staysByVolunteer), $today) as $row) {
            $experience[$row['volunteerId']][] = $row;
        }

        $lastActivities = $this->programs->findLastActivityDates($programs);

        $result = [];
        foreach ($programs as $open) {
            $result[] = $this->matchesFor($open, $staysByVolunteer, $experience, $lastActivities[(int) $open->getId()] ?? null, $today);
        }

        usort($result, static fn(ProgramMatches $a, ProgramMatches $b): int => [...self::urgency($a), $a->program->getName()] <=> [...self::urgency($b), $b->program->getName()]);

        return $result;
    }

    /**
     * @param array<int, list<Stay>>                                                                                       $staysByVolunteer
     * @param array<int, list<array{volunteerId: int, typeId: int, programId: int, total: int, last: \DateTimeImmutable}>> $experience
     */
    private function matchesFor(Program $program, array $staysByVolunteer, array $experience, ?\DateTimeImmutable $lastActivity, \DateTimeImmutable $today): ProgramMatches
    {
        // By name, whatever order the collection was loaded in.
        $needed = array_values($program->getSkills()->toArray());
        usort($needed, static fn(Skill $a, Skill $b): int => $a->getName() <=> $b->getName());
        $typeNames = [];
        foreach ($program->getActivityTypes() as $type) {
            $typeNames[(int) $type->getId()] = $type->getName();
        }

        $candidates = [];
        foreach ($staysByVolunteer as $volunteerId => $stays) {
            $stay = $this->stayFor($program, $stays);
            $volunteer = $stay?->getVolunteer();
            if (null === $stay || null === $volunteer) {
                continue;
            }

            $held = $volunteer->getSkills();
            $matched = array_values(array_filter($needed, static fn(Skill $skill): bool => $held->contains($skill)));

            $done = [];
            $inProgram = 0;
            $last = null;
            foreach ($experience[$volunteerId] ?? [] as $row) {
                $name = $typeNames[$row['typeId']] ?? null;
                if (null === $name) {
                    continue;
                }
                $done[$name] = ($done[$name] ?? 0) + $row['total'];
                $inProgram += $row['programId'] === $program->getId() ? $row['total'] : 0;
                $last = null === $last || $row['last'] > $last ? $row['last'] : $last;
            }

            if ([] === $matched && [] === $done) {
                continue;
            }

            // Most done first, then by name.
            uksort($done, static fn(string $a, string $b): int => [$done[$b], $a] <=> [$done[$a], $b]);

            $candidates[] = new VolunteerMatch(
                $volunteer,
                $stay,
                $stay->covers($today),
                $matched,
                array_values(array_filter($needed, static fn(Skill $skill): bool => !$held->contains($skill))),
                $done,
                $inProgram,
                $last,
            );
        }

        // Skills first, then experience, then present before upcoming, then name.
        usort($candidates, static fn(VolunteerMatch $a, VolunteerMatch $b): int => [
            count($b->matchedSkills), $b->experienceCount(), $b->present, $a->volunteer->getLastName() ?? '', $a->volunteer->getFirstName(),
        ] <=> [
            count($a->matchedSkills), $a->experienceCount(), $a->present, $b->volunteer->getLastName() ?? '', $b->volunteer->getFirstName(),
        ]);

        $uncovered = array_values(array_filter($needed, static function (Skill $skill) use ($candidates): bool {
            foreach ($candidates as $candidate) {
                if (in_array($skill, $candidate->matchedSkills, true)) {
                    return false;
                }
            }

            return true;
        }));

        $start = $program->getStartDate();

        return new ProgramMatches(
            $program,
            $lastActivity,
            null === $lastActivity ? null : (int) $lastActivity->diff($today)->format('%r%a'),
            null !== $start && $start > $today,
            $uncovered,
            $candidates,
        );
    }

    /**
     * The volunteer's earliest stay at the program's branch that overlaps
     * its dates, a missing bound being open as in Program::covers().
     *
     * @param list<Stay> $stays earliest first, none ended
     */
    private function stayFor(Program $program, array $stays): ?Stay
    {
        $branchId = $program->getProject()?->getBranch()?->getId();
        $start = $program->getStartDate();
        $end = $program->getEndDate();

        foreach ($stays as $stay) {
            if ($stay->getBranch()?->getId() === $branchId
                && (null === $end || $stay->getStartDate() <= $end)
                && (null === $start || $stay->getEndDate() >= $start)) {
                return $stay;
            }
        }

        return null;
    }

    /**
     * Started programs first: never run, then the longest since an activity
     * (a planned one counting as covered). Programs not started yet last,
     * the soonest first.
     *
     * @return array{int, int}
     */
    private static function urgency(ProgramMatches $matches): array
    {
        if ($matches->notStarted) {
            return [1, (int) $matches->program->getStartDate()?->getTimestamp()];
        }

        return [0, null === $matches->daysSinceLastActivity ? PHP_INT_MIN : -$matches->daysSinceLastActivity];
    }
}
