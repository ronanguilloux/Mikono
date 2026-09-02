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

    /**
     * The escort names as the VM writes them — "Edna and Sam", not
     * "Edna, Sam". Both roster read paths exist to be copied back into
     * WhatsApp, so they match her wording rather than a list separator.
     */
    public function escortLine(): string
    {
        if (count($this->escortNames) < 2) {
            return implode('', $this->escortNames);
        }

        return implode(', ', array_slice($this->escortNames, 0, -1))
            . ' and ' . $this->escortNames[count($this->escortNames) - 1];
    }
}
