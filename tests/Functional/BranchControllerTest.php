<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\BranchFactory;
use App\Factory\ProjectFactory;
use App\Factory\StayFactory;
use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Foundry resets the test database by replaying migrations, so every test
 * starts with the five branches the table's migration seeds.
 */
#[ResetDatabase]
final class BranchControllerTest extends WebTestCase
{
    use ReadsListExports;

    /**
     * The five real branches come from the migration, so the counts include
     * them.
     */
    #[Test]
    public function theExportCarriesTheOnScreenSortOrEveryRowInDefaultOrder(): void
    {
        $client = static::createClient();
        BranchFactory::createOne(['name' => 'AAA Test branch']);
        $client->loginUser(UserFactory::createOne());

        $sorted = self::exportedRows($client, '/branches/export.csv?sort=name&direction=desc');
        self::assertCount(6, $sorted);
        self::assertSame('AAA Test branch', $sorted[5][0]);

        $whole = self::exportedRows($client, '/branches/export.csv');
        self::assertCount(6, $whole);
        self::assertSame('AAA Test branch', $whole[0][0]);
    }

    #[Test]
    public function theIndexListsTheSeededBranches(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/branches');

        self::assertResponseIsSuccessful();
        self::assertCount(5, $crawler->filter('table tbody tr'));
        foreach (['Nairobi (HQ)', 'Mombasa', 'Samburu', 'Uganda', 'USA (Global)'] as $name) {
            self::assertSelectorTextContains('table', $name);
        }
        self::assertSelectorTextContains('table', 'Kibera Plaza, Off Ngong Road');
    }

    #[Test]
    public function newWithValidDataPersists(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/branches/new');

        $client->submit($crawler->selectButton('Save')->form([
            'branch_form[name]' => 'Kisumu',
            'branch_form[physicalLocation]' => 'Milimani',
            'branch_form[projectZones]' => 'Nyalenda, Manyatta',
            'branch_form[programFocus]' => 'Lake-side education.',
        ]));

        self::assertResponseRedirects('/branches');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Kisumu was added.');
        self::assertSelectorTextContains('table', 'Milimani');
    }

    #[Test]
    public function newWithoutANameOrLocationIsUnprocessable(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/branches/new');

        $client->submit($crawler->selectButton('Save')->form([
            'branch_form[name]' => '',
            'branch_form[physicalLocation]' => '',
        ]));

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function editPersists(): void
    {
        $client = static::createClient();
        $branch = BranchFactory::createOne(['name' => 'Kisumu']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/branches/' . $branch->getId() . '/edit');

        $client->submit($crawler->selectButton('Save')->form([
            'branch_form[physicalLocation]' => 'Kondele',
            'branch_form[isActive]' => false,
        ]));

        self::assertResponseRedirects('/branches');
        BranchFactory::assert()->exists(['name' => 'Kisumu', 'physicalLocation' => 'Kondele', 'isActive' => false]);
    }

    #[Test]
    public function deleteRemovesTheBranch(): void
    {
        $client = static::createClient();
        BranchFactory::createOne(['name' => 'Aardvark Branch']);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/branches');

        // Sorted by name, the new branch is the first row's Delete form.
        $client->submitForm('Delete');

        self::assertResponseRedirects('/branches');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Aardvark Branch was deleted.');
        BranchFactory::assert()->count(5);
    }

    #[Test]
    public function deleteWithABadTokenIsRefused(): void
    {
        $client = static::createClient();
        $branch = BranchFactory::createOne();
        $client->loginUser(UserFactory::createOne());

        $client->request('POST', '/branches/' . $branch->getId() . '/delete', ['_token' => 'forged']);

        self::assertResponseRedirects('/branches');
        BranchFactory::assert()->count(6);
    }

    #[Test]
    public function theIndexPaginatesAtTwentyFivePerPage(): void
    {
        $client = static::createClient();
        BranchFactory::createMany(21);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/branches');
        self::assertCount(25, $crawler->filter('table tbody tr'));

        $crawler = $client->request('GET', '/branches?page=2');
        self::assertCount(1, $crawler->filter('table tbody tr'));
    }

    #[Test]
    public function theIndexSortsByARequestedColumn(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/branches?sort=name&direction=desc');
        // SQLite's binary collation: "Uganda" sorts after "USA (Global)".
        self::assertStringContainsString('Uganda', $crawler->filter('table tbody tr')->first()->text());

        $crawler = $client->request('GET', '/branches?sort=name&direction=asc');
        self::assertStringContainsString('Mombasa', $crawler->filter('table tbody tr')->first()->text());
    }

    #[Test]
    public function theIndexShrugsOffAnUnknownSortColumn(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/branches?sort=programFocus&direction=desc');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Mombasa', $crawler->filter('table tbody tr')->first()->text());
    }

    #[Test]
    public function theIndexShowsDeleteAsUnavailableForABranchWithStays(): void
    {
        $client = static::createClient();
        $branch = BranchFactory::createOne(['name' => 'Aardvark Branch']);
        StayFactory::createOne(['branch' => $branch]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/branches');

        self::assertStringContainsString(
            'Cannot delete Aardvark Branch — 1 stay or project points to it.',
            $crawler->filter('table tbody tr')->first()->filter('[aria-disabled="true"]')->text(),
        );
    }

    #[Test]
    public function deleteIsRefusedWhileStaysReferenceTheBranch(): void
    {
        $client = static::createClient();
        $branch = BranchFactory::createOne(['name' => 'Aardvark Branch']);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/branches');

        StayFactory::createOne(['branch' => $branch]);
        $client->submitForm('Delete');

        self::assertResponseRedirects('/branches');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cannot delete Aardvark Branch');
        BranchFactory::assert()->count(6);
    }

    #[Test]
    public function theIndexShowsDeleteAsUnavailableForABranchWithProjects(): void
    {
        $client = static::createClient();
        $branch = BranchFactory::createOne(['name' => 'Aardvark Branch']);
        ProjectFactory::createOne(['branch' => $branch]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/branches');

        self::assertStringContainsString(
            'Cannot delete Aardvark Branch — 1 stay or project points to it.',
            $crawler->filter('table tbody tr')->first()->filter('[aria-disabled="true"]')->text(),
        );
    }
}
