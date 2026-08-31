<?php

declare(strict_types=1);

namespace App\Report;

/**
 * One project + activity type block of a day's roster, with everyone attending
 * it and whoever is accompanying them. Mirrors how the VM's own roster messages
 * are laid out: a site heading, the volunteers under it, then the escort line.
 */
final readonly class RosterGroup
{
    /**
     * @param list<RosterSlot> $slots
     * @param list<string>     $escortNames
     */
    public function __construct(
        public string $projectName,
        public string $activityTypeName,
        public array $slots,
        public array $escortNames,
    ) {}
}
