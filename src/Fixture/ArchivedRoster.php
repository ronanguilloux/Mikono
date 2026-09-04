<?php

declare(strict_types=1);

namespace App\Fixture;

/**
 * One evening's schedule: the day it covers, and the sites it sends people to.
 *
 * `anchor: today` marks the archive day the fixtures pin onto the day they are
 * loaded (`anchor: tomorrow` marks the one after it, for readability). Every
 * roster then shifts by that same offset — see `RosterArchive::anchorDay()`.
 */
final readonly class ArchivedRoster
{
    /** @param list<ArchivedSite> $sites */
    public function __construct(
        public \DateTimeImmutable $date,
        public ?string $anchor,
        public array $sites,
    ) {}
}
