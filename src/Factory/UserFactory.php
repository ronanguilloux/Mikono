<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return User::class;
    }

    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'fullName' => self::faker()->name(),
            'roles' => ['ROLE_USER'],
            'isActive' => true,
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (User $user): void {
            if ('' === $user->getPassword()) {
                $user->setPassword($this->passwordHasher->hashPassword($user, 'password-1234'));
            }
        });
    }

    public function admin(): self
    {
        return $this->with(['roles' => ['ROLE_ADMIN']]);
    }

    public function inactive(): self
    {
        return $this->with(['isActive' => false]);
    }
}
