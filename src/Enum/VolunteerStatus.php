<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Where a volunteer stands today, read from their stays and never stored.
 * See ADR 0026.
 *
 * Declared in sort order: VolunteerRepository ranks a volunteer by the
 * position of their status in cases(), so reordering these reorders /volunteers.
 */
enum VolunteerStatus: string
{
    case Present = 'present';
    case Upcoming = 'upcoming';
    case Past = 'past';
    case NoStay = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Upcoming => 'Upcoming',
            self::Past => 'Past',
            self::NoStay => 'No stay',
        };
    }

    /** The <twig:Pill> tone it is drawn in, on the profile and in lists. */
    public function tone(): string
    {
        return match ($this) {
            self::Present => 'accent',
            self::Upcoming => 'brand',
            self::Past, self::NoStay => 'neutral',
        };
    }
}
