<?php

declare(strict_types=1);

namespace App\Enum;

enum ActivityDuration: string
{
    case HalfDay = 'half_day';
    case FullDay = 'full_day';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::HalfDay => 'Half day',
            self::FullDay => 'Full day',
            self::Other => 'Other',
        };
    }

    public function toDays(): float
    {
        return match ($this) {
            self::HalfDay => 0.5,
            self::FullDay => 1.0,
            // Free-text value (Activity::$durationOther) isn't parsed into a
            // day count — reports undercount these entries until duration
            // reporting is revisited.
            self::Other => 0.0,
        };
    }
}
