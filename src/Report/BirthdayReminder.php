<?php

declare(strict_types=1);

namespace App\Report;

final readonly class BirthdayReminder
{
    public function __construct(
        public int $volunteerId,
        public string $firstName,
        public string $fullName,
        /** 0 for today, else one of BirthdayReminderFinder::DAYS_AHEAD. */
        public int $daysAway,
        public \DateTimeImmutable $date,
    ) {}
}
