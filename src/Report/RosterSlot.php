<?php

declare(strict_types=1);

namespace App\Report;

/**
 * One volunteer's place in a roster group. `isLater` marks a volunteer already
 * listed under an earlier project the same day — the real WhatsApp messages
 * write that as "Rahel — later MVETI".
 */
final readonly class RosterSlot
{
    public function __construct(
        public string $volunteerName,
        public bool $isLater,
    ) {}
}
