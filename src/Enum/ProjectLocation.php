<?php

declare(strict_types=1);

namespace App\Enum;

enum ProjectLocation: string
{
    case Kibera = 'kibera';
    case Mombasa = 'mombasa';

    public function label(): string
    {
        return match ($this) {
            self::Kibera => 'Kibera (Nairobi)',
            self::Mombasa => 'Mombasa',
        };
    }
}
