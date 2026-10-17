<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import;

use App\Entity\ActivityType;
use App\Entity\Program;
use App\Entity\Stay;
use App\Entity\User;
use App\Entity\Volunteer;
use App\Factory\ActivityTypeFactory;
use App\Factory\ProgramFactory;
use App\Factory\StayFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use App\Import\LegacyAck;
use App\Import\LegacyImporter;
use App\Import\LegacyPeopleBuilder;
use App\Import\LegacyWorkbook;
use App\Repository\StayRepository;
use App\Repository\VolunteerRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The legacy workbook import (brainstorm 15) on a hand-built workbook of
 * invented people: what the builder joins, and the two rules the importer
 * keeps — production wins, and a second run changes nothing.
 */
#[ResetDatabase]
final class LegacyImportTest extends KernelTestCase
{
    #[Test]
    public function theBuilderJoinsTheTabsAndReadsEachDateDialect(): void
    {
        $people = (new LegacyPeopleBuilder())->build(self::workbook());

        $asha = $people['email:asha@example.org'];
        self::assertSame(['form r2', '2024 r4'], $asha->seenIn);
        self::assertSame('Asha', $asha->firstName);
        // A date cell starts the stay; "8/24/0024" is the form's typo for 2024.
        self::assertSame('2024-08-01', $asha->stays[0]['start']->format('Y-m-d'));
        self::assertSame('2024-08-24', $asha->stays[0]['end']->format('Y-m-d'));
        // The stay's branch is the roster's, for the stay's year.
        self::assertSame('Nairobi', $asha->stays[0]['branch']);
        self::assertSame('1997-11-15', self::field($asha->fields, 'dateOfBirth'));
        self::assertContains('2024 roster — branch: Nairobi; project: Peggy Lucas; activity: Childcare; source: VW; duration: 2 WEEKS', $asha->legacy);

        // The repeated header row is not a person; a roster-only person with
        // no dates keeps their duration for notes.
        self::assertArrayNotHasKey('name:name', $people);
        self::assertSame([], $people['name:bo undated']->stays);
        self::assertSame(['2024 roster: 1 WEEK'], $people['name:bo undated']->notes);
    }

    #[Test]
    public function productionWinsAndASecondRunChangesNothing(): void
    {
        self::bootKernel();
        $asha = VolunteerFactory::createOne(['firstName' => 'Asha', 'lastName' => 'Test', 'email' => 'asha@example.org', 'phone' => '+254 700 000 000', 'stays' => []]);
        StayFactory::createOne(['volunteer' => $asha, 'startDate' => new \DateTimeImmutable('2024-08-10'), 'endDate' => new \DateTimeImmutable('2024-08-20')]);
        $bo = VolunteerFactory::createOne(['firstName' => 'Bo', 'lastName' => 'Undated', 'email' => null, 'notes' => 'Met at JKIA', 'stays' => []]);

        $importer = self::getContainer()->get(LegacyImporter::class);
        $people = (new LegacyPeopleBuilder())->build(self::workbook());
        $mapping = $importer->proposeMapping($people, ['branches' => [], 'sources' => [], 'skills' => [], 'countries' => []]);
        self::assertSame('Nairobi (HQ)', $mapping['branches']['Nairobi']);
        self::assertSame(['Early-childhood care'], $mapping['skills']['Childcare']);

        $plan = array_column($importer->plan($people, $mapping, []), null, 'key');
        self::assertSame('fill:' . $asha->getId(), $plan['email:asha@example.org']['action']);
        // A name alone is never enough: the reviewer decides.
        self::assertSame('?', $plan['name:bo undated']['action']);
        self::assertSame('fill:' . $bo->getId(), $plan['name:bo undated']['suggestion']);
        self::assertSame('create', $plan['email:cy@example.org']['action']);

        $decisions = [
            'email:asha@example.org' => ['action' => 'fill:' . $asha->getId(), 'branch' => ''],
            'name:bo undated' => ['action' => 'fill:' . $bo->getId(), 'branch' => ''],
            'email:cy@example.org' => ['action' => 'create', 'branch' => ''],
        ];
        $first = $importer->apply($people, $mapping, $decisions, true, true);
        self::assertSame([], $first['problems']);
        self::assertSame(1, $first['counts']['volunteers created']);
        self::assertSame(1, $first['counts']['stays skipped (overlap)']);

        $volunteers = self::getContainer()->get(VolunteerRepository::class);
        $asha = $volunteers->find($asha->getId());
        self::assertInstanceOf(Volunteer::class, $asha);
        self::assertSame('+254 700 000 000', $asha->getPhone(), 'a production value is never overwritten');
        self::assertSame('Hiking', $asha->getHobbies(), 'a production blank is filled');
        self::assertCount(1, $asha->getStays(), 'the overlapping legacy stay is skipped');
        self::assertSame("Met at JKIA\n2024 roster: 1 WEEK", $volunteers->find($bo->getId())?->getNotes());
        $cy = $volunteers->findOneBy(['email' => 'cy@example.org']);
        self::assertInstanceOf(Volunteer::class, $cy);
        $stay = $cy->getStays()->first();
        self::assertInstanceOf(Stay::class, $stay);
        self::assertSame('Mombasa', $stay->getBranch()?->getName());
        self::assertStringStartsWith(LegacyImporter::KEY_PREFIX . 'email:cy@example.org', (string) $cy->getLegacyRecord());

        // people.csv still says "create" for Cy: the key finds them again.
        $second = $importer->apply((new LegacyPeopleBuilder())->build(self::workbook()), $mapping, $decisions, true, true);
        self::assertSame([], $second['problems']);
        self::assertSame(0, $second['counts']['volunteers created']);
        self::assertSame(0, $second['counts']['volunteers filled']);
        self::assertSame(0, $second['counts']['stays created']);
    }

