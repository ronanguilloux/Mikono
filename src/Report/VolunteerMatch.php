<?php

declare(strict_types=1);

namespace App\Report;

use App\Entity\Skill;
use App\Entity\Stay;
use App\Entity\Volunteer;

/**
 * One candidate for a program on /matches, and why. See ADR 0042.
 */
final readonly class VolunteerMatch
{
    public function __construct(
        public Volunteer $volunteer,
        /** The stay that puts them at the program's branch during its dates. */
        public Stay $stay,
        /** True when that stay covers today, false when it starts later. */
        public bool $present,
        /** @var list<Skill> the program's skills they hold */
        public array $matchedSkills,
        /** @var list<Skill> the program's skills they don't */
        public array $missingSkills,
        /** @var array<string, int> activity type name => times done, any program, the program's types only */
        public array $experience,
        /** Of those, how many were in this program. */
        public int $activitiesInProgram,
        /** The latest of them; null with no experience. */
        public ?\DateTimeImmutable $lastExperience,
        /** @var list<\DateTimeImmutable> days of the stay, from today, they already have an activity on */
        public array $booked = [],
    ) {}

    public function experienceCount(): int
    {
        return array_sum($this->experience);
    }

    /** Suggested on past activities alone: none of the program's skills is on their profile. */
    public function isByExperienceOnly(): bool
    {
        return [] === $this->matchedSkills;
    }
}
