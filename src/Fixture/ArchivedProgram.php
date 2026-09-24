<?php

declare(strict_types=1);

namespace App\Fixture;

/**
 * A program UCESCO runs, transcribed from its public Volunteer World listing
 * rather than from the rosters, which never name one (ADR 0012, ADR 0030).
 *
 * The listing places a program in a city, never at a site, so `projectKey`
 * is the branch's hub project unless another source names the site (see
 * `docs/fixtures/README.md`). No dates yet: the listing's "1–50 weeks" is how
 * long a volunteer may stay, not when the program runs.
 */
final readonly class ArchivedProgram
{
    /**
     * @param list<string> $activityTypes
     */
    public function __construct(
        public string $name,
        public string $projectKey,
        public array $activityTypes,
        public ?string $suggestedRoles,
        public ?\DateTimeImmutable $startDate,
        public ?\DateTimeImmutable $endDate,
    ) {}
}