    #[Test]
    public function ackDatesAreSwappedBackAndASheetTheSwapDoesNotFitIsRefused(): void
    {
        // Sheets stored "1/9/2025" (Monday 1 September) as 9 January.
        $days = LegacyAck::days(LegacyWorkbook::fromArray([LegacyAck::SHEET => [
            [0 => 'Volunteer Name'],
            [0 => 'Asha Test', 1 => new \DateTimeImmutable('2025-01-09')],
            [0 => 'asha  test', 1 => '3/9/2025'],
        ]]));
        self::assertSame(['2025-09-01', '2025-09-03'], array_map(static fn(\DateTimeImmutable $d): string => $d->format('Y-m-d'), $days['asha test']['days']));

        // 2025-09-06 swapped back is 9 June: fine. 2025-06-09 swapped is Saturday 6 September: refused.
        $this->expectException(\UnexpectedValueException::class);
        LegacyAck::days(LegacyWorkbook::fromArray([LegacyAck::SHEET => [[0 => 'Name'], [0 => 'Asha', 1 => new \DateTimeImmutable('2025-06-09')]]]));
    }

    #[Test]
    public function aRunSpansWeekendsAndBreaksOnlyPastTheGap(): void
    {
        $day = static fn(string $date): \DateTimeImmutable => new \DateTimeImmutable($date);
        // Fri 5 Sep to Mon 8 Sep: no working day between. Thu 11 to Mon 22 Sep: six.
        $days = [$day('2025-09-05'), $day('2025-09-08'), $day('2025-09-11'), $day('2025-09-22')];

        self::assertCount(2, LegacyAck::runs($days, 5));
        self::assertCount(1, LegacyAck::runs($days, 6));
        self::assertSame(0, LegacyAck::workingDaysBetween($day('2025-09-05'), $day('2025-09-08')));
    }

    #[Test]
    public function anAckRunNextToAStayExtendsItAndASecondRunLogsNothing(): void
    {
        self::bootKernel();
        $volunteer = VolunteerFactory::createOne(['stays' => []]);
        $stay = StayFactory::createOne(['volunteer' => $volunteer, 'startDate' => new \DateTimeImmutable('2025-09-01'), 'endDate' => new \DateTimeImmutable('2025-09-05')]);
        $program = ProgramFactory::createOne(['activityTypes' => [$type = ActivityTypeFactory::createOne()]]);
        $user = UserFactory::createOne();
        $byName = ['asha test' => ['name' => 'Asha Test', 'days' => [new \DateTimeImmutable('2025-09-04'), new \DateTimeImmutable('2025-09-09')]]];
        $decisions = ['asha test#1' => ['volunteer' => (string) $volunteer->getId(), 'action' => 'accept']];
        $ack = self::getContainer()->get(LegacyAck::class);

        $first = $ack->apply($byName, $decisions, $program, $type, $user, 5, true);
        self::assertSame([], $first['problems']);
        self::assertSame(1, $first['counts']['stays extended']);
        self::assertSame(2, $first['counts']['activities created']);
        $reloaded = self::getContainer()->get(StayRepository::class)->find($stay->getId());
        self::assertSame('2025-09-09', $reloaded?->getEndDate()?->format('Y-m-d'));

        // apply() clears the entity manager: a second run loads its own.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $second = $ack->apply($byName, $decisions, $em->find(Program::class, $program->getId()) ?? self::fail(), $em->find(ActivityType::class, $type->getId()) ?? self::fail(), $em->find(User::class, $user->getId()) ?? self::fail(), 5, true);
        self::assertSame(0, $second['counts']['activities created']);
        self::assertSame(2, $second['counts']['days already logged']);
    }

    private static function workbook(): LegacyWorkbook
    {
        $form = array_fill(0, 27, null);
        $asha = $form;
        $asha[0] = new \DateTimeImmutable('2024-07-01');
        $asha[1] = 'Asha Test';
        $asha[2] = '15.11.1997 F';
        $asha[4] = 'Asha@Example.org';
        $asha[5] = '+33 6 00 00 00 00';
        $asha[21] = new \DateTimeImmutable('2024-08-01');
        $asha[22] = '8/24/0024';
        $asha[23] = 'Hiking';
        $cy = $form;
        $cy[0] = new \DateTimeImmutable('2025-03-01');
        $cy[1] = 'Cy New';
        $cy[4] = 'cy@example.org';
        $cy[21] = new \DateTimeImmutable('2025-04-01');
        $cy[22] = new \DateTimeImmutable('2025-04-14');

        return LegacyWorkbook::fromArray([
            LegacyPeopleBuilder::FORM => [[1 => 'Full names:'], $asha, $cy],
            '2024 Volunteers' => [
                [0 => 'NAME', 1 => 'BRANCH'],
                [0 => 'Bo Undated', 6 => '1 WEEK'],
                [0 => 'NAME', 1 => 'BRANCH', 2 => 'PROJECT'],
                [0 => 'ASHA TEST', 1 => 'Nairobi', 2 => 'Peggy Lucas', 3 => 'Childcare', 5 => 'VW', 6 => '2 WEEKS'],
            ],
            '2025 volunteers' => [[0 => 'FULL NAME'], [0 => 'Cy New', 1 => 'Mombasa']],
            LegacyPeopleBuilder::DIRECT => [[0 => 'NAME']],
        ]);
    }

    /** @param array<string, mixed> $fields */
    private static function field(array $fields, string $name): ?string
    {
        $value = $fields[$name] ?? null;

        return $value instanceof \DateTimeImmutable ? $value->format('Y-m-d') : null;
    }
}
