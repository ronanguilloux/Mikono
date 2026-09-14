<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Stay;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Stay>
 */
final class StayFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Stay::class;
    }

    protected function defaults(): array
    {
        $today = new \DateTimeImmutable('today');

        return [
            'volunteer' => VolunteerFactory::new()->withoutStay(),
            // A seeded branch (ADR 0025), and not a random one: ProjectFactory
            // defaults to the same, so an activity's project and stay agree
            // unless a test says otherwise (ADR 0027).
            'branch' => BranchFactory::find(['name' => 'Nairobi (HQ)']),
            'startDate' => $today->modify('-1 month'),
            'endDate' => $today->modify('+1 month'),
        ];
    }

    public function past(): self
    {
        $today = new \DateTimeImmutable('today');

        return $this->with([
            'startDate' => $today->modify('-6 months'),
            'endDate' => $today->modify('-5 months'),
        ]);
    }
}
