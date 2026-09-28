<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Skill;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Skill>
 */
final class SkillFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Skill::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->words(3, true),
        ];
    }
}
