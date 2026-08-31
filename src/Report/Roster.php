<?php

declare(strict_types=1);

namespace App\Report;

/**
 * A single day's schedule, grouped the way the VM already communicates it.
 */
final readonly class Roster
{
    /** @param list<RosterGroup> $groups */
    public function __construct(
        public \DateTimeImmutable $date,
        public array $groups,
    ) {}

    public function isEmpty(): bool
    {
        return [] === $this->groups;
    }

    /**
     * Distinct projects, not group count — a project running two different
     * activity types in one day is still one place to be.
     */
    public function projectCount(): int
    {
        $names = [];
        foreach ($this->groups as $group) {
            $names[$group->projectName] = true;
        }

        return count($names);
    }

    /** Volunteer slots, counting a volunteer at two sites in one day twice. */
    public function slotCount(): int
    {
        $count = 0;
        foreach ($this->groups as $group) {
            $count += count($group->slots);
        }

        return $count;
    }

    /**
     * The schedule body of the VM's nightly WhatsApp message — deliberately
     * without a greeting. Every real message opens in Edna's own voice with a
     * quote or a personal line, so the app generates the part that's actually
     * data and leaves the opening to her.
     */
    public function toWhatsAppText(): string
    {
        $blocks = ['📅 ' . $this->date->format('l, j F Y')];

        foreach ($this->groups as $group) {
            $lines = [sprintf('📍 %s (%s)', $group->projectName, $group->activityTypeName)];

            foreach ($group->slots as $slot) {
                $lines[] = '- ' . $slot->volunteerName . ($slot->isLater ? ' (later)' : '');
            }

            if ([] !== $group->escortNames) {
                $lines[] = 'Accompanied by: ' . implode(', ', $group->escortNames);
            }

            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks);
    }
}
