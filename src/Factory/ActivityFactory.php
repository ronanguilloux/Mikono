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
            // Midnight, not the time faker hands back: Activity::$date is a
            // `date_immutable` column, so the time never survives the round
            // trip. Left on, a just-created entity carries a time its own
            // hydrated row does not, and the two disagree — which flipped
            // /reports' "Planned" badge (mostRecent > today, today being
            // midnight) for any activity faker happened to date today.
            'date' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-3 months', 'now'))->setTime(0, 0),
            'volunteer' => VolunteerFactory::new(),
            'project' => ProjectFactory::new(),
            'activityType' => ActivityTypeFactory::new(),
            // Excludes ActivityDuration::Other, which needs a companion
            // durationOther value — set it explicitly via ->with() when a
            // test needs an "Other" duration.
            'duration' => self::faker()->randomElement([ActivityDuration::HalfDay, ActivityDuration::FullDay]),
            'loggedBy' => UserFactory::new(),
        ];
    }
}
