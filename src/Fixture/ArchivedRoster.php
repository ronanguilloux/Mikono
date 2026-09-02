<?php

declare(strict_types=1);

namespace App\Fixture;

/**
 * One evening's schedule: the day it covers, and the sites it sends people to.
 *
 * `anchor` is set on the archive's last two days ("today" / "tomorrow"). The
 * home screen's roster panels are date-relative, so those two are shifted onto
 * the day the fixtures are loaded — the calendar moves, the people don't.
 */
final readonly class ArchivedRoster
{
    /** @param list<ArchivedSite> $sites */
    public function __construct(
        public \DateTimeImmutable $date,
        public ?string $anchor,
        public array $sites,
    ) {}

    public function dateRelativeTo(\DateTimeImmutable $today): \DateTimeImmutable
    {
        return match ($this->anchor) {
            'today' => $today,
            'tomorrow' => $today->modify('+1 day'),
            null => $this->date,
            default => throw new \RuntimeException(sprintf('Unknown roster anchor "%s".', $this->anchor)),
        };
    }
}
