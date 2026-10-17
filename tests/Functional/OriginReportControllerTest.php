<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class OriginReportControllerTest extends WebTestCase
{
    use ReadsListExports;

    #[Test]
    public function itCountsVolunteersByCountryOfResidenceLargestFirst(): void
    {
        $client = static::createClient();
        VolunteerFactory::createMany(3, ['countryOfResidence' => 'DE']);
        VolunteerFactory::createOne(['countryOfResidence' => 'FR']);
        VolunteerFactory::createOne(['countryOfResidence' => 'AT']);
        VolunteerFactory::createOne(['countryOfResidence' => 'MC']);
        VolunteerFactory::createOne(['countryOfResidence' => null]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports/origins');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-origins-summary]', '7 volunteers on file, from 4 countries of residence · 1 with none recorded.');
        // Largest first, then by name; shares are of the 6 with a country.
        self::assertSame(
            ['Germany 3 50.0%', 'Austria 1 16.7%', 'France 1 16.7%', 'Monaco 1 16.7%'],
            $crawler->filter('figure ol > li')->each(static fn($li) => preg_replace('/\s+/', ' ', trim($li->text()))),
        );
        self::assertSelectorNotExists('table');

        $map = $crawler->filter('[data-controller="world-map"]');
        self::assertSame(
            ['DE' => '#0085a6', 'AT' => '#00aed9', 'FR' => '#00aed9', 'MC' => '#00aed9'],
            json_decode((string) $map->attr('data-world-map-fills-value'), true),
        );
        self::assertSame(
            ['DE' => 'Germany: 3 volunteers', 'AT' => 'Austria: 1 volunteer', 'FR' => 'France: 1 volunteer', 'MC' => 'Monaco: 1 volunteer'],
            json_decode((string) $map->attr('data-world-map-titles-value'), true),
        );

        // Monaco is too small for the map, so it is named in words.
        self::assertSelectorTextContains('[data-too-small]', 'Monaco 1');
    }

    #[Test]
    public function itExportsTheSameRows(): void
    {
        $client = static::createClient();
        VolunteerFactory::createMany(3, ['countryOfResidence' => 'DE']);
        VolunteerFactory::createOne(['countryOfResidence' => null]);

        $client->loginUser(UserFactory::createOne());

        self::assertSame(
            [['Germany', 'DE', '3', '100.0%'], ['Not recorded', '—', '1', '—']],
            self::exportedRows($client, '/reports/origins/export.csv'),
        );
    }
}
