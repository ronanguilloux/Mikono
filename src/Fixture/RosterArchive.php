<?php

declare(strict_types=1);

namespace App\Fixture;

use App\Enum\ActivityDuration;
use App\Enum\ProjectOwnership;
use Symfony\Component\Yaml\Yaml;

/**
 * The real UCESCO roster archive — `docs/fixtures/rosters.yaml`, transcribed
 * by hand from the VM's WhatsApp roster messages.
 *
 * Its `programs:` section comes from a second real source, UCESCO's public
 * Volunteer World listing, since the rosters never name a program. Nothing
 * `AppStory` seeds is generated: no invented people, sites, programs or
 * dates. See ADR 0012, and `docs/fixtures/README.md` for the transcription
 * rules the file obeys.
 *
 * Everything here fails loudly on a malformed file rather than seeding a
 * half-empty database — a fixture that quietly skips rows is worse than one
 * that refuses to load.
 */
final readonly class RosterArchive
{
    public const string DEFAULT_PATH = 'docs/fixtures/rosters.yaml';

    /**
     * @param list<ArchivedVolunteer>        $volunteers
     * @param list<string>                   $escorts
     * @param array<string, ArchivedProject> $projects   keyed by the YAML's project key
     * @param list<ArchivedRoster>           $rosters
     * @param list<ArchivedProgram>          $programs
     */
    private function __construct(
        public array $volunteers,
        public array $escorts,
        public array $projects,
        public array $rosters,
        public array $programs,
    ) {}

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Roster archive not found at "%s".', $path));
        }

        $parsed = Yaml::parseFile($path, Yaml::PARSE_DATETIME);
        if (!is_array($parsed)) {
            throw new \RuntimeException(sprintf('Roster archive "%s" is not a YAML mapping.', $path));
        }

        $volunteers = [];
        foreach (self::rows($parsed, 'volunteers') as $row) {
            $volunteers[] = new ArchivedVolunteer(
                self::string($row, 'name'),
                self::bool($row, 'active'),
                self::nullableString($row, 'notes'),
            );
        }

        $projects = [];
        foreach (self::rows($parsed, 'projects') as $row) {
            $key = self::string($row, 'key');
            $projects[$key] = new ArchivedProject(
                $key,
                self::string($row, 'name'),
                self::string($row, 'branch'),
                ProjectOwnership::from(self::string($row, 'ownership')),
                self::nullableString($row, 'partner'),
                self::nullableString($row, 'activity_type'),
            );
        }

        $programs = [];
        foreach (self::rows($parsed, 'programs') as $row) {
            $name = self::string($row, 'name');
            $projectKey = self::string($row, 'project');
            if (!isset($projects[$projectKey])) {
                throw new \RuntimeException(sprintf('Program "%s" references unknown project "%s".', $name, $projectKey));
            }

            $activityTypes = self::strings($row, 'activity_types');
            if ([] === $activityTypes) {
                throw new \RuntimeException(sprintf('Program "%s" offers no activity type.', $name));
            }

            $startDate = self::nullableDate($row, 'start');
            $endDate = self::nullableDate($row, 'end');
            if (null !== $startDate && null !== $endDate && $endDate < $startDate) {
                throw new \RuntimeException(sprintf('Program "%s" ends before it starts.', $name));
            }

            $programs[] = new ArchivedProgram(
                $name,
                $projectKey,
                $activityTypes,
                self::nullableString($row, 'suggested_roles'),
                $startDate,
                $endDate,
            );
        }

        $rosters = [];
        foreach (self::rows($parsed, 'rosters') as $row) {
            $sites = [];
            foreach (self::rows($row, 'sites') as $site) {
                $slots = [];
                foreach (self::rows($site, 'volunteers') as $slot) {
                    $slots[] = new ArchivedSlot(
                        self::string($slot, 'name'),
                        self::nullableString($slot, 'note'),
                    );
                }

                $projectKey = self::string($site, 'project');
                if (!isset($projects[$projectKey])) {
                    throw new \RuntimeException(sprintf('Roster references unknown project "%s".', $projectKey));
                }

                if (null === $projects[$projectKey]->activityType) {
                    throw new \RuntimeException(sprintf('Roster sends volunteers to project "%s", which has no activity_type.', $projectKey));
                }

                $duration = self::nullableString($site, 'duration');
                $sites[] = new ArchivedSite(
                    $projectKey,
                    $slots,
                    self::strings($site, 'escorts'),
                    null === $duration ? ActivityDuration::HalfDay : ActivityDuration::from($duration),
                );
            }

            $anchor = self::nullableString($row, 'anchor');
            if (null !== $anchor && !in_array($anchor, ['today', 'tomorrow'], true)) {
                throw new \RuntimeException(sprintf('Unknown roster anchor "%s".', $anchor));
            }

            $rosters[] = new ArchivedRoster(
                self::date($row, 'date'),
                $anchor,
                $sites,
            );
        }

        return new self($volunteers, self::strings($parsed, 'escorts'), $projects, $rosters, $programs);
    }

    /**
     * The listed program at a project that offers a type, if there is one.
     *
     * A roster activity takes this program; when there is none, the project's
     * generated always-on program stands in (`AppStory`). Two listed programs
     * matching would leave the activity's program a guess, so that fails.
     */
    public function listedProgramFor(string $projectKey, string $activityType): ?ArchivedProgram
    {
        $matches = array_values(array_filter(
            $this->programs,
            static fn(ArchivedProgram $program) => $program->projectKey === $projectKey
                && \in_array($activityType, $program->activityTypes, true),
        ));

        if (\count($matches) > 1) {
            throw new \RuntimeException(sprintf('Several programs at project "%s" offer "%s"; a roster activity there has no single program.', $projectKey, $activityType));
        }

        return $matches[0] ?? null;
    }

    /**
     * The archive day that `anchor: today` pins onto the day the fixtures load.
     *
     * Every roster shifts by that one offset, so the archive keeps its own
     * shape. Moving only the anchored days instead would tear a hole in the
     * timeline (and stack two rosters on one day) as soon as the fixtures are
     * loaded on any date but the anchor's own.
     */
    public function anchorDay(): \DateTimeImmutable
    {
        foreach ($this->rosters as $roster) {
            if ('today' === $roster->anchor) {
                return $roster->date;
            }
        }

        throw new \RuntimeException('No roster in the archive carries "anchor: today".');
    }

    /**
     * @param array<mixed> $row
     *
     * @return list<array<mixed>>
     */
    private static function rows(array $row, string $key): array
    {
        $value = $row[$key] ?? null;
        if (!is_array($value)) {
            throw new \RuntimeException(sprintf('Expected a list at "%s".', $key));
        }

        $rows = [];
        foreach ($value as $entry) {
            if (!is_array($entry)) {
                throw new \RuntimeException(sprintf('Expected every entry under "%s" to be a mapping.', $key));
            }

            $rows[] = $entry;
        }

        return $rows;
    }

    /**
     * @param array<mixed> $row
     *
     * @return list<string>
     */
    private static function strings(array $row, string $key): array
    {
        $value = $row[$key] ?? [];
        if (!is_array($value)) {
            throw new \RuntimeException(sprintf('Expected a list of strings at "%s".', $key));
        }

        $strings = [];
        foreach ($value as $entry) {
            if (!is_string($entry)) {
                throw new \RuntimeException(sprintf('Expected every entry under "%s" to be a string.', $key));
            }

            $strings[] = $entry;
        }

        return $strings;
    }

    /** @param array<mixed> $row */
    private static function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || '' === $value) {
            throw new \RuntimeException(sprintf('Expected a non-empty string at "%s".', $key));
        }

        return $value;
    }

    /** @param array<mixed> $row */
    private static function nullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if (null === $value) {
            return null;
        }

        if (!is_string($value)) {
            throw new \RuntimeException(sprintf('Expected a string or nothing at "%s".', $key));
        }

        return $value;
    }

    /** @param array<mixed> $row */
    private static function bool(array $row, string $key): bool
    {
        $value = $row[$key] ?? null;
        if (!is_bool($value)) {
            throw new \RuntimeException(sprintf('Expected a boolean at "%s".', $key));
        }

        return $value;
    }

    /** @param array<mixed> $row */
    private static function nullableDate(array $row, string $key): ?\DateTimeImmutable
    {
        return null === ($row[$key] ?? null) ? null : self::date($row, $key);
    }

    /** @param array<mixed> $row */
    private static function date(array $row, string $key): \DateTimeImmutable
    {
        $value = $row[$key] ?? null;
        if (!$value instanceof \DateTimeInterface) {
            throw new \RuntimeException(sprintf('Expected a YYYY-MM-DD date at "%s".', $key));
        }

        // Rebuilt in the app's own timezone: the archive records a day, not a
        // moment, and Africa/Nairobi is where every one of these days happened.
        return new \DateTimeImmutable($value->format('Y-m-d'));
    }
}
