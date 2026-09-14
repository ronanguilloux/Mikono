<?php

declare(strict_types=1);

namespace App\Fixture;

use App\Enum\ProjectOwnership;

/**
 * A site the rosters send volunteers to, plus the project metadata the
 * rosters themselves don't carry (branch, ownership, partner name), which
 * comes from the project records established with the VM in
 * docs/brainstorm/04. `branch` is the name of a row the branch migration
 * seeds (ADR 0025).
 *
 * `activityType` is the work that site means — "Peggy Lucas school" is
 * school support, "ACK clinic" is clinic support. The rosters name the place
 * and leave the rest understood.
 */
final readonly class ArchivedProject
{
    public function __construct(
        public string $key,
        public string $name,
        public string $branch,
        public ProjectOwnership $ownership,
        public ?string $partner,
        public string $activityType,
    ) {}
}
