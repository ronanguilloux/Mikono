<?php

declare(strict_types=1);

namespace App\Report;

use App\Entity\Program;
use App\Entity\Skill;

/**
 * A program on /matches: how much it needs people, and who could go. See
 * ADR 0042.
 */
final readonly class ProgramMatches
{
    public function __construct(
        public Program $program,
        /** Its latest activity, planned ones included; null when it has none. */
        public ?\DateTimeImmutable $lastActivity,
        /** Days from the latest activity to today, negative when it is planned; null when it has none. */
        public ?int $daysSinceLastActivity,
        /** Its start date is after today. */
        public bool $notStarted,
        /** @var list<Skill> the program's skills no candidate holds */
        public array $uncoveredSkills,
        /** @var list<VolunteerMatch> best first */
        public array $candidates,
    ) {}
}
