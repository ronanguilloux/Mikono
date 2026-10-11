<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ActivityFactory;
use App\Factory\BranchFactory;
use App\Factory\ProjectFactory;
use App\Factory\StayFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

use function Zenstruck\Foundry\Persistence\save;

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

    #[Test]
    public function capacityIsOptionalAndPositive(): void
    {
        $client = static::createClient();
        $branch = BranchFactory::createOne(['name' => 'Kisumu']);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/branches/' . $branch->getId() . '/edit');
        $client->submit($crawler->selectButton('Save')->form(['branch_form[capacity]' => '0']));
        self::assertResponseStatusCodeSame(422);

        $crawler = $client->request('GET', '/branches/' . $branch->getId() . '/edit');
        $client->submit($crawler->selectButton('Save')->form(['branch_form[capacity]' => '12']));
        self::assertResponseRedirects('/branches');
        BranchFactory::assert()->exists(['name' => 'Kisumu', 'capacity' => 12]);
    }

    #[Test]
    public function theIndexLinksEachNameToItsPage(): void
    {
        $client = static::createClient();
        $mombasa = BranchFactory::find(['name' => 'Mombasa']);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/branches');

        self::assertSame(sprintf('/branches/%d', $mombasa->getId()), $crawler->filter('table a:contains("Mombasa")')->attr('href'));
    }

    #[Test]
    public function theShowPageGathersTheBranchsProjectsPeopleAndActivity(): void
    {
        $client = static::createClient();
        $today = new \DateTimeImmutable('today');
        $mombasa = BranchFactory::find(['name' => 'Mombasa']);
        $mombasa->setCapacity(4);
        save($mombasa);
        $project = ProjectFactory::createOne(['name' => 'Likoni School', 'branch' => $mombasa]);
        ProjectFactory::createOne(['name' => 'Kibera Library']);
        $stay = StayFactory::createOne([
            'volunteer' => VolunteerFactory::new()->withoutStay()->with(['firstName' => 'Amina']),
            'branch' => $mombasa,
            'startDate' => $today->modify('-3 days'),
            'endDate' => $today->modify('+10 days'),
        ]);
        ActivityFactory::createOne(['project' => $project, 'volunteer' => $stay->getVolunteer(), 'stay' => $stay, 'date' => $today->modify('-1 day')]);
        ActivityFactory::createOne(['project' => $project, 'volunteer' => $stay->getVolunteer(), 'stay' => $stay, 'date' => $today->modify('+2 days')]);
        StayFactory::createOne(['branch' => $mombasa, 'startDate' => $today->modify('+3 months'), 'endDate' => $today->modify('+4 months')]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', sprintf('/branches/%d', $mombasa->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mombasa');
        self::assertSelectorTextContains('[data-branch-projects]', 'Likoni School');
        self::assertSelectorTextNotContains('[data-branch-projects]', 'Kibera Library');
        self::assertSelectorTextContains('[data-branch-on-site]', 'Amina');
        self::assertCount(2, $crawler->filter('[data-branch-activities] tbody tr'));
        self::assertSelectorTextContains('[data-branch-activities] tbody tr:first-child', 'Planned');
        self::assertCount(1, $crawler->filter('[data-occupancy] figure [data-limit]'));
        $lastMonth = $crawler->filter('[data-branch-months] table tbody tr')->last();
        self::assertSame($today->format('F Y'), $lastMonth->filter('th')->text(), 'a booked future month is not charted');
        self::assertSame('1', $lastMonth->filter('td')->text());
    }

    #[Test]
    public function eachListOnTheShowPagePaginatesAndSortsOnItsOwn(): void
    {
        $client = static::createClient();
        $mombasa = BranchFactory::find(['name' => 'Mombasa']);
        $project = ProjectFactory::createOne(['name' => 'Likoni School', 'branch' => $mombasa]);
        // Without a stay of their own, each volunteer's activity gets one at the project's branch.
        ActivityFactory::createMany(26, ['project' => $project, 'volunteer' => VolunteerFactory::new()->withoutStay()]);
        $client->loginUser(UserFactory::createOne());
        $path = sprintf('/branches/%d', $mombasa->getId());

        $crawler = $client->request('GET', $path);

        self::assertCount(25, $crawler->filter('[data-branch-activities] tbody tr'));
        self::assertSelectorTextContains('[data-branch-activities] [data-pagination-bar]', 'Showing 1–25 of 26');
        self::assertSame($path . '?activitiesPage=2', $crawler->filter('[data-branch-activities] [data-pagination-bar] a[aria-label="Page 2"]')->attr('href'));
        self::assertSame($path . '?activitiesSort=volunteer&activitiesDirection=asc', $crawler->filter('[data-branch-activities] [data-sort-link="volunteer"]')->attr('href'));

        $crawler = $client->request('GET', $path . '?activitiesPage=2&activitiesSort=volunteer&activitiesDirection=desc');

        self::assertCount(1, $crawler->filter('[data-branch-activities] tbody tr'));
        self::assertCount(1, $crawler->filter('[data-branch-activities] th[aria-sort="descending"]'));
        self::assertCount(0, $crawler->filter('[data-branch-projects] th[aria-sort]'), 'another list keeps its own sort');
        self::assertSelectorTextContains('[data-branch-projects] tbody', 'Likoni School');
    }
}
