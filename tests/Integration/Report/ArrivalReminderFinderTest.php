<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Entity\Volunteer;
use App\Factory\StayFactory;
use App\Factory\VolunteerFactory;
use App\Report\ArrivalReminder;
use App\Report\ArrivalReminderFinder;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ArrivalReminderFinderTest extends KernelTestCase
{
    #[Test]
    public function remindsAWeekThreeDaysAndOneDayAhead(): void
    {
        self::bootKernel();
        foreach (['Today' => '2026-09-28', 'One' => '2026-09-29', 'Two' => '2026-09-30', 'Three' => '2026-10-01', 'Seven' => '2026-10-05', 'Eight' => '2026-10-06'] as $name => $start) {
            $this->stay($name, $start, '2026-12-31');
        }

        $reminders = $this->find('2026-09-28');

        self::assertSame(['One' => 1, 'Three' => 3, 'Seven' => 7], array_column(
            array_map(static fn(ArrivalReminder $r) => [$r->fullName, $r->daysAway], $reminders),
            1,
            0,
        ));
        self::assertSame('Nairobi (HQ)', $reminders[0]->branchName);
        self::assertSame('2026-12-31', $reminders[0]->endDate->format('Y-m-d'));
    }

    #[Test]
    public function aStayContinuingThePreviousOneIsNotAnArrival(): void
    {
        self::bootKernel();
        $continuing = $this->stay('Continuing', '2026-08-01', '2026-09-28');
        StayFactory::createOne(['volunteer' => $continuing, 'startDate' => new \DateTimeImmutable('2026-09-29'), 'endDate' => new \DateTimeImmutable('2026-10-31')]);
        $returning = $this->stay('Returning', '2026-08-01', '2026-09-27');
        StayFactory::createOne(['volunteer' => $returning, 'startDate' => new \DateTimeImmutable('2026-09-29'), 'endDate' => new \DateTimeImmutable('2026-10-31')]);

        $reminders = $this->find('2026-09-28');

        self::assertSame(['Returning'], array_map(static fn(ArrivalReminder $r) => $r->fullName, $reminders), 'a one-day gap is a new arrival');
    }

    /** Creates a volunteer, named $name only, with one stay; returns the volunteer. */
    private function stay(string $name, string $start, string $end): Volunteer
    {
        $volunteer = VolunteerFactory::new()->withoutStay()->create(['firstName' => $name, 'lastName' => null]);
        StayFactory::createOne(['volunteer' => $volunteer, 'startDate' => new \DateTimeImmutable($start), 'endDate' => new \DateTimeImmutable($end)]);

        return $volunteer;
    }

    /** @return list<ArrivalReminder> */
    private function find(string $today): array
    {
        $finder = self::getContainer()->get(ArrivalReminderFinder::class);
        self::assertInstanceOf(ArrivalReminderFinder::class, $finder);

        return $finder->find(new \DateTimeImmutable($today));
    }
}
