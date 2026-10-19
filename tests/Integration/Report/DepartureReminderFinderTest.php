<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Factory\BranchFactory;
use App\Factory\StayFactory;
use App\Factory\VolunteerFactory;
use App\Report\DepartureReminder;
use App\Report\DepartureReminderFinder;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class DepartureReminderFinderTest extends KernelTestCase
{
    #[Test]
    public function remindsFromTwoWorkingDaysBeforeTheLastDayThroughIt(): void
    {
        self::bootKernel();
        // Thursday 22 October 2026: a stay ending Monday shows from today,
        // one ending Tuesday only from Friday.
        foreach (['Wednesday' => '2026-10-21', 'Thursday' => '2026-10-22', 'Friday' => '2026-10-23', 'Saturday' => '2026-10-24', 'Sunday' => '2026-10-25', 'Monday' => '2026-10-26', 'Tuesday' => '2026-10-27'] as $name => $end) {
            $this->stay($name, $end);
        }

        self::assertSame(
            ['Thursday' => 0, 'Friday' => 1, 'Saturday' => 2, 'Sunday' => 3, 'Monday' => 4],
            array_column(array_map(static fn(DepartureReminder $r) => [$r->firstName, $r->daysAway], $this->find('2026-10-22')), 1, 0),
        );
        // On the Wednesday, Friday's end is two working days away; Saturday's
        // (Thursday onwards) and Monday's are not shown yet.
        self::assertSame(['Wednesday', 'Thursday', 'Friday'], array_map(static fn(DepartureReminder $r) => $r->firstName, $this->find('2026-10-21')));
    }

    #[Test]
    public function aStayContinuedStraightIntoAnotherIsNoDeparture(): void
    {
        self::bootKernel();
        $volunteer = VolunteerFactory::new()->withoutStay()->create(['firstName' => 'Moving', 'lastName' => null]);
        StayFactory::createOne(['volunteer' => $volunteer, 'startDate' => new \DateTimeImmutable('2026-10-01'), 'endDate' => new \DateTimeImmutable('2026-10-23')]);
        StayFactory::createOne(['volunteer' => $volunteer, 'branch' => BranchFactory::find(['name' => 'Mombasa']), 'startDate' => new \DateTimeImmutable('2026-10-24'), 'endDate' => new \DateTimeImmutable('2026-11-30')]);

        self::assertSame([], $this->find('2026-10-22'));
    }

    private function stay(string $name, string $end): void
    {
        $volunteer = VolunteerFactory::new()->withoutStay()->create(['firstName' => $name, 'lastName' => null]);
        StayFactory::createOne(['volunteer' => $volunteer, 'startDate' => new \DateTimeImmutable('2026-10-01'), 'endDate' => new \DateTimeImmutable($end)]);
    }

    /** @return list<DepartureReminder> */
    private function find(string $today): array
    {
        $finder = self::getContainer()->get(DepartureReminderFinder::class);
        self::assertInstanceOf(DepartureReminderFinder::class, $finder);

        return $finder->find(new \DateTimeImmutable($today));
    }
}
