<?php

declare(strict_types=1);

namespace App\Report;

final readonly class QuietProject
{
    public function __construct(
        public int $id,
        public string $name,
        public int $days,
        public QuietProjectSeverity $severity,
        /** Null when nothing has ever been logged against the project. */
        public ?\DateTimeImmutable $lastActivity,
    ) {}

    public function hasNeverBeenUsed(): bool
    {
        return null === $this->lastActivity;
    }
}
