<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\Activity;
use App\Entity\ActivityType;
use App\Entity\Branch;
use App\Entity\Program;
use App\Entity\Stay;
use App\Entity\User;
use App\Entity\Volunteer;
use App\Enum\ActivityDuration;
use App\Repository\ActivityRepository;
use App\Repository\VolunteerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The ACK Clinic attendance tab (brainstorm 15, step 5): one row per day a
 * volunteer worked at the clinic. Days become activities; consecutive days
 * become one stay, a gap of up to $maxGap working days (Mon–Fri) still the
 * same run, so a weekend never splits one.
 *
 * @phpstan-type Run array{key: string, ack_name: string, volunteer: string, candidates: string, proposal: string, action: string, start: string, end: string, days: string, issues: string}
 */
final class LegacyAck
{
    public const string SHEET = 'ACK Clinic Volunteer attendance';
    public const string NOTE = 'Imported from the ACK Clinic attendance sheet';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VolunteerRepository $volunteers,
        private readonly ActivityRepository $activities,
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * Name => its attendance days, sorted. Date cells are swapped back to
     * day-first (Sheets read "1/9/2025" as 9 January); the swap must leave no
     * weekend day and the rows in date order, or the tab isn't what we
     * proved it is and this throws.
     *
     * @return array<string, array{name: string, days: list<\DateTimeImmutable>}>
     */
    public static function days(LegacyWorkbook $workbook): array
    {
        $byName = [];
        $previous = null;
        foreach ($workbook->sheet(self::SHEET) as $index => $row) {
            $name = $row[0] ?? null;
            $cell = $row[1] ?? null;
            if (0 === $index || !is_string($name)) {
                continue;
            }
            $day = $cell instanceof \DateTimeImmutable ? LegacyDates::swapDayMonth($cell) : (null === $cell ? null : LegacyDates::parseDayFirst($cell));
            if (null !== $cell && null === $day) {
                throw new \UnexpectedValueException(sprintf('ACK row %d: unreadable date.', $index + 1));
            }
            if (null !== $day && ((int) $day->format('N') >= 6 || (null !== $previous && $day < $previous))) {
                throw new \UnexpectedValueException(sprintf('ACK row %d: %s is a weekend day or out of order; the day/month swap does not hold.', $index + 1, $day->format('Y-m-d')));
            }
            $previous = $day ?? $previous;
            $key = LegacyPeopleBuilder::normaliseName($name);
            $byName[$key] ??= ['name' => trim($name), 'days' => []];
            if (null !== $day) {
                $byName[$key]['days'][$day->format('Y-m-d')] = $day;
            }
        }
        foreach ($byName as &$entry) {
            ksort($entry['days']);
            $entry['days'] = array_values($entry['days']);
        }

        return $byName;
    }

    /**
     * Splits sorted days into runs: a new run when more than $maxGap working
     * days lie strictly between two attendance days.
     *
     * @param list<\DateTimeImmutable> $days
     *
     * @return list<non-empty-list<\DateTimeImmutable>>
     */
    public static function runs(array $days, int $maxGap): array
    {
        $runs = [];
        foreach ($days as $day) {
            $last = array_key_last($runs);
            if (null !== $last && self::workingDaysBetween(end($runs[$last]), $day) <= $maxGap) {
                $runs[$last][] = $day;
                continue;
            }
            $runs[] = [$day];
        }

        return $runs;
    }

    public static function workingDaysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $count = 0;
        for ($day = $from->modify('+1 day'); $day < $to; $day = $day->modify('+1 day')) {
            $count += (int) $day->format('N') <= 5 ? 1 : 0;
        }

