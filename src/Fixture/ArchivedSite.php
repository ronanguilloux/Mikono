<?php

declare(strict_types=1);

namespace App\Fixture;

use App\Enum\ActivityDuration;

/**
 * One block of a roster message: a site, who is going, and who accompanies
 * them. An empty `escorts` means the archive doesn't say — either nobody did,
 * or WhatsApp cut the message off before the "Accompanied by" line.
 */
final readonly class ArchivedSite
{
    /**
     * @param list<ArchivedSlot> $volunteers
     * @param list<string>       $escorts
     */
    public function __construct(
        public string $projectKey,
        public array $volunteers,
        public array $escorts,
        public ActivityDuration $duration,
    ) {}
}
