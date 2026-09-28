<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\BeneficiaryGroup;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<BeneficiaryGroup>
 */
final class BeneficiaryGroupFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return BeneficiaryGroup::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->words(3, true),
        ];
    }
}
