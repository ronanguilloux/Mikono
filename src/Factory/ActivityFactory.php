<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Activity;
use App\Enum\ActivityDuration;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Activity>
 */
final class ActivityFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Activity::class;
    }

    protected function defaults(): array
    {
        return [
            'date' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-3 months', 'now')),
            'volunteer' => VolunteerFactory::new(),
            'project' => ProjectFactory::new(),
            'activityType' => ActivityTypeFactory::new(),
            'duration' => self::faker()->randomElement(ActivityDuration::cases()),
            'loggedBy' => UserFactory::new(),
        ];
    }
}
