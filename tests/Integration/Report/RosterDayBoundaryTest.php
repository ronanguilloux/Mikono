<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\ProjectFactory;
use App\Factory\VolunteerFactory;
use App\Report\RosterBuilder;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Pins the day boundary the home screen's rosters depend on.
 *
 * `DashboardController` resolves `new \DateTimeImmutable('today')` against
 * PHP's default timezone, which `frankenphp/conf.d/10-app.ini` sets to
 * `Africa/Nairobi` — every user of this app is in Kenya. Under UTC the app
 * still runs, still passes every other test, and quietly shows *yesterday's*
 * roster between 00:00 and 03:00 EAT, which is exactly when a VM checking the
 * day's schedule before an early start would look. Nothing else fails if that
 * setting is dropped or reverted, so this test is the only thing holding it.
 *
 * Note that `10-app.ini` is copied into the image rather than bind-mounted, so
 * an edit to it only reaches this test after a `docker compose build php`.
 */
#[ResetDatabase]
final class RosterDayBoundaryTest extends KernelTestCase
{
    #[Test]
    public function theApplicationRunsOnNairobiTime(): void
    {
        self::assertSame(
            'Africa/Nairobi',
            date_default_timezone_get(),
            'date.timezone must stay Africa/Nairobi (frankenphp/conf.d/10-app.ini) — '
            . 'the home screen resolves "today" against it.',
        );
    }

    /**
     * Guards the stand-in below: it only proves anything about the real
     * controller for as long as it computes what `'today'` computes.
     */
    #[Test]
    public function theStandInForTodayMatchesWhatPhpItselfResolves(): void
    {
        self::assertEquals(
            new \DateTimeImmutable('today'),
            self::todayAt(new \DateTimeImmutable()),
        );
    }

    #[Test]
    public function atOneInTheMorningTheRosterIsAlreadyTheNewDays(): void
    {
        self::bootKernel();
        // 01:00 EAT on Sunday 30 August — the hour the UTC bug was invisible in.
        $earlyMorning = new \DateTimeImmutable('2026-08-29 22:00:00', new \DateTimeZone('UTC'));

        $this->activityOn('2026-08-29', 'Beyond Zero clinic');
        $this->activityOn('2026-08-30', 'Peggy Lucas school');

        $roster = $this->builder()->buildFor(self::todayAt($earlyMorning));

        self::assertSame('2026-08-30', $roster->date->format('Y-m-d'));
        self::assertCount(1, $roster->groups);
        self::assertSame('Peggy Lucas school', $roster->groups[0]->projectName);
    }

    #[Test]
    public function tomorrowsRosterMovesWithIt(): void
    {
        self::bootKernel();
        $earlyMorning = new \DateTimeImmutable('2026-08-29 22:00:00', new \DateTimeZone('UTC'));

        $this->activityOn('2026-08-30', 'Peggy Lucas school');
        $this->activityOn('2026-08-31', 'MVETI');

        $roster = $this->builder()->buildFor(self::todayAt($earlyMorning)->modify('+1 day'));

        self::assertSame('2026-08-31', $roster->date->format('Y-m-d'));
        self::assertSame('MVETI', $roster->groups[0]->projectName);
    }

    /**
     * The other end of the same day. UTC and EAT agree here, so this one
     * cannot catch a revert to UTC — it pins that the boundary hasn't been
     * pushed the *other* way, which is what a manual `+1 day` offset bolted on
     * as a "fix" for the early-morning case would do.
     */
    #[Test]
    public function lateEveningIsStillTheSameDay(): void
    {
        self::bootKernel();
        // 23:00 EAT on Saturday 29 August.
        $lateEvening = new \DateTimeImmutable('2026-08-29 20:00:00', new \DateTimeZone('UTC'));

        $this->activityOn('2026-08-29', 'Beyond Zero clinic');
        $this->activityOn('2026-08-30', 'Peggy Lucas school');

        $roster = $this->builder()->buildFor(self::todayAt($lateEvening));

        self::assertSame('2026-08-29', $roster->date->format('Y-m-d'));
        self::assertSame('Beyond Zero clinic', $roster->groups[0]->projectName);
    }

    /**
     * What `new \DateTimeImmutable('today')` does, for an instant we choose
     * rather than the one the clock happens to be at: take the wall-clock date
     * in PHP's default timezone and truncate it to midnight. Deliberately
     * reads `date_default_timezone_get()` instead of naming Nairobi, so these
     * tests fail if the setting reverts.
     */
    private static function todayAt(\DateTimeImmutable $instant): \DateTimeImmutable
    {
        return $instant
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->setTime(0, 0);
    }

    private function activityOn(string $date, string $projectName): void
    {
        ActivityFactory::createOne([
            'date' => new \DateTimeImmutable($date),
            'project' => ProjectFactory::createOne(['name' => $projectName]),
            'activityType' => ActivityTypeFactory::createOne(),
            'volunteer' => VolunteerFactory::createOne(),
        ]);
    }

    private function builder(): RosterBuilder
    {
        $builder = self::getContainer()->get(RosterBuilder::class);
        self::assertInstanceOf(RosterBuilder::class, $builder);

        return $builder;
    }
}
