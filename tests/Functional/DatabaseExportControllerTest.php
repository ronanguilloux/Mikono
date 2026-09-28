<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class DatabaseExportControllerTest extends WebTestCase
{
    #[Test]
    public function aRegularRoleUserIsForbiddenFromTheDatabaseExport(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne(['roles' => ['ROLE_USER']]));

        $client->request('GET', '/database/export.zip');

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function theArchiveHoldsOneCsvPerTableMinusTheSkippedOnes(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());
        VolunteerFactory::createOne(['firstName' => '=cmd']);

        $zip = self::download($client);

        $tables = static::getContainer()->get(Connection::class)->createSchemaManager()->listTableNames();
        $expected = array_map(
            static fn(string $table): string => $table . '.csv',
            array_diff($tables, ['doctrine_migration_versions', 'volunteer_photo']),
        );
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $entries[] = (string) $zip->getNameIndex($i);
        }
        sort($expected);
        sort($entries);
        self::assertSame($expected, $entries);
        foreach (['stay.csv', 'volunteer_skill.csv', 'login_attempt.csv', 'usage_event.csv'] as $entry) {
            self::assertContains($entry, $entries);
        }
    }

    #[Test]
    public function secretsLeaveTheArchiveAndCsvCellsAreFormulaGuarded(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());
        VolunteerFactory::createOne(['firstName' => '=cmd']);

        $zip = self::download($client);

        $userHeader = self::header((string) $zip->getFromName('user.csv'));
        self::assertContains('email', $userHeader);
        self::assertNotContains('password', $userHeader);

        $volunteerCsv = (string) $zip->getFromName('volunteer.csv');
        $volunteerHeader = self::header($volunteerCsv);
        self::assertContains('gender', $volunteerHeader);
        self::assertNotContains('passport_number_ciphertext', $volunteerHeader);
        self::assertStringContainsString("'=cmd", $volunteerCsv);
    }

    #[Test]
    public function theExportIsInTheAdminMenuForAnAdminOnly(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne(['roles' => ['ROLE_USER']]));

        $crawler = $client->request('GET', '/');
        self::assertCount(0, $crawler->filter('a[href="/database/export.zip"]'));

        $client->loginUser(UserFactory::new()->admin()->create());
        $crawler = $client->request('GET', '/');
        $links = $crawler->filter('a[href="/database/export.zip"]');
        self::assertGreaterThan(0, $links->count());
        // Turbo Drive would otherwise try to render the zip as a page.
        self::assertSame('false', $links->attr('data-turbo'));
    }

    private static function download(KernelBrowser $client): \ZipArchive
    {
        $client->request('GET', '/database/export.zip');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/zip');

        $path = (string) tempnam(sys_get_temp_dir(), 'zip-test-');
        file_put_contents($path, $client->getInternalResponse()->getContent());
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path));

        return $zip;
    }

    /**
     * @return list<string>
     */
    private static function header(string $csv): array
    {
        // Strip OpenSpout's UTF-8 BOM before reading the first line.
        $firstLine = strtok(str_replace("\u{FEFF}", '', $csv), "\n");

        return array_map(strval(...), str_getcsv((string) $firstLine, escape: ''));
    }
}
