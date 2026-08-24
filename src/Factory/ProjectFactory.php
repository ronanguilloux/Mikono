<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Project;
use App\Enum\ProjectLocation;
use App\Enum\ProjectOwnership;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Project>
 */
final class ProjectFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Project::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->company(),
            'location' => self::faker()->randomElement(ProjectLocation::cases()),
            'ownership' => ProjectOwnership::Ucesco,
            'isActive' => true,
        ];
    }

    public function partner(): self
    {
        return $this->with([
            'ownership' => ProjectOwnership::Partner,
            'partnerOrganizationName' => self::faker()->company(),
        ]);
    }

    public function inactive(): self
    {
        return $this->with(['isActive' => false]);
    }
}
