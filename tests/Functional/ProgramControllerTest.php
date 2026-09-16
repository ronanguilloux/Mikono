<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\ProgramFactory;
use App\Factory\ProjectFactory;
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
}
