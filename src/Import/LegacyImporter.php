<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\Branch;
use App\Entity\Skill;
use App\Entity\Source;
use App\Entity\Stay;
use App\Entity\Volunteer;
use App\Repository\BranchRepository;
use App\Repository\SkillRepository;
use App\Repository\SourceRepository;
use App\Repository\VolunteerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Intl\Countries;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Maps LegacyPeopleBuilder's people onto production's lists and volunteers,
 * and writes them (brainstorm 15). Two rules hold everywhere: a value already
 * in production is never overwritten, and a person whose match isn't certain
 * waits for a reviewer's decision in people.csv.
 *
 * @phpstan-type Mapping array{branches: array<string, ?string>, sources: array<string, ?string>, skills: array<string, list<string>>, countries: array<string, ?string>}
 * @phpstan-type Decision array{action: string, branch: string}
 * @phpstan-type PlanRow array{key: string, proposed: string, suggestion: string, action: string, branch: string, exception: bool, name: string, email: string, match: string, stays: string, seen_in: string, issues: string}
 */
final class LegacyImporter
{
    public const string KEY_PREFIX = 'Legacy import key: ';

    /** Words in a roster ACTIVITY => the seeded skill they mean. */
    private const array SKILL_WORDS = [
        'child' => 'Early-childhood care', 'teach' => 'Teaching & tutoring', 'educat' => 'Teaching & tutoring',
        'tutor' => 'Teaching & tutoring', 'mentor' => 'Teaching & tutoring', 'sport' => 'Sports coaching',
        'football' => 'Sports coaching', 'danc' => 'Dance', 'music' => 'Music', 'art' => 'Art & crafts',
        'creative' => 'Art & crafts', 'paint' => 'Painting & decoration', 'construct' => 'Construction',
        'build' => 'Construction', 'clinic' => 'Medicine', 'medic' => 'Medicine', 'doc' => 'Medicine',
        'triage' => 'Medicine', 'nurs' => 'Nursing & midwifery', 'midwi' => 'Nursing & midwifery',
        'mental' => 'Counselling', 'counsel' => 'Counselling', 'psych' => 'Counselling',
        'photo' => 'Photography & video', 'video' => 'Photography & video', 'social media' => 'Social media & content',
        'french' => 'Language teaching', 'english' => 'Language teaching', 'language' => 'Language teaching',
        'farm' => 'Farming & gardening', 'eco' => 'Farming & gardening', 'garden' => 'Farming & gardening',
        'health educ' => 'Public-health education', 'nutrition' => 'Public-health education',
        'computer' => 'Computer & digital skills', 'project manag' => 'Office administration',
        'yoga' => 'Yoga & fitness', 'dent' => 'Dentistry', 'first aid' => 'First aid',
        'med student' => 'Medicine', 'med camp' => 'Medicine', 'pych' => 'Counselling', 'rugby' => 'Sports coaching',
        'swim' => 'Swimming & lifeguarding', 'carpent' => 'Construction',
    ];

    private const array SOURCE_WORDS = ['vw' => 'Volunteer World', 'volunteer world' => 'Volunteer World',
        'email' => 'Website/Email', 'website' => 'Website/Email', 'whatsapp' => 'WhatsApp', 'tiktok' => 'TikTok',
        'instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube'];

    private const array BRANCH_WORDS = ['nairobi' => 'Nairobi (HQ)', 'narobi' => 'Nairobi (HQ)', 'kibera' => 'Nairobi (HQ)',
        'mombasa' => 'Mombasa', 'samburu' => 'Samburu', 'kampala' => 'Uganda', 'uganda' => 'Uganda'];

