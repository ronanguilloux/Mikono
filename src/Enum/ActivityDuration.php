<?php

declare(strict_types=1);

namespace App\Enum;

enum ActivityDuration: string
{
    case HalfDay = 'half_day';
    case FullDay = 'full_day';

    public function label(): string
    {
        return match ($this) {
            self::HalfDay => 'Half day',
            self::FullDay => 'Full day',
        };
    }

    public function toDays(): float
    {
        return match ($this) {
            self::HalfDay => 0.5,
            self::FullDay => 1.0,
        };
    }
}
