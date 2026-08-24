<?php

declare(strict_types=1);

namespace App\Enum;

enum ProjectOwnership: string
{
    case Ucesco = 'ucesco';
    case Partner = 'partner';

    public function label(): string
    {
        return match ($this) {
            self::Ucesco => 'UCESCO-owned',
            self::Partner => 'Partner organization',
        };
    }
}
