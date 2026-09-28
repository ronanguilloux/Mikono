<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Factory\AchievementFactory;
use App\Factory\StayFactory;
use App\Factory\VolunteerFactory;
use App\Report\AnniversaryReminder;
use App\Report\AnniversaryReminderFinder;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class AnniversaryReminderFinderTest extends KernelTestCase
{
    #[Test]
    public function remindsOfWholeYearAnniversariesTodayAndTomorrow(): void
    {
        self::bootKernel();
        foreach (['One' => '2025-09-28', 'Three' => '2023-09-28', 'Tomorrow' => '2024-09-29', 'SameYear' => '2026-09-28', 'Yesterday' => '2025-09-27', 'OtherMonth' => '2025-10-28'] as $name => $achievedOn) {
            $this->achievement($name, $achievedOn);
        }

        $reminders = $this->find('2026-09-28');

        self::assertSame(
            [['One', 1, 0], ['Three', 3, 0], ['Tomorrow', 2, 1]],
            array_map(static fn(AnniversaryReminder $r) => [$r->firstName, $r->years, $r->daysAway], $reminders),
        );
        self::assertSame('Nairobi (HQ)', $reminders[0]->branchName);
        self::assertSame('Title of One', $reminders[0]->title);
    }

    #[Test]
    public function aLeapDayAchievementFallsOnThe28thInANonLeapYear(): void
    {
        self::bootKernel();
        $this->achievement('Leap', '2024-02-29');

        self::assertSame([1], array_map(static fn(AnniversaryReminder $r) => $r->years, $this->find('2025-02-28')));
        self::assertSame([1], array_map(static fn(AnniversaryReminder $r) => $r->daysAway, $this->find('2025-02-27')));
        self::assertSame([1], array_map(static fn(AnniversaryReminder $r) => $r->daysAway, $this->find('2028-02-28')), 'a leap year keeps the 29th');
        self::assertSame([4], array_map(static fn(AnniversaryReminder $r) => $r->years, $this->find('2028-02-29')));
    }

    private function achievement(string $name, string $achievedOn): void
    {
        AchievementFactory::createOne([
            'stay' => StayFactory::new(['volunteer' => VolunteerFactory::new()->withoutStay()->with(['firstName' => $name, 'lastName' => null])]),
            'title' => 'Title of ' . $name,
            'achievedOn' => new \DateTimeImmutable($achievedOn),
        ]);
    }

    /** @return list<AnniversaryReminder> */
    private function find(string $today): array
    {
        $finder = self::getContainer()->get(AnniversaryReminderFinder::class);
        self::assertInstanceOf(AnniversaryReminderFinder::class, $finder);

        return $finder->find(new \DateTimeImmutable($today));
    }
}
