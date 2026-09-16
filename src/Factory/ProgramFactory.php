<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Program;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Program>
 */
final class ProgramFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Program::class;
    }

    /**
     * Always-on by default, so an activity on any date fits it.
     */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->words(2, true),
            'project' => ProjectFactory::new(),
            'activityTypes' => [ActivityTypeFactory::new()],
        ];
    }
}
