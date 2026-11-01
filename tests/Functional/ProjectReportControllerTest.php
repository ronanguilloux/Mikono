<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ActivityFactory;
use App\Factory\ProjectFactory;
use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ProjectReportControllerTest extends WebTestCase
{
    use ReadsListExports;

    #[Test]
    public function itShowsTheTilesAndNeedingProjectsFirst(): void
    {
        $client = static::createClient();
        ActivityFactory::createOne(['date' => new \DateTimeImmutable('-2 days'), 'project' => ProjectFactory::createOne(['name' => 'Busy'])]);
        ProjectFactory::createOne(['name' => 'Empty']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports/projects');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-kpi-tiles]', 'Needing volunteers');
        self::assertSame(['Empty', 'Busy'], $crawler->filter('tbody tr td:first-child')->each(static fn($cell): string => trim($cell->text())));
    }

    #[Test]
    public function itSortsByLastActivityWithNeverVisitedLast(): void
    {
        $client = static::createClient();
        ActivityFactory::createOne(['date' => new \DateTimeImmutable('-2 days'), 'project' => ProjectFactory::createOne(['name' => 'Busy'])]);
        ProjectFactory::createOne(['name' => 'Empty']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports/projects?sort=lastActivity&direction=desc');

        self::assertSame(['Busy', 'Empty'], $crawler->filter('tbody tr td:first-child')->each(static fn($cell): string => trim($cell->text())));
    }

    #[Test]
    public function itExportsEveryRowWithoutPaging(): void
    {
        $client = static::createClient();
        ProjectFactory::createMany(30);

        $client->loginUser(UserFactory::createOne());

        self::assertCount(30, self::exportedRows($client, '/reports/projects/export.csv'));
    }
}
