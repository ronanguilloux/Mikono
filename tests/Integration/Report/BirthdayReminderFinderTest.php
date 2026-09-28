<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Factory\VolunteerFactory;
use App\Report\BirthdayReminder;
use App\Report\BirthdayReminderFinder;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class BirthdayReminderFinderTest extends KernelTestCase
{
    #[Test]
    public function remindsAWeekThreeDaysAndOneDayAheadAndOnTheDay(): void
    {
        self::bootKernel();
        foreach (['Today' => '1990-09-28', 'One' => '1990-09-29', 'Two' => '1990-09-30', 'Three' => '1990-10-01', 'Four' => '1990-10-02', 'Seven' => '1990-10-05', 'Eight' => '1990-10-06', 'Past' => '1990-09-27'] as $name => $dateOfBirth) {
            VolunteerFactory::createOne(['firstName' => $name, 'lastName' => null, 'dateOfBirth' => new \DateTimeImmutable($dateOfBirth)]);
        }
        VolunteerFactory::createOne(['firstName' => 'Unknown', 'dateOfBirth' => null]);

        $reminders = $this->find('2026-09-28');

        self::assertSame(['Today' => 0, 'One' => 1, 'Three' => 3, 'Seven' => 7], array_column(
            array_map(static fn(BirthdayReminder $r) => [$r->firstName, $r->daysAway], $reminders),
            1,
            0,
        ));
        self::assertSame('2026-10-05', $reminders[3]->date->format('Y-m-d'));
    }

    #[Test]
    public function inactiveVolunteersAreRemindedToo(): void
    {
        self::bootKernel();
        VolunteerFactory::new()->inactive()->create(['firstName' => 'Former', 'dateOfBirth' => new \DateTimeImmutable('1985-09-28')]);

        self::assertCount(1, $this->find('2026-09-28'));
    }

    #[Test]
    public function aBirthdayEarlyInJanuaryIsSeenFromLateDecember(): void
    {
        self::bootKernel();
        VolunteerFactory::createOne(['firstName' => 'Newyear', 'dateOfBirth' => new \DateTimeImmutable('2000-01-04')]);

        $reminders = $this->find('2026-12-28');

        self::assertCount(1, $reminders);
        self::assertSame(7, $reminders[0]->daysAway);
        self::assertSame('2027-01-04', $reminders[0]->date->format('Y-m-d'));
    }

    #[Test]
    public function aLeapDayBirthdayFallsOnTheTwentyEighthInACommonYear(): void
    {
        self::bootKernel();
        VolunteerFactory::createOne(['firstName' => 'Leap', 'dateOfBirth' => new \DateTimeImmutable('2000-02-29')]);

        self::assertSame(0, $this->find('2027-02-28')[0]->daysAway);
        self::assertSame(1, $this->find('2028-02-28')[0]->daysAway, 'a leap year has the real day');
        self::assertSame(0, $this->find('2028-02-29')[0]->daysAway);
    }

    /** @return list<BirthdayReminder> */
    private function find(string $today): array
    {
        $finder = self::getContainer()->get(BirthdayReminderFinder::class);
        self::assertInstanceOf(BirthdayReminderFinder::class, $finder);

        return $finder->find(new \DateTimeImmutable($today));
    }
}
