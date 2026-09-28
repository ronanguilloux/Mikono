<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Achievement;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Achievement>
 */
final class AchievementFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Achievement::class;
    }

    protected function defaults(): array
    {
        return [
            // StayFactory's stay covers today, and ProjectFactory's project is
            // at the same branch, so the default achievement fits (ADR 0038).
            'stay' => StayFactory::new(),
            'project' => ProjectFactory::new(),
            'title' => self::faker()->sentence(3),
            'achievedOn' => new \DateTimeImmutable('today'),
        ];
    }
}
