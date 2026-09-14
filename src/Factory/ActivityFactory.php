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

    /**
     * Every activity needs the stay covering its date (ADR 0026). Left unset,
     * it is the volunteer's stay on that day, or else a one-day stay on it at
     * the project's branch — a single day nothing covers can't overlap
     * another stay.
     */
    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (Activity $activity): void {
            $volunteer = $activity->getVolunteer();
            $date = $activity->getDate();

            if (null !== $activity->getStay() || null === $volunteer || null === $date) {
                return;
            }

            $activity->setStay($volunteer->getStayCovering($date) ?? StayFactory::createOne(array_filter([
                'volunteer' => $volunteer,
                'branch' => $activity->getProject()?->getBranch(),
                'startDate' => $date,
                'endDate' => $date,
            ])));
        });
    }
}
