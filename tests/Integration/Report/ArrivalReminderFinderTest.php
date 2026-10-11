<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Entity\Volunteer;
use App\Factory\BranchFactory;
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
    public function remindsEveryDayOfTheWeekAhead(): void
    {
        self::bootKernel();
        foreach (['Today' => '2026-09-28', 'One' => '2026-09-29', 'Two' => '2026-09-30', 'Four' => '2026-10-02', 'Seven' => '2026-10-05', 'Eight' => '2026-10-06'] as $name => $start) {
            $this->stay($name, $start, '2026-12-31');
        }

        $reminders = $this->find('2026-09-28');

        self::assertSame(['One' => 1, 'Two' => 2, 'Four' => 4, 'Seven' => 7], array_column(
            array_map(static fn(ArrivalReminder $r) => [$r->fullName, $r->daysAway], $reminders),
            1,
            0,
        ));
        self::assertSame('Nairobi (HQ)', $reminders[0]->branchName);
        self::assertSame('2026-12-31', $reminders[0]->endDate->format('Y-m-d'));
    }

    #[Test]
    public function aStayContinuingThePreviousOneAtAnotherBranchIsAnArrival(): void
    {
        self::bootKernel();
        $moving = $this->stay('Moving', '2026-08-01', '2026-09-28');
        StayFactory::createOne(['volunteer' => $moving, 'branch' => BranchFactory::find(['name' => 'Mombasa']), 'startDate' => new \DateTimeImmutable('2026-09-29'), 'endDate' => new \DateTimeImmutable('2026-10-31')]);

        $reminders = $this->find('2026-09-28');

        self::assertCount(1, $reminders);
        self::assertSame('Mombasa', $reminders[0]->branchName);
    }

    #[Test]
    public function sortsByBranchThenDaysAwayThenName(): void
    {
        self::bootKernel();
        foreach ([['Zawadi', 'Nairobi (HQ)', '2026-09-29'], ['Baraka', 'Mombasa', '2026-10-02'], ['Amani', 'Mombasa', '2026-10-02'], ['Chausiku', 'Mombasa', '2026-09-30']] as [$name, $branch, $start]) {
            $volunteer = VolunteerFactory::new()->withoutStay()->create(['firstName' => $name, 'lastName' => null]);
            StayFactory::createOne(['volunteer' => $volunteer, 'branch' => BranchFactory::find(['name' => $branch]), 'startDate' => new \DateTimeImmutable($start), 'endDate' => new \DateTimeImmutable('2026-12-31')]);
        }

        self::assertSame(
            ['Mombasa 2 Chausiku', 'Mombasa 4 Amani', 'Mombasa 4 Baraka', 'Nairobi (HQ) 1 Zawadi'],
            array_map(static fn(ArrivalReminder $r) => "{$r->branchName} {$r->daysAway} {$r->fullName}", $this->find('2026-09-28')),
        );
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
