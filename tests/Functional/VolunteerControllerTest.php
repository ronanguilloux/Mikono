<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\ActivityDuration;
use App\Factory\ActivityFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class VolunteerControllerTest extends WebTestCase
{
    #[Test]
    public function indexListsSeededVolunteers(): void
    {
        $client = static::createClient();
        VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/volunteers');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Aisha Njoroge');
    }

    /**
     * Volunteers leave after a few weeks, so someone who finished their stint
     * shouldn't sit between two people working this week. Nobody is hidden —
     * active status only decides the default order.
     */
    #[Test]
    public function theIndexListsActiveVolunteersBeforeInactiveOnes(): void
    {
        $client = static::createClient();
        VolunteerFactory::new()->inactive()->create(['firstName' => 'Aisha', 'lastName' => 'Achieng']);
        VolunteerFactory::createOne(['firstName' => 'Zawadi', 'lastName' => 'Zuma']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers');

        // Zuma sorts after Achieng by name; active-first is what puts her top.
        self::assertStringContainsString('Zawadi Zuma', $crawler->filter('table tbody tr')->first()->text());
    }

    #[Test]
    public function newWithValidDataPersistsAndRedirects(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers/new');

        $form = $crawler->selectButton('Save')->form([
            'volunteer_form[firstName]' => 'Grace',
            'volunteer_form[lastName]' => 'Wanjiru',
            'volunteer_form[email]' => 'grace@example.org',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/volunteers');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Grace Wanjiru');
    }

    #[Test]
    public function newWithInvalidDataIsUnprocessableAndReshowsErrors(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers/new');

        $form = $crawler->selectButton('Save')->form([
            'volunteer_form[firstName]' => '',
            'volunteer_form[lastName]' => '',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function showDisplaysVolunteerDetailsAndTimeline(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge', 'notes' => 'Fluent in Swahili.']);
        ActivityFactory::createOne([
            'volunteer' => $volunteer,
            'date' => new \DateTimeImmutable('yesterday'),
            'duration' => ActivityDuration::FullDay,
        ]);
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', "/volunteers/{$volunteer->getId()}");

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Aisha Njoroge');
        self::assertSelectorTextContains('body', 'Fluent in Swahili.');
        self::assertSelectorTextContains('body', 'Activities logged');
        self::assertSelectorTextContains('body', 'Full day');
    }

    #[Test]
    public function showTagsAFutureActivityAsPlanned(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        ActivityFactory::createOne(['volunteer' => $volunteer, 'date' => new \DateTimeImmutable('+1 week')]);
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', "/volunteers/{$volunteer->getId()}");

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Planned');
    }

    #[Test]
    public function editUpdatesTheVolunteer(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge', 'phone' => '+254700000000']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', "/volunteers/{$volunteer->getId()}/edit");

        $form = $crawler->selectButton('Save')->form([
            'volunteer_form[firstName]' => 'Aisha',
            'volunteer_form[lastName]' => 'Njoroge',
            'volunteer_form[phone]' => '+254711111111',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/volunteers');
        $client->followRedirect();
        self::assertSelectorTextContains('body', '+254711111111');
    }

    #[Test]
    public function deleteRemovesAVolunteerWithNoActivities(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/volunteers');
        $client->submitForm('Delete');

        self::assertResponseRedirects('/volunteers');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'No volunteers yet');
    }

    /**
     * The index no longer offers Delete once a volunteer has activity, so the
     * way to reach the server guard is the way a reader would in real life:
     * with a page rendered before the activity existed. That stale-tab case is
     * exactly why the guard stays in the controller rather than moving into
     * the view.
     */
    #[Test]
    public function deleteIsBlockedWhenAnActivityReferencesTheVolunteer(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/volunteers');

        ActivityFactory::createOne(['volunteer' => $volunteer]);
        $client->submitForm('Delete');

        self::assertResponseRedirects('/volunteers');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cannot delete Aisha Njoroge');
        self::assertSelectorTextContains('body', 'Aisha Njoroge');
    }

    #[Test]
    public function theIndexShowsDeleteAsUnavailableForAVolunteerWithActivity(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        ActivityFactory::createOne(['volunteer' => $volunteer]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers');

        self::assertResponseIsSuccessful();
        // No form to submit, so nothing to confirm and then be refused.
        self::assertCount(0, $crawler->filter('table tbody form'));

        $inert = $crawler->filter('table tbody [aria-disabled="true"]');
        self::assertCount(1, $inert);
        self::assertStringContainsString('Delete', $inert->text());
        // The same sentence the flash would have shown, ahead of the attempt.
        self::assertStringContainsString(
            'Cannot delete Aisha Njoroge — 1 activity references them.',
            (string) $inert->attr('title'),
        );
    }

    #[Test]
    public function theIndexKeepsDeleteForAVolunteerWithNoActivity(): void
    {
        $client = static::createClient();
        VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers');

        self::assertCount(1, $crawler->filter('table tbody form'));
        self::assertCount(0, $crawler->filter('table tbody [aria-disabled="true"]'));
        self::assertCount(0, $crawler->filter('[data-delete-guard-note]'));
    }

    #[Test]
    public function theDeleteGuardNoteCountsOnlyTheGuardedRows(): void
    {
        $client = static::createClient();
        foreach (['Aisha Njoroge', 'Grace Wanjiru'] as $fullName) {
            [$firstName, $lastName] = explode(' ', $fullName, 2);
            ActivityFactory::createOne([
                'volunteer' => VolunteerFactory::createOne(['firstName' => $firstName, 'lastName' => $lastName]),
            ]);
        }
        VolunteerFactory::createOne(['firstName' => 'Susan', 'lastName' => 'Njoki']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers');

        $note = $crawler->filter('[data-delete-guard-note]');
        self::assertCount(1, $note);
        self::assertStringContainsString('2 volunteers on this page have logged activity', $note->text());
        self::assertCount(2, $crawler->filter('table tbody [aria-disabled="true"]'));
        self::assertCount(1, $crawler->filter('table tbody form'));
    }

    #[Test]
    public function theDeleteGuardNoteReadsAsSingularForOneRow(): void
    {
        $client = static::createClient();
        ActivityFactory::createOne(['volunteer' => VolunteerFactory::createOne()]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers');

        self::assertStringContainsString(
            '1 volunteer on this page has logged activity',
            $crawler->filter('[data-delete-guard-note]')->text(),
        );
    }

    #[Test]
    public function theIndexPaginatesAtTwentyFivePerPage(): void
    {
        $client = static::createClient();
        VolunteerFactory::createMany(26);

        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/volunteers');
        self::assertCount(25, $crawler->filter('table tbody tr'));

        $crawler = $client->request('GET', '/volunteers?page=2');
        self::assertCount(1, $crawler->filter('table tbody tr'));
    }

    #[Test]
    public function theIndexSortsByARequestedColumn(): void
    {
        $client = static::createClient();
        VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Achieng']);
        VolunteerFactory::createOne(['firstName' => 'Zawadi', 'lastName' => 'Zuma']);

        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/volunteers?sort=name&direction=asc');
        self::assertStringContainsString('Aisha Achieng', $crawler->filter('table tbody tr')->first()->text());

        $crawler = $client->request('GET', '/volunteers?sort=name&direction=desc');
        self::assertStringContainsString('Zawadi Zuma', $crawler->filter('table tbody tr')->first()->text());
    }

    /**
     * The map is the whitelist, so a stale bookmark or a hand-edited URL falls
     * back to the default order instead of erroring — the same posture
     * ListPaginator already keeps for `page` and `perPage`.
     */
    #[Test]
    public function theIndexShrugsOffAnUnknownSortColumn(): void
    {
        $client = static::createClient();
        VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Achieng']);
        VolunteerFactory::createOne(['firstName' => 'Zawadi', 'lastName' => 'Zuma']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers?sort=v.lastName&direction=sideways');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Aisha Achieng', $crawler->filter('table tbody tr')->first()->text());
    }

    /**
     * The case that would bite: `InputBag::get()` throws a BadRequestException
     * on a non-scalar, so reading `sort` through it would turn `?sort[]=name`
     * into a 400. ListPaginator goes through `query->all()` instead, and the
     * page-size form and page links both skip iterable params.
     */
    #[Test]
    public function theIndexShrugsOffAnArraySortParam(): void
    {
        $client = static::createClient();
        VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Achieng']);

        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/volunteers?sort[]=name&direction[]=desc');

        self::assertResponseIsSuccessful();
    }

    /**
     * Covers the shared DataTable header markup, not just this screen: the
     * link text has to stay the bare column label (several tests resolve
     * headers and buttons by text) with the direction carried by aria-sort
     * and an arrow outside the link.
     */
    #[Test]
    public function sortableHeadersLinkOnTheLabelAndAnnounceTheDirection(): void
    {
        $client = static::createClient();
        VolunteerFactory::createOne();

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers?sort=name&direction=asc');

        self::assertSame('Name', $crawler->filter('[data-sort-link="name"]')->text());
        self::assertSame('ascending', $crawler->filter('thead th')->first()->attr('aria-sort'));

        // A second click flips it; every other column starts over at ascending.
        self::assertStringContainsString('direction=desc', (string) $crawler->filter('[data-sort-link="name"]')->attr('href'));
        self::assertStringContainsString('direction=asc', (string) $crawler->filter('[data-sort-link="email"]')->attr('href'));
    }

    #[Test]
    public function aSortLinkKeepsThePageSizeAndStartsOverAtPageOne(): void
    {
        $client = static::createClient();
        VolunteerFactory::createMany(26);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers?page=2&perPage=50');

        $href = (string) $crawler->filter('[data-sort-link="email"]')->attr('href');

        self::assertStringContainsString('perPage=50', $href);
        // A new order means a new page 1; keeping the old offset would drop
        // the reader somewhere arbitrary in the re-sorted list.
        self::assertStringNotContainsString('page=2', $href);
    }

    /**
     * Status has two values, so without the default order kept as a tie-break
     * SQLite is free to hand back a row on page 2 that was already on page 1.
     */
    #[Test]
    public function sortingByALowCardinalityColumnStillPagesWithoutRepeatingRows(): void
    {
        $client = static::createClient();
        foreach (range(1, 26) as $number) {
            VolunteerFactory::createOne(['firstName' => 'Volunteer', 'lastName' => sprintf('Number%02d', $number)]);
        }

        $client->loginUser(UserFactory::createOne());

        $firstPage = $client->request('GET', '/volunteers?sort=status&direction=asc')->filter('table tbody tr')->each(
            static fn($row) => $row->text(),
        );
        $secondPage = $client->request('GET', '/volunteers?sort=status&direction=asc&page=2')->filter('table tbody tr')->each(
            static fn($row) => $row->text(),
        );

        self::assertCount(25, $firstPage);
        self::assertCount(1, $secondPage);
        self::assertSame([], array_intersect($firstPage, $secondPage));
    }
}
