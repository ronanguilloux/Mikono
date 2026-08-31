<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Factory\ActivityFactory;
use App\Factory\ProjectFactory;
use App\Factory\VolunteerFactory;
use App\Report\QuietProject;
use App\Report\QuietProjectFinder;
use App\Report\QuietProjectSeverity;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class QuietProjectFinderTest extends KernelTestCase
{
    #[Test]
    public function flagsAProjectNobodyHasVisitedInAWhile(): void
    {
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        ActivityFactory::createOne([
            'date' => $today->modify('-45 days'),
            'project' => ProjectFactory::createOne(['name' => 'Dreams of Hope Kids', 'isActive' => true]),
        ]);

        $quiet = $this->finder()->find($today);

        self::assertCount(1, $quiet);
        self::assertSame('Dreams of Hope Kids', $quiet[0]->name);
        self::assertSame(45, $quiet[0]->days);
        self::assertSame(QuietProjectSeverity::Warn, $quiet[0]->severity);
        self::assertFalse($quiet[0]->hasNeverBeenUsed());
    }

    #[Test]
    public function escalatesToCriticalPastFiftyDays(): void
    {
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        ActivityFactory::createOne(['date' => $today->modify('-58 days')]);

        $quiet = $this->finder()->find($today);

        self::assertSame(58, $quiet[0]->days);
        self::assertSame(QuietProjectSeverity::Critical, $quiet[0]->severity);
    }

    #[Test]
    public function flagsAProjectThatHasNeverBeenUsedAtAll(): void
    {
        self::bootKernel();
        ProjectFactory::createOne(['name' => 'Nyali Beach', 'isActive' => true]);

        // Looking at the project 62 days on from the day it was created.
        $quiet = $this->finder()->find((new \DateTimeImmutable('today'))->modify('+62 days'));

        self::assertCount(1, $quiet);
        self::assertSame('Nyali Beach', $quiet[0]->name);
        self::assertSame(62, $quiet[0]->days);
        self::assertTrue($quiet[0]->hasNeverBeenUsed());
        self::assertNull($quiet[0]->lastActivity);
    }

    #[Test]
    public function neverListsVolunteers(): void
    {
        // Volunteers come for a few weeks and then leave, so someone who has
        // stopped appearing has usually finished rather than lapsed. Only
        // projects belong on this list.
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        ActivityFactory::createOne([
            'date' => $today->modify('-70 days'),
            'volunteer' => VolunteerFactory::createOne(['firstName' => 'Long', 'lastName' => 'Gone', 'isActive' => true]),
            'project' => ProjectFactory::createOne(['name' => 'Peggy Lucas school']),
        ]);

        $names = array_map(static fn(QuietProject $p) => $p->name, $this->finder()->find($today));

        self::assertSame(['Peggy Lucas school'], $names);
    }

    #[Test]
    public function leavesOutAnInactiveProject(): void
    {
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        ActivityFactory::createOne([
            'date' => $today->modify('-70 days'),
            'project' => ProjectFactory::createOne(['name' => 'Closed site', 'isActive' => false]),
        ]);

        self::assertSame([], $this->finder()->find($today));
    }

    #[Test]
    public function leavesOutAProjectVisitedRecently(): void
    {
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        ActivityFactory::createOne(['date' => $today->modify('-10 days')]);

        self::assertSame([], $this->finder()->find($today));
    }

    #[Test]
    public function treatsAVisitAlreadyPlannedAheadAsNotQuiet(): void
    {
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        ActivityFactory::createOne(['date' => $today->modify('+1 day')]);

        self::assertSame([], $this->finder()->find($today));
    }

    #[Test]
    public function sortsTheQuietestFirst(): void
    {
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        ActivityFactory::createOne([
            'date' => $today->modify('-33 days'),
            'project' => ProjectFactory::createOne(['name' => 'Recently quiet']),
        ]);
        ActivityFactory::createOne([
            'date' => $today->modify('-62 days'),
            'project' => ProjectFactory::createOne(['name' => 'Quiet the longest']),
        ]);

        $names = array_map(static fn(QuietProject $p) => $p->name, $this->finder()->find($today));

        self::assertSame(['Quiet the longest', 'Recently quiet'], $names);
    }

    private function finder(): QuietProjectFinder
    {
        $finder = self::getContainer()->get(QuietProjectFinder::class);
        self::assertInstanceOf(QuietProjectFinder::class, $finder);

        return $finder;
    }
}
