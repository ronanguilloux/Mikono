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
            // One of the five branches the migration seeds (ADR 0025).
            'branch' => BranchFactory::random(),
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
