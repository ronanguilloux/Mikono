<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Source;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Source>
 */
final class SourceFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Source::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->words(3, true),
        ];
    }
}
