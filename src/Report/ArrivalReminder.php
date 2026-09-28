<?php

declare(strict_types=1);

namespace App\Report;

final readonly class ArrivalReminder
{
    public function __construct(
        public int $volunteerId,
        public string $fullName,
        public string $branchName,
        /** One of ArrivalReminderFinder::DAYS_AHEAD. */
        public int $daysAway,
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
    ) {}
}
