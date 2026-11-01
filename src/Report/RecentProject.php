<?php

declare(strict_types=1);

namespace App\Report;

final readonly class RecentProject
{
    public function __construct(
        public int $id,
        public string $name,
        /** Zero when it is today or planned, as QuietProject counts it. */
        public int $days,
        public \DateTimeImmutable $lastActivity,
        /** Its latest activity is still to come. */
        public bool $planned,
    ) {}
}
