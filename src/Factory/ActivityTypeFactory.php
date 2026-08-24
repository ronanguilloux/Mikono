<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\ActivityType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ActivityType>
 */
final class ActivityTypeFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ActivityType::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->words(2, true),
        ];
    }
}
