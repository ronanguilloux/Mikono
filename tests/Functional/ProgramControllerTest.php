<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\BeneficiaryGroupFactory;
use App\Factory\BranchFactory;
use App\Factory\ProgramFactory;
use App\Factory\ProjectFactory;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use App\Repository\ProgramRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ProgramControllerTest extends WebTestCase
{
    use ReadsListExports;

    #[Test]
    public function aProgramIsAddedWithItsDatesAndTypes(): void
    {
        $client = static::createClient();
        $project = ProjectFactory::createOne(['name' => 'Peggy Lucas school']);
        ActivityTypeFactory::createOne(['name' => 'Computer tuition']);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/programs/new');
        $form = $crawler->selectButton('Save')->form([
            'program_form[name]' => 'Computer Tuition',
            'program_form[project]' => (string) $project->getId(),
            'program_form[startDate]' => '2026-10-01',
            'program_form[endDate]' => '2026-12-15',
            'program_form[suggestedRoles]' => 'Tutor',
            'program_form[beneficiariesReached]' => '30 pupils',
        ]);
        $types = $form['program_form[activityTypes]'];
        self::assertIsArray($types);
        self::assertInstanceOf(ChoiceFormField::class, $types[0]);
        $types[0]->tick();
        $client->submit($form);

        self::assertResponseRedirects('/programs');
        $crawler = $client->followRedirect();
        $row = $crawler->filter('table tbody tr')->first()->text();
        self::assertStringContainsString('Computer Tuition', $row);
        self::assertStringContainsString('Peggy Lucas school', $row);
        self::assertStringContainsString('01/10/2026 – 15/12/2026', $row);
        self::assertStringContainsString('Computer tuition', $row);
        self::assertSame('30 pupils', static::getContainer()->get(ProgramRepository::class)->findOneBy(['name' => 'Computer Tuition'])?->getBeneficiariesReached());
    }

    #[Test]
    public function aProgramNeedsAProjectAnActivityTypeAndOrderedDates(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/programs/new');
        $client->submit($crawler->selectButton('Save')->form([
            'program_form[name]' => 'Medical camp',
            'program_form[startDate]' => '2026-10-10',
            'program_form[endDate]' => '2026-10-01',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Choose a project.');
        self::assertSelectorTextContains('body', 'Choose at least one activity type.');
        self::assertSelectorTextContains('body', 'The end date must be on or after the start date.');
    }

    #[Test]
    public function datesReadAsAlwaysOnOrOpenEnded(): void
    {
        $client = static::createClient();
        ProgramFactory::createOne(['name' => 'A School support']);
        ProgramFactory::createOne(['name' => 'B Tuition', 'startDate' => new \DateTimeImmutable('2026-09-01')]);
        $client->loginUser(UserFactory::createOne());

        $rows = self::exportedRows($client, '/programs/export.csv?sort=name');

        self::assertSame(['Always on', 'From 01/09/2026'], array_column($rows, 3));
    }

    /**
     * One filter per column, reaching the export too (ADR 0029).
     */
    #[Test]
    public function eachColumnFiltersTheListAndItsExport(): void
    {
        $client = static::createClient();
        $today = new \DateTimeImmutable('today');
        $mombasa = BranchFactory::find(['name' => 'Mombasa']);
        $coast = ProjectFactory::createOne(['name' => 'Coast', 'branch' => $mombasa]);
        $tuition = ActivityTypeFactory::createOne(['name' => 'Tuition']);
        $maths = SkillFactory::createOne(['name' => 'Maths']);
        $girls = BeneficiaryGroupFactory::createOne(['name' => 'Girls']);
        ProgramFactory::createOne(['name' => 'A Always on']);
        ProgramFactory::createOne(['name' => 'B Upcoming', 'startDate' => $today->modify('+1 week'), 'skills' => [$maths]]);
        ProgramFactory::createOne(['name' => 'C Ended', 'project' => $coast, 'endDate' => $today->modify('-1 week'), 'activityTypes' => [$tuition]]);
        ProgramFactory::createOne(['name' => 'D Running', 'project' => $coast, 'startDate' => $today->modify('-1 week'), 'endDate' => $today, 'beneficiaryGroups' => [$girls]]);
        $client->loginUser(UserFactory::createOne());

        $names = static fn(string $url): array => $client->request('GET', $url)
            ->filter('table tbody tr td:first-child')->each(static fn($cell): string => trim($cell->text()));

        foreach ([
            'q=runn' => ['D Running'],
            "project={$coast->getId()}" => ['C Ended', 'D Running'],
            "branch={$mombasa->getId()}" => ['C Ended', 'D Running'],
            'period=current' => ['A Always on', 'D Running'],
            'period=upcoming' => ['B Upcoming'],
            'period=ended' => ['C Ended'],
            "activityType={$tuition->getId()}" => ['C Ended'],
            "skill={$maths->getId()}" => ['B Upcoming'],
            "group={$girls->getId()}" => ['D Running'],
            "branch={$mombasa->getId()}&period=current" => ['D Running'],
            "q=a&skill={$maths->getId()}&period=ended" => [],
        ] as $query => $expected) {
            if ([] === $expected) {
                $client->request('GET', "/programs?{$query}");
                self::assertSelectorTextContains('body', 'No programs match these filters.');
            } else {
                self::assertSame($expected, $names("/programs?{$query}&sort=name"), $query);
            }
            self::assertSame($expected, array_column(self::exportedRows($client, "/programs/export.csv?{$query}&sort=name"), 0), $query);
        }

        // Malformed or unknown input degrades to no filter (ADR 0023).
        foreach (['q[]=x', 'period=foo', 'skill[]=1', 'project=abc', 'group=999999'] as $query) {
            $crawler = $client->request('GET', "/programs?{$query}");
            self::assertResponseIsSuccessful();
            self::assertCount(4, $crawler->filter('table tbody tr'), $query);
        }
    }

    #[Test]
    public function aProgramIsDeleted(): void
    {
        $client = static::createClient();
        ProgramFactory::createOne(['name' => 'Medical camp']);
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/programs');
        $client->submitForm('Delete');

        self::assertResponseRedirects('/programs');
        self::assertSame(0, self::getContainer()->get(ProgramRepository::class)->count([]));
    }

    #[Test]
    public function aProgramWithActivitiesCannotBeDeleted(): void
    {
        $client = static::createClient();
        $program = ProgramFactory::createOne(['name' => 'School support']);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/programs');

        // A tab opened before the activity existed still offers Delete.
        ActivityFactory::createOne(['program' => $program]);
        $client->submitForm('Delete');
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Cannot delete School support — 1 activity belongs to it.');
        $crawler = $client->request('GET', '/programs');
        self::assertCount(0, $crawler->filter('table tbody form'));
        self::assertCount(1, $crawler->filter('table tbody [aria-disabled="true"]'));
    }

    #[Test]
    public function beneficiaryGroupsAreTickedOnEditAndListed(): void
    {
        $client = static::createClient();
        $program = ProgramFactory::createOne(['name' => 'Mentoring']);
        $girls = BeneficiaryGroupFactory::createOne(['name' => 'Adolescent girls']);
        BeneficiaryGroupFactory::createOne(['name' => 'Families']);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/programs/{$program->getId()}/edit");
        $form = $crawler->selectButton('Save')->form();
        $groups = $form['program_form[beneficiaryGroups]'];
        self::assertIsArray($groups);
        foreach ($groups as $group) {
            self::assertInstanceOf(ChoiceFormField::class, $group);
            if ($group->availableOptionValues() === [(string) $girls->getId()]) {
                $group->tick();
            }
        }
        $client->submit($form);
        self::assertResponseRedirects();

        $crawler = $client->request('GET', '/programs');
        self::assertStringContainsString('Adolescent girls', $crawler->filter('tr:contains("Mentoring")')->text());
        self::assertStringNotContainsString('Families', $crawler->filter('tr:contains("Mentoring")')->text());
    }

    #[Test]
    public function anEditMayNotStrandTheProgramsActivities(): void
    {
        $client = static::createClient();
        $schoolSupport = ActivityTypeFactory::createOne(['name' => 'School support']);
        $tuition = ActivityTypeFactory::createOne(['name' => 'Tuition']);
        $program = ProgramFactory::createOne(['activityTypes' => [$schoolSupport, $tuition]]);
        ActivityFactory::createOne([
            'program' => $program,
            'activityType' => $schoolSupport,
            'date' => new \DateTimeImmutable('2026-08-10'),
        ]);
        $otherProject = ProjectFactory::createOne();
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/programs/{$program->getId()}/edit");
        $form = $crawler->selectButton('Save')->form([
            'program_form[project]' => (string) $otherProject->getId(),
            'program_form[startDate]' => '2026-09-01',
        ]);
        $types = $form['program_form[activityTypes]'];
        self::assertIsArray($types);
        foreach ($types as $type) {
            self::assertInstanceOf(ChoiceFormField::class, $type);
            $type->availableOptionValues() === [(string) $schoolSupport->getId()] ? $type->untick() : $type->tick();
        }
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'This program has activities, so it stays at its project.');
        self::assertSelectorTextContains('body', '1 activity of this program would fall outside these dates.');
        self::assertSelectorTextContains('body', 'Activities of this program use School support, so it stays.');
    }

    #[Test]
    public function theMatchesActionOpensTheMatchesScreenOnThatProgram(): void
    {
        $client = static::createClient();
        $program = ProgramFactory::createOne();
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/programs');

        self::assertSame("/matches?program={$program->getId()}", $crawler->filter('table')->selectLink('Matches')->attr('href'));
    }
}
