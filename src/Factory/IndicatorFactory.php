<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Indicator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Indicator>
 */
final class IndicatorFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Indicator::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->words(3, true),
        ];
    }
}
