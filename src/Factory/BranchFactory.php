<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Branch;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Branch>
 */
final class BranchFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Branch::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->city(),
            'physicalLocation' => self::faker()->streetAddress(),
            'isActive' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->with(['isActive' => false]);
    }
}
