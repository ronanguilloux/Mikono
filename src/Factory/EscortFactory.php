<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Escort;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Escort>
 */
final class EscortFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Escort::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->name(),
            'isActive' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->with(['isActive' => false]);
    }
}
