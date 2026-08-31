<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\EscortFactory;
use App\Factory\ProjectFactory;
use App\Factory\VolunteerFactory;
use App\Report\RosterBuilder;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class RosterBuilderTest extends KernelTestCase
{
    #[Test]
    public function groupsADaysActivitiesByProjectAndActivityType(): void
    {
        self::bootKernel();
        $date = new \DateTimeImmutable('2026-08-29');
        $clinic = ProjectFactory::createOne(['name' => 'Beyond Zero clinic']);
        $support = ActivityTypeFactory::createOne(['name' => 'Clinic support']);
        $escort = EscortFactory::createOne(['name' => 'Mr Maeba']);

        ActivityFactory::createOne([
            'date' => $date,
            'project' => $clinic,
            'activityType' => $support,
            'accompaniedBy' => $escort,
            'volunteer' => VolunteerFactory::createOne(['firstName' => 'Rahel', 'lastName' => 'Atieno']),
        ]);
        ActivityFactory::createOne([
            'date' => $date,
            'project' => $clinic,
            'activityType' => $support,
            'accompaniedBy' => $escort,
            'volunteer' => VolunteerFactory::createOne(['firstName' => 'James', 'lastName' => 'Otieno']),
        ]);

        $roster = $this->builder()->buildFor($date);

        self::assertFalse($roster->isEmpty());
        self::assertCount(1, $roster->groups);
        self::assertSame('Beyond Zero clinic', $roster->groups[0]->projectName);
        self::assertSame('Clinic support', $roster->groups[0]->activityTypeName);
        self::assertSame(['Mr Maeba'], $roster->groups[0]->escortNames);
        self::assertSame(
            ['Rahel Atieno', 'James Otieno'],
            array_map(static fn($slot) => $slot->volunteerName, $roster->groups[0]->slots),
        );
        self::assertSame(1, $roster->projectCount());
        self::assertSame(2, $roster->slotCount());
    }

    #[Test]
    public function marksAVolunteersSecondSiteOfTheDayAsLater(): void
    {
        self::bootKernel();
        $date = new \DateTimeImmutable('2026-08-29');
        $rahel = VolunteerFactory::createOne(['firstName' => 'Rahel', 'lastName' => 'Atieno']);

        ActivityFactory::createOne([
            'date' => $date,
            'volunteer' => $rahel,
            'project' => ProjectFactory::createOne(['name' => 'Beyond Zero clinic']),
            'activityType' => ActivityTypeFactory::createOne(['name' => 'Clinic support']),
        ]);
        ActivityFactory::createOne([
            'date' => $date,
            'volunteer' => $rahel,
            'project' => ProjectFactory::createOne(['name' => 'MVETI']),
            'activityType' => ActivityTypeFactory::createOne(['name' => 'Vocational training']),
        ]);

        $roster = $this->builder()->buildFor($date);

        self::assertCount(2, $roster->groups);
        self::assertFalse($roster->groups[0]->slots[0]->isLater);
        self::assertTrue($roster->groups[1]->slots[0]->isLater);
        self::assertSame(2, $roster->slotCount());
    }

    #[Test]
    public function rendersTheScheduleInTheVolunteerManagersOwnMessageFormat(): void
    {
        self::bootKernel();
        $date = new \DateTimeImmutable('2026-08-29');

        ActivityFactory::createOne([
            'date' => $date,
            'volunteer' => VolunteerFactory::createOne(['firstName' => 'Grace', 'lastName' => 'Wanjiru']),
            'project' => ProjectFactory::createOne(['name' => 'Peggy Lucas school']),
            'activityType' => ActivityTypeFactory::createOne(['name' => 'School support']),
            'accompaniedBy' => EscortFactory::createOne(['name' => 'Ms Njeri']),
        ]);

        $text = $this->builder()->buildFor($date)->toWhatsAppText();

        self::assertSame(
            <<<'TEXT'
                📅 Saturday, 29 August 2026

                📍 Peggy Lucas school (School support)
                - Grace Wanjiru
                Accompanied by: Ms Njeri
                TEXT,
            $text,
        );
    }

    #[Test]
    public function leavesOutTheEscortLineWhenNobodyIsAccompanyingTheGroup(): void
    {
        self::bootKernel();
        $date = new \DateTimeImmutable('2026-08-29');

        ActivityFactory::createOne([
            'date' => $date,
            'volunteer' => VolunteerFactory::createOne(['firstName' => 'Susan', 'lastName' => 'Njoki']),
            'project' => ProjectFactory::createOne(['name' => 'Mombasa Home Visits']),
            'activityType' => ActivityTypeFactory::createOne(['name' => 'Home visit']),
            'accompaniedBy' => null,
        ]);

        $roster = $this->builder()->buildFor($date);

        self::assertSame([], $roster->groups[0]->escortNames);
        self::assertStringNotContainsString('Accompanied by', $roster->toWhatsAppText());
    }

    #[Test]
    public function ignoresActivitiesOnOtherDays(): void
    {
        self::bootKernel();
        ActivityFactory::createOne(['date' => new \DateTimeImmutable('2026-08-28')]);

        $roster = $this->builder()->buildFor(new \DateTimeImmutable('2026-08-29'));

        self::assertTrue($roster->isEmpty());
        self::assertSame(0, $roster->projectCount());
        self::assertSame(0, $roster->slotCount());
    }

    private function builder(): RosterBuilder
    {
        $builder = self::getContainer()->get(RosterBuilder::class);
        self::assertInstanceOf(RosterBuilder::class, $builder);

        return $builder;
    }
}
