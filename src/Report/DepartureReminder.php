<?php

declare(strict_types=1);

namespace App\Report;

final readonly class DepartureReminder
{
    public function __construct(
        public int $volunteerId,
        public string $firstName,
        public int $stayId,
        public string $branchName,
        /** 0 when the stay ends today, else calendar days to its last day. */
        public int $daysAway,
        public \DateTimeImmutable $endDate,
    ) {}
}