    private const array COUNTRY_ALIASES = ['usa' => 'US', 'us' => 'US', 'america' => 'US', 'united states of america' => 'US',
        'uk' => 'GB', 'england' => 'GB', 'scotland' => 'GB', 'wales' => 'GB', 'great britain' => 'GB',
        'holland' => 'NL', 'the netherlands' => 'NL', 'deutschland' => 'DE', 'schweiz' => 'CH', 'suisse' => 'CH',
        'espana' => 'ES', 'italia' => 'IT', 'czech republic' => 'CZ', 'south korea' => 'KR', 'korea' => 'KR',
        'russia' => 'RU', 'polska' => 'PL', 'osterreich' => 'AT', 'danmark' => 'DK', 'sverige' => 'SE', 'norge' => 'NO',
        'u s a' => 'US', 'u s' => 'US', 'uae' => 'AE', 'dr congo' => 'CD', 'drc' => 'CD', 'nederland' => 'NL',
        'german' => 'DE', 'british' => 'GB', 'french' => 'FR', 'italian' => 'IT', 'spanish' => 'ES', 'swiss' => 'CH',
        'dutch' => 'NL', 'american' => 'US', 'canadian' => 'CA', 'australian' => 'AU'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VolunteerRepository $volunteers,
        private readonly BranchRepository $branches,
        private readonly SkillRepository $skills,
        private readonly SourceRepository $sources,
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * Every distinct legacy value, with production's name for it where one is
     * clear. A value already in $current keeps the reviewer's choice.
     *
     * @param array<string, LegacyPerson> $people
     * @param Mapping                     $current
     *
     * @return Mapping
     */
    public function proposeMapping(array $people, array $current): array
    {
        $mapping = $current;
        $branchNames = array_map(static fn(Branch $b): string => $b->getName(), $this->branches->findAll());
        $skillNames = array_map(static fn(Skill $s): string => $s->getName(), $this->skills->findAll());
        $sourceNames = array_map(static fn(Source $s): string => $s->getName(), $this->sources->findAll());

        foreach ($people as $person) {
            foreach ($person->stays as $stay) {
                if (null !== $stay['branch'] && !array_key_exists($stay['branch'], $mapping['branches'])) {
                    $mapping['branches'][$stay['branch']] = self::firstWord($stay['branch'], self::BRANCH_WORDS, $branchNames);
                }
            }
            foreach ($person->sources as $raw) {
                if (!array_key_exists($raw, $mapping['sources'])) {
                    $mapping['sources'][$raw] = self::firstWord($raw, self::SOURCE_WORDS, $sourceNames);
                }
            }
            foreach ($person->activities as $raw) {
                if (!array_key_exists($raw, $mapping['skills'])) {
                    $mapping['skills'][$raw] = self::allWords($raw, self::SKILL_WORDS, $skillNames);
                }
            }
            foreach ($person->countries as $raw) {
                if (!array_key_exists($raw, $mapping['countries'])) {
                    $mapping['countries'][$raw] = self::country($raw);
                }
            }
        }
        foreach ($mapping as &$section) {
            ksort($section, \SORT_NATURAL | \SORT_FLAG_CASE);
        }

        return $mapping;
    }

    /**
     * One row per person for people.csv: what the importer would do, and
     * why it would wait. $previous holds the reviewer's earlier decisions.
     *
     * @param array<string, LegacyPerson> $people
     * @param Mapping                     $mapping
     * @param array<string, Decision>     $previous
     *
     * @return list<PlanRow>
     */
    public function plan(array $people, array $mapping, array $previous): array
    {
        [$byKey, $byEmail, $byName] = $this->productionIndex();
        $rows = [];
        foreach ($people as $person) {
            $match = '';
            $suggestion = '';
            $proposed = 'create';
            $matched = null;
            if (null !== $volunteer = $byKey[$person->key] ?? null) {
                $proposed = 'fill:' . $volunteer->getId();
                $match = 'import key';
                $matched = $volunteer;
            } elseif (is_string($email = $person->fields['email'] ?? null) && null !== $volunteer = $byEmail[$email] ?? null) {
                $proposed = 'fill:' . $volunteer->getId();
                $match = 'email';
                $matched = $volunteer;
            } elseif ([] !== $named = $byName[LegacyPeopleBuilder::normaliseName($person->fullName())] ?? []) {
                $proposed = '?';
                $suggestion = 'fill:' . $named[0]->getId();
                $match = sprintf('name only (%d in production)', count($named));
                $matched = $named[0];
            }

            $issues = array_values(array_unique($person->issues));
            $blocked = false;
            $unbranched = array_filter($person->stays, fn(array $s): bool => null === $this->branchFor($s['branch'], $mapping, '')
                && !self::overlapsAny($s, $matched));
            $branch = '';
            if ([] !== $unbranched) {
                $issues[] = sprintf('%d stay(s) without a branch: confirm the branch column', count($unbranched));
                $branch = 'Nairobi (HQ)';
                $blocked = true;
            }
            foreach ($issues as $issue) {
                $blocked = $blocked || str_starts_with($issue, 'maybe the same person') || str_starts_with($issue, 'name matches several');
            }
            if ($blocked && '?' !== $proposed) {
                $suggestion = $proposed;
                $proposed = '?';
            }

            $before = $previous[$person->key] ?? null;
            $rows[] = [
                'key' => $person->key,
                'proposed' => $proposed,
                'suggestion' => $suggestion,
                // A reviewer's decision outlives a re-analysis.
                'action' => null !== $before && '' !== $before['action'] ? $before['action'] : $proposed,
                'branch' => null !== $before && '' !== $before['branch'] ? $before['branch'] : $branch,
                'exception' => !$person->hasDates(),
                'name' => $person->fullName(),
                'email' => is_string($person->fields['email'] ?? null) ? $person->fields['email'] : '',
                'match' => $match,
                'stays' => implode(' | ', array_map(static fn(array $s): string => sprintf('%s→%s %s', $s['start']->format('Y-m-d'), $s['end']->format('Y-m-d'), $s['branch'] ?? '(no branch)'), $person->stays)),
                'seen_in' => implode(', ', $person->seenIn),
                'issues' => implode('; ', $issues),
            ];
        }

        return $rows;
    }

    /**
     * Writes every decided person in one transaction, flushed only when
     * $commit and nothing failed validation. Returns counts and problems.
     *
     * @param array<string, LegacyPerson> $people
     * @param Mapping                     $mapping
     * @param array<string, Decision>     $decisions
     *
     * @return array{counts: array<string, int>, problems: list<string>}
     */
    public function apply(array $people, array $mapping, array $decisions, bool $withExceptions, bool $commit): array
    {
        $counts = ['volunteers created' => 0, 'volunteers filled' => 0, 'volunteers unchanged' => 0, 'stays created' => 0, 'stays skipped (overlap)' => 0,
            'skills linked' => 0, 'sources linked' => 0, 'skipped' => 0, 'held (exception)' => 0];
        $problems = [];

        foreach ($decisions as $key => $decision) {
            if (str_starts_with($decision['action'], 'merge:')) {
                $target = $people[substr($decision['action'], 6)] ?? null;
                if (null === $target || !isset($people[$key])) {
                    $problems[] = sprintf('%s: merge target not found', $key);
                    continue;
                }
                self::merge($people[$key], $target);
            }
        }

        // A person already imported is found again by key, whatever people.csv
        // says: a second run never creates them twice.
        [$imported] = $this->productionIndex();

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            foreach ($people as $key => $person) {
                $action = $decisions[$key]['action'] ?? '?';
                if (str_starts_with($action, 'merge:')) {
                    continue;
                }
                if ('?' === $action) {
                    $problems[] = sprintf('%s: undecided (action "?")', $key);
                    continue;
                }
                if ('skip' === $action) {
                    ++$counts['skipped'];
                    continue;
                }
                if (!$person->hasDates() && !$withExceptions) {
                    ++$counts['held (exception)'];
                    continue;
                }
                $volunteer = 'create' === $action && isset($imported[$key])
                    ? $imported[$key]
                    : $this->volunteerFor($action, $key, $problems);
                if (null === $volunteer) {
                    continue;
                }
                $isNew = null === $volunteer->getId();
                $changed = $this->write($person, $volunteer, $mapping, $decisions[$key]['branch'] ?? '', $counts, $problems);
                ++$counts[match (true) {
                    $isNew => 'volunteers created',
                    $changed => 'volunteers filled',
                    default => 'volunteers unchanged',
                }];
            }

            if ([] === $problems) {
                $this->entityManager->flush();
            }[] === $problems && $commit ? $connection->commit() : $connection->rollBack();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        } finally {
            $this->entityManager->clear();
        }

        return ['counts' => $counts, 'problems' => $problems];
    }