        return $count;
    }

    /**
     * One row per run for ack.csv: who it is (or candidates), and what would
     * hold the days — a stay covering them, one to extend, or a new one.
     * $previous holds the reviewer's earlier volunteer/action choices.
     *
     * @param array<string, array{name: string, days: list<\DateTimeImmutable>}> $byName
     * @param array<string, array{volunteer: string, action: string}>            $previous
     *
     * @return list<Run>
     */
    public function plan(array $byName, Program $program, int $maxGap, array $previous): array
    {
        $all = $this->volunteers->findAll();
        $rows = [];
        foreach ($byName as $normalised => $entry) {
            [$volunteer, $candidates] = self::identify($normalised, $all);
            $runs = [] === $entry['days'] ? [[]] : self::runs($entry['days'], $maxGap);
            foreach ($runs as $i => $run) {
                $key = $normalised . '#' . ($i + 1);
                $before = $previous[$key] ?? null;
                $chosen = null !== $before && '' !== $before['volunteer'] ? $before['volunteer'] : (null === $volunteer ? '' : (string) $volunteer->getId());
                $target = ctype_digit($chosen) ? $this->volunteers->find((int) $chosen) : null;
                [$proposal, $issue] = [] === $run ? ['none', 'no dates: exceptions pass'] : $this->proposal($target, $run, $program, $maxGap);
                $proposed = match (true) {
                    [] === $run => 'skip',
                    null === $target && 'new' !== $chosen => '?',
                    str_starts_with($proposal, 'review') => '?',
                    default => 'accept',
                };
                $rows[] = [
                    'key' => $key,
                    'ack_name' => $entry['name'],
                    'volunteer' => $chosen,
                    'candidates' => $candidates,
                    'proposal' => $proposal,
                    'action' => null !== $before && '' !== $before['action'] ? $before['action'] : $proposed,
                    'start' => [] === $run ? '' : $run[0]->format('Y-m-d'),
                    'end' => [] === $run ? '' : end($run)->format('Y-m-d'),
                    'days' => implode(' ', array_map(static fn(\DateTimeImmutable $d): string => $d->format('m-d'), $run)),
                    'issues' => $issue,
                ];
            }
        }

        return $rows;
    }

    /**
     * Writes every accepted run in one transaction: its stay (kept, extended
     * or created) and one activity per day not already logged.
     *
     * @param array<string, array{name: string, days: list<\DateTimeImmutable>}> $byName
     * @param array<string, array{volunteer: string, action: string}>            $decisions
     *
     * @return array{counts: array<string, int>, problems: list<string>}
     */
    public function apply(array $byName, array $decisions, Program $program, ActivityType $type, User $loggedBy, int $maxGap, bool $commit): array
    {
        $counts = ['volunteers created' => 0, 'stays created' => 0, 'stays extended' => 0, 'activities created' => 0, 'days already logged' => 0, 'runs skipped' => 0];
        $problems = [];
        $branch = $program->getProject()?->getBranch();
        if (!$program->offers($type)) {
            return ['counts' => $counts, 'problems' => [sprintf('%s does not offer %s', $program->getName(), $type->getName())]];
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $created = [];
            foreach ($byName as $normalised => $entry) {
                foreach (self::runs($entry['days'], $maxGap) as $i => $run) {
                    $key = $normalised . '#' . ($i + 1);
                    $decision = $decisions[$key] ?? ['volunteer' => '', 'action' => '?'];
                    if ('accept' !== $decision['action']) {
                        '?' === $decision['action'] ? $problems[] = $key . ': undecided' : ++$counts['runs skipped'];
                        continue;
                    }
                    $volunteer = $this->volunteerFor($decision['volunteer'], $entry['name'], $created, $counts);
                    if (null === $volunteer) {
                        $problems[] = $key . ': no volunteer ' . $decision['volunteer'];
                        continue;
                    }
                    $stay = $this->stayFor($volunteer, $run, $branch, $maxGap, $counts);
                    if (null === $stay) {
                        $problems[] = $key . ': the run overlaps a stay at another branch';
                        continue;
                    }
                    foreach ($run as $day) {
                        if (!$program->covers($day)) {
                            $problems[] = sprintf('%s: %s does not run on %s', $key, $program->getName(), $day->format('Y-m-d'));
                            continue;
                        }
                        if ($this->alreadyLogged($volunteer, $day, $program)) {
                            ++$counts['days already logged'];
                            continue;
                        }
                        $activity = (new Activity())->setDate($day)->setVolunteer($volunteer)->setStay($stay)->setProgram($program)
                            ->setActivityType($type)->setDuration(ActivityDuration::FullDay)->setLoggedBy($loggedBy)->setNotes(self::NOTE);
                        $this->entityManager->persist($activity);
                        ++$counts['activities created'];
                        foreach ($this->validator->validate($activity) as $violation) {
                            $problems[] = sprintf('%s %s: %s', $key, $day->format('Y-m-d'), $violation->getMessage());
                        }
                    }
                    foreach ($this->validator->validate($stay) as $violation) {
                        $problems[] = sprintf('%s: stay: %s', $key, $violation->getMessage());
                    }
                }
            }
            $this->entityManager->flush();
            [] === $problems && $commit ? $connection->commit() : $connection->rollBack();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        } finally {
            $this->entityManager->clear();
        }

        return ['counts' => $counts, 'problems' => $problems];
    }

    /**
     * @param non-empty-list<\DateTimeImmutable> $run
     *
     * @return array{0: string, 1: string}
     */
    private function proposal(?Volunteer $volunteer, array $run, Program $program, int $maxGap): array
    {
        $branch = $program->getProject()?->getBranch();
        $first = $run[0];
        $last = $run[count($run) - 1];
        foreach ($volunteer?->getStays() ?? [] as $stay) {
            $near = self::near($stay, $first, $last, $maxGap);
            if (!$near) {
                continue;
            }
            if ($stay->getBranch() !== $branch) {
                return ['review: stay #' . $stay->getId() . ' at ' . $stay->getBranch()?->getName(), 'overlaps a stay at another branch'];
            }
            if ($stay->covers($first) && $stay->covers($last)) {
                return ['use stay #' . $stay->getId(), ''];
            }

            return [sprintf('extend stay #%d (%s–%s)', $stay->getId(), $stay->getStartDate()?->format('Y-m-d'), $stay->getEndDate()?->format('Y-m-d')), ''];
        }

        return ['create stay (inferred)', ''];
    }

    /**
     * @param non-empty-list<\DateTimeImmutable> $run
     * @param array<string, int>                 $counts
     */
    private function stayFor(Volunteer $volunteer, array $run, ?Branch $branch, int $maxGap, array &$counts): ?Stay
    {
        $first = $run[0];
        $last = $run[count($run) - 1];
        foreach ($volunteer->getStays() as $stay) {
            if (!self::near($stay, $first, $last, $maxGap)) {
                continue;
            }
            if ($stay->getBranch() !== $branch) {
                return null;
            }
            if (!$stay->covers($first) || !$stay->covers($last)) {
                $stay->setStartDate(min($stay->getStartDate() ?? $first, $first))->setEndDate(max($stay->getEndDate() ?? $last, $last));
                $stay->touch();
                ++$counts['stays extended'];
            }

            return $stay;
        }

        $stay = (new Stay())->setBranch($branch)->setStartDate($first)->setEndDate($last);
        $volunteer->addStay($stay);
        $this->entityManager->persist($stay);
        ++$counts['stays created'];

        return $stay;
    }

    /**
     * @param array<string, Volunteer> $created
     * @param array<string, int>       $counts
     */
    private function volunteerFor(string $chosen, string $name, array &$created, array &$counts): ?Volunteer
    {
        if (ctype_digit($chosen)) {
            return $this->volunteers->find((int) $chosen);
        }
        if ('new' !== $chosen) {
            return null;
        }
        $key = LegacyImporter::KEY_PREFIX . 'ack:' . LegacyPeopleBuilder::normaliseName($name);
        // A second run finds the volunteer it created the first time.
        foreach ($this->volunteers->findAll() as $volunteer) {
            if (str_contains((string) $volunteer->getLegacyRecord(), $key)) {
                return $created[$name] = $volunteer;
            }
        }
        if (isset($created[$name])) {
            return $created[$name];
        }
        $parts = explode(' ', trim($name));
        $volunteer = (new Volunteer())->setFirstName(count($parts) > 1 ? implode(' ', array_slice($parts, 0, -1)) : $parts[0])
            ->setLastName(count($parts) > 1 ? end($parts) : null)
            ->setLegacyRecord($key . "\nKnown only from the ACK Clinic attendance sheet");
        $this->entityManager->persist($volunteer);
        ++$counts['volunteers created'];

        return $created[$name] = $volunteer;
    }

    private function alreadyLogged(Volunteer $volunteer, \DateTimeImmutable $day, Program $program): bool
    {
        foreach ($this->activities->findSameDay([$volunteer], $day) as $activity) {
            if ($activity->getProgram() === $program) {
                return true;
            }
        }

        return false;
    }

    /** The stay overlaps the run, or ends/starts within $maxGap working days of it. */
    private static function near(Stay $stay, \DateTimeImmutable $first, \DateTimeImmutable $last, int $maxGap): bool
    {
        $start = $stay->getStartDate();
        $end = $stay->getEndDate();
        if (null === $start || null === $end) {
            return false;
        }

        return ($start <= $last && $first <= $end)
            || ($end < $first && self::workingDaysBetween($end, $first) <= $maxGap)
            || ($last < $start && self::workingDaysBetween($last, $start) <= $maxGap);
    }

    /**
     * The volunteer an attendance name means, when there's exactly one, and
     * a short list for the reviewer otherwise.
     *
     * @param list<Volunteer> $all
     *
     * @return array{0: ?Volunteer, 1: string}
     */
    private static function identify(string $normalised, array $all): array
    {
        $tokens = explode(' ', $normalised);
        $tiers = [[], [], []];
        foreach ($all as $volunteer) {
            $name = LegacyPeopleBuilder::normaliseName($volunteer->getFullName());
            $other = explode(' ', $name);
            match (true) {
                $name === $normalised => $tiers[0][] = $volunteer,
                count($tokens) > 1 && $other[0] === $tokens[0] && end($other) === end($tokens) => $tiers[1][] = $volunteer,
                $other[0] === $tokens[0] => $tiers[2][] = $volunteer,
                default => null,
            };
        }
        foreach ($tiers as $level => $found) {
            if ([] === $found) {
                continue;
            }
            $list = implode('; ', array_map(static fn(Volunteer $v): string => sprintf('#%d %s', $v->getId(), $v->getFullName()), array_slice($found, 0, 6)));

            return [0 === $level && 1 === count($found) ? $found[0] : null, ['exact', 'first+last', 'first name'][$level] . ': ' . $list];
        }

        return [null, 'nobody'];
    }
}
