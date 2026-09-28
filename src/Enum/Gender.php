<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Sensitive personal data (DPA 2019 s.2), held only to pair volunteers for
 * shared accommodation. See ADR 0037.
 */
enum Gender: string
{
    case Female = 'female';
    case Male = 'male';
    case Other = 'other';
    case PreferNotToSay = 'prefer_not_to_say';

    public function label(): string
    {
        return match ($this) {
            self::Female => 'Female',
            self::Male => 'Male',
            self::Other => 'Other',
            self::PreferNotToSay => 'Prefer not to say',
        };
    }
}