    /**
     * @param list<string> $problems
     */
    private function volunteerFor(string $action, string $key, array &$problems): ?Volunteer
    {
        if ('create' === $action) {
            $volunteer = new Volunteer();
            $this->entityManager->persist($volunteer);

            return $volunteer;
        }
        if (1 === preg_match('/^fill:(\d+)$/', $action, $m)) {
            $volunteer = $this->volunteers->find((int) $m[1]);
            if (null === $volunteer) {
                $problems[] = sprintf('%s: volunteer %s not found', $key, $m[1]);
            }

            return $volunteer;
        }
        $problems[] = sprintf('%s: unknown action "%s"', $key, $action);

        return null;
    }

    /**
     * @param Mapping            $mapping
     * @param array<string, int> $counts
     * @param list<string>       $problems
     *
     * @return bool whether anything on the volunteer changed
     */
    private function write(LegacyPerson $person, Volunteer $volunteer, array $mapping, string $branchOverride, array &$counts, array &$problems): bool
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $changed = false;
        $fill = static function (string $property, mixed $value) use ($accessor, $volunteer, &$changed): void {
            if (null !== $value && null === $accessor->getValue($volunteer, $property)) {
                $accessor->setValue($volunteer, $property, $value);
                $changed = true;
            }
        };

        if ('' === $volunteer->getFirstName()) {
            $volunteer->setFirstName($person->firstName);
            $changed = true;
        }
        $fill('lastName', $person->lastName);
        foreach ($person->fields as $property => $value) {
            $fill($property, $value);
        }
        foreach ($person->countries as $raw) {
            if (null !== $iso = $mapping['countries'][$raw] ?? null) {
                $fill('countryOfResidence', $iso);
                break;
            }
        }
        foreach (['instagram', 'facebook', 'twitter', 'tiktok', 'linkedin'] as $property) {
            $url = $person->fields[$property] ?? null;
            if (is_string($url) && 1 === preg_match('#^https?://[^/\s]+\.[a-z]{2,}(/\S*)?$#i', $url) && mb_strlen($url) <= 255) {
                $fill('socialMediaUrl', $url);
                break;
            }
        }

