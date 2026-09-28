<?php

declare(strict_types=1);

namespace App\Report;

final readonly class AnniversaryReminder
{
    public function __construct(
        public int $volunteerId,
        public string $firstName,
        public string $title,
        public int $projectId,
        public string $projectName,
        public string $branchName,
        /** Whole years since the achievement, at least 1. */
        public int $years,
        /** One of AnniversaryReminderFinder::DAYS_AHEAD. */
        public int $daysAway,
    ) {}
}
