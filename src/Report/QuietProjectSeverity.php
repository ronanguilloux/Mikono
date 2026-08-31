<?php

declare(strict_types=1);

namespace App\Report;

enum QuietProjectSeverity: string
{
    case Warn = 'warn';
    case Critical = 'critical';

    public static function forDays(int $days): self
    {
        return $days >= QuietProjectFinder::CRITICAL_AFTER_DAYS ? self::Critical : self::Warn;
    }
}