        $changed = self::appendLines($volunteer->getLegacyRecord(), $person->legacy, $volunteer->setLegacyRecord(...)) || $changed;

        foreach (array_unique(array_merge(...array_map(static fn(string $raw): array => $mapping['skills'][$raw] ?? [], $person->activities))) as $name) {
            $skill = $this->skills->findOneBy(['name' => $name]);
            if (null === $skill) {
                $problems[] = sprintf('%s: no skill "%s" in production', $person->key, $name);
            } elseif (!$volunteer->getSkills()->contains($skill)) {
                $volunteer->addSkill($skill);
                ++$counts['skills linked'];
                $changed = true;
            }
        }
        foreach (array_unique(array_filter(array_map(static fn(string $raw): ?string => $mapping['sources'][$raw] ?? null, $person->sources), static fn(?string $name): bool => null !== $name)) as $name) {
            $source = $this->sources->findOneBy(['name' => $name]);
            if (null === $source) {
                $problems[] = sprintf('%s: no source "%s" in production', $person->key, $name);
            } elseif (!$volunteer->getSources()->contains($source)) {
                $volunteer->addSource($source);
                ++$counts['sources linked'];
                $changed = true;
            }
        }

        foreach ($person->stays as $legacy) {
            // Production wins: a stay already covering these days stays as it is.
            $stay = (new Stay())->setStartDate($legacy['start'])->setEndDate($legacy['end']);
            foreach ($volunteer->getStays() as $existing) {
                if ($existing->overlaps($stay)) {
                    ++$counts['stays skipped (overlap)'];
                    continue 2;
                }
            }
            $branch = $this->branchFor($legacy['branch'], $mapping, $branchOverride);
            if (null === $branch) {
                $problems[] = sprintf('%s: stay %s has no branch', $person->key, $legacy['start']->format('Y-m-d'));
                continue;
            }
            $stay->setBranch($branch);
            $volunteer->addStay($stay);
            $this->entityManager->persist($stay);
            ++$counts['stays created'];
            $changed = true;
            foreach ($this->validator->validate($stay) as $violation) {
                $problems[] = sprintf('%s: stay %s: %s', $person->key, $violation->getPropertyPath(), $violation->getMessage());
            }
        }

        // A roster duration stands in for dates only on a volunteer with no stay.
        if ($volunteer->getStays()->isEmpty()) {
            $changed = self::appendLines($volunteer->getNotes(), $person->notes, $volunteer->setNotes(...)) || $changed;
        }

        if ($changed && null !== $volunteer->getId()) {
            $volunteer->touch();
        }
        foreach ($this->validator->validate($volunteer) as $violation) {
            $problems[] = sprintf('%s: %s: %s', $person->key, $violation->getPropertyPath(), $violation->getMessage());
        }

