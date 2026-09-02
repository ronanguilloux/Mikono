<?php

declare(strict_types=1);

namespace App\Fixture;

/**
 * One volunteer on one roster line, with whatever the VM appended to it —
 * "To go with the kids to the sports", "Later MVETI". Those notes are the
 * reason a roster line isn't just a name.
 */
final readonly class ArchivedSlot
{
    public function __construct(
        public string $name,
        public ?string $note = null,
    ) {}
}
