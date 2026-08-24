<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Volunteer;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Volunteer>
 */
final class VolunteerFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Volunteer::class;
    }

    protected function defaults(): array
    {
        return [
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'email' => self::faker()->unique()->safeEmail(),
            'phone' => self::faker()->e164PhoneNumber(),
            'isActive' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->with(['isActive' => false]);
    }
}