        return $changed;
    }

    /** @param Mapping $mapping */
    private function branchFor(?string $raw, array $mapping, string $override): ?Branch
    {
        $name = null === $raw ? null : ($mapping['branches'][$raw] ?? null);
        $name ??= '' === $override ? null : $override;

        return null === $name ? null : $this->branches->findOneBy(['name' => $name]);
    }

    /**
     * Production volunteers by import key, email and normalised name.
     *
     * @return array{0: array<string, Volunteer>, 1: array<string, Volunteer>, 2: array<string, list<Volunteer>>}
     */
    private function productionIndex(): array
    {
        $byKey = $byEmail = $byName = [];
        foreach ($this->volunteers->findAll() as $volunteer) {
            foreach (explode("\n", (string) $volunteer->getLegacyRecord()) as $line) {
                if (str_starts_with($line, self::KEY_PREFIX)) {
                    $byKey[substr($line, strlen(self::KEY_PREFIX))] = $volunteer;
                }
            }
            if (null !== $email = $volunteer->getEmail()) {
                $byEmail[strtolower($email)] = $volunteer;
            }
            $byName[LegacyPeopleBuilder::normaliseName($volunteer->getFullName())][] = $volunteer;
        }

        return [$byKey, $byEmail, $byName];
    }

    /** @param array{start: \DateTimeImmutable, end: \DateTimeImmutable} $legacy */
    private static function overlapsAny(array $legacy, ?Volunteer $volunteer): bool
    {
        $stay = (new Stay())->setStartDate($legacy['start'])->setEndDate($legacy['end']);
        foreach ($volunteer?->getStays() ?? [] as $existing) {
            if ($existing->overlaps($stay)) {
                return true;
            }
        }

        return false;
    }

    private static function merge(LegacyPerson $from, LegacyPerson $into): void
    {
        $into->legacy = [...$into->legacy, ...$from->legacy, 'Merged from: ' . $from->fullName()];
        $into->fields += $from->fields;
        $into->lastName ??= $from->lastName;
        $into->countries = [...$into->countries, ...$from->countries];
        $into->activities = [...$into->activities, ...$from->activities];
        $into->sources = [...$into->sources, ...$from->sources];
        $into->seenIn = [...$into->seenIn, ...$from->seenIn];
        $into->rosterBranches += $from->rosterBranches;
        $into->directBranch ??= $from->directBranch;
        // An overlapping stay of the merged person is skipped at write time.
        $into->stays = [...$into->stays, ...$from->stays];
        $from->stays = [];
        // The branch a form stay lacked may come from the other half's roster.
        foreach ($into->stays as $i => $stay) {
            $into->stays[$i]['branch'] ??= $into->rosterBranches[(int) $stay['start']->format('Y')]
                ?? $into->rosterBranches[(int) $stay['end']->format('Y')]
                ?? $into->directBranch;
        }
        // Roster durations stand in for dates only when there are none.
        $into->notes = $into->hasDates() ? [] : array_values(array_unique([...$into->notes, ...$from->notes]));
    }

    /**
     * Adds the lines not already there, after what's there — never replacing.
     *
     * @param list<string>             $lines
     * @param callable(?string): mixed $set
     */
    private static function appendLines(?string $current, array $lines, callable $set): bool
    {
        $existing = null === $current ? [] : explode("\n", $current);
        $new = array_values(array_diff(array_unique($lines), $existing));
        if ([] === $new) {
            return false;
        }
        $set(implode("\n", [...$existing, ...$new]));

        return true;
    }

    /**
     * @param array<string, string> $words
     * @param list<string>          $names
     */
    private static function firstWord(string $raw, array $words, array $names): ?string
    {
        return self::allWords($raw, $words, $names)[0] ?? null;
    }

    /**
     * @param array<string, string> $words
     * @param list<string>          $names
     *
     * @return list<string>
     */
    private static function allWords(string $raw, array $words, array $names): array
    {
        $text = LegacyPeopleBuilder::normaliseName($raw);
        $found = [];
        foreach ($words as $word => $name) {
            if (in_array($name, $names, true) && (str_starts_with($text, $word) || str_contains($text, ' ' . $word) || $text === $word)) {
                $found[] = $name;
            }
        }

        return array_values(array_unique($found));
    }

    private static function country(string $raw): ?string
    {
        // "USA- New York", "Spain , Barcelona": the country comes first.
        $first = trim(explode(',', str_replace('-', ',', $raw))[0]);
        $names = [];
        foreach (Countries::getNames('en') as $code => $name) {
            $names[LegacyPeopleBuilder::normaliseName($name)] = $code;
        }
        foreach (array_unique([LegacyPeopleBuilder::normaliseName($raw), LegacyPeopleBuilder::normaliseName($first)]) as $text) {
            if ('' === $text) {
                continue;
            }
            if (null !== $code = self::COUNTRY_ALIASES[$text] ?? $names[$text] ?? null) {
                return $code;
            }
            foreach ($names as $name => $candidate) {
                // "Hong Kong" is "Hong Kong SAR China"; "Ausralia", "Luxemboorg" are typos.
                if (str_starts_with($name, $text . ' ') || (strlen($text) >= 5 && levenshtein($text, $name) <= 2)) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}
