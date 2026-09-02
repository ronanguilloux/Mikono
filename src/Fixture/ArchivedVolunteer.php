<?php

declare(strict_types=1);

namespace App\Fixture;

/**
 * A volunteer as the VM's rosters name them: a first name, and whether they
 * are still coming. No surname, no email, no phone — the rosters have none,
 * and inventing them is what ADR 0012 forbids.
 */
final readonly class ArchivedVolunteer
{
    public function __construct(
        public string $name,
        public bool $active,
        public ?string $notes = null,
    ) {}
}
