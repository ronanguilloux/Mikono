<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\ProjectOwnership;
use App\Factory\ActivityFactory;
use App\Factory\BranchFactory;
use App\Factory\ProjectFactory;
use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ProjectControllerTest extends WebTestCase
{
    use ReadsListExports;

    #[Test]
    public function theExportCarriesTheOnScreenSortOrEveryRowInDefaultOrder(): void
    {
        $client = static::createClient();
        ProjectFactory::createOne(['name' => 'Bright Achievers']);
        ProjectFactory::createOne(['name' => 'Zion Academy']);
        $client->loginUser(UserFactory::createOne());

        $sorted = self::exportedRows($client, '/projects/export.csv?sort=name&direction=desc');
        self::assertSame(['Zion Academy', 'Bright Achievers'], array_column($sorted, 0));

        $whole = self::exportedRows($client, '/projects/export.csv');
        self::assertSame(['Bright Achievers', 'Zion Academy'], array_column($whole, 0));
    }

    #[Test]
    public function newPartnerProjectWithoutAnOrganizationNameIsUnprocessable(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/projects/new');

        $form = $crawler->selectButton('Save')->form([
            'project_form[name]' => 'Test Partner No Org',
            'project_form[branch]' => (string) BranchFactory::find(['name' => 'Mombasa'])->getId(),
            'project_form[ownership]' => 'partner',
            'project_form[partnerOrganizationName]' => '',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Enter the partner organization');
    }

    /**
     * The show/hide itself is Stimulus, so what a functional test can hold is
     * the wiring: the controller is attached, the select reports changes, the
     * row it hides is findable, and the value it compares against comes from
     * the enum rather than a string repeated in JavaScript. The rule is
     * enforced server-side regardless — see the test above.
     */
    #[Test]
    public function theProjectFormWiresUpTheConditionalPartnerField(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/projects/new');

        $form = $crawler->filter('form[data-controller="partner-field"]');
        self::assertCount(1, $form);
        self::assertSame(
            ProjectOwnership::Partner->value,
            $form->attr('data-partner-field-required-for-value'),
        );
        self::assertCount(1, $crawler->filter('select[data-partner-field-target="ownership"][data-action="partner-field#toggle"]'));
        self::assertCount(1, $crawler->filter('[data-partner-field-target="field"] input[name="project_form[partnerOrganizationName]"]'));
    }

    #[Test]
    public function newWithValidPartnerDataPersists(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/projects/new');

        $form = $crawler->selectButton('Save')->form([
            'project_form[name]' => 'Bright Achievers',
            'project_form[branch]' => (string) BranchFactory::find(['name' => 'Nairobi (HQ)'])->getId(),
            'project_form[ownership]' => 'partner',
            'project_form[partnerOrganizationName]' => 'Bright Achievers High School',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/projects');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Bright Achievers');
        self::assertSelectorTextContains('body', 'Nairobi (HQ)');
    }

    /**
     * The index no longer offers Delete once a project has activity, so the
     * way to reach the server guard is the way a reader would in real life:
     * with a page rendered before the activity existed. That stale-tab case is
     * exactly why the guard stays in the controller rather than moving into
     * the view.
     */
    #[Test]
    public function deleteIsBlockedWhenAnActivityReferencesTheProject(): void
    {
        $client = static::createClient();
        $project = ProjectFactory::createOne(['name' => 'Bright Achievers']);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/projects');

        ActivityFactory::createOne(['project' => $project]);
        $client->submitForm('Delete');

        self::assertResponseRedirects('/projects');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cannot delete Bright Achievers');
    }

    #[Test]
    public function theIndexShowsDeleteAsUnavailableForAProjectWithActivity(): void
    {
        $client = static::createClient();
        $project = ProjectFactory::createOne(['name' => 'Bright Achievers']);
        ActivityFactory::createOne(['project' => $project]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/projects');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('table tbody form'));

        $inert = $crawler->filter('table tbody [aria-disabled="true"]');
        self::assertCount(1, $inert);
        self::assertStringContainsString('Delete', $inert->text());
        self::assertStringContainsString(
            'Cannot delete Bright Achievers — 1 activity references it.',
            (string) $inert->attr('title'),
        );

        // The reason in words is on the project's own edit screen, not here.
        self::assertCount(0, $crawler->filter('[data-delete-guard-note]'));
    }

    #[Test]
    public function theIndexKeepsDeleteForAProjectWithNoActivity(): void
    {
        $client = static::createClient();
        ProjectFactory::createOne(['name' => 'Bright Achievers']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/projects');

        self::assertCount(1, $crawler->filter('table tbody form'));
        self::assertCount(0, $crawler->filter('table tbody [aria-disabled="true"]'));
    }

    #[Test]
    public function theEditScreenSaysWhyDeleteIsUnavailable(): void
    {
        $client = static::createClient();
        $project = ProjectFactory::createOne(['name' => 'Bright Achievers']);
        ActivityFactory::createOne(['project' => $project]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/projects/' . $project->getId() . '/edit');

        self::assertResponseIsSuccessful();
        $note = $crawler->filter('[data-delete-guard-note]');
        self::assertCount(1, $note);
        // Word for word what the index's inert Delete and the flash both say.
        self::assertStringContainsString(
            'Cannot delete Bright Achievers — 1 activity references it.',
            $note->text(),
        );
    }

    #[Test]
    public function theEditScreenHasNoNoteForAProjectWithNoActivity(): void
    {
        $client = static::createClient();
        $project = ProjectFactory::createOne();

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/projects/' . $project->getId() . '/edit');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-delete-guard-note]'));
    }

    #[Test]
    public function theIndexPaginatesAtTwentyFivePerPage(): void
    {
        $client = static::createClient();
        ProjectFactory::createMany(26);

        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/projects');
        self::assertCount(25, $crawler->filter('table tbody tr'));

        $crawler = $client->request('GET', '/projects?page=2');
        self::assertCount(1, $crawler->filter('table tbody tr'));
    }

    #[Test]
    public function theIndexSortsByARequestedColumn(): void
    {
        $client = static::createClient();
        ProjectFactory::createOne(['name' => 'Alpha Centre']);
        ProjectFactory::createOne(['name' => 'Zebra Centre']);

        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/projects?sort=name&direction=desc');
        self::assertStringContainsString('Zebra Centre', $crawler->filter('table tbody tr')->first()->text());

        $crawler = $client->request('GET', '/projects?sort=name&direction=asc');
        self::assertStringContainsString('Alpha Centre', $crawler->filter('table tbody tr')->first()->text());
    }

    /** Branch sorts by the branch's name, through the index query's join. */
    #[Test]
    public function theIndexSortsByBranchName(): void
    {
        $client = static::createClient();
        ProjectFactory::createOne(['name' => 'Aardvark Project', 'branch' => BranchFactory::find(['name' => 'Samburu'])]);
        ProjectFactory::createOne(['name' => 'Zebra Project', 'branch' => BranchFactory::find(['name' => 'Mombasa'])]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/projects?sort=branch&direction=asc');

        self::assertStringContainsString('Zebra Project', $crawler->filter('table tbody tr')->first()->text());
    }

    #[Test]
    public function theIndexShrugsOffAnUnknownSortColumn(): void
    {
        $client = static::createClient();
        ProjectFactory::createOne(['name' => 'Alpha Centre']);
        ProjectFactory::createOne(['name' => 'Zebra Centre']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/projects?sort=partnerOrganizationName&direction=desc');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Alpha Centre', $crawler->filter('table tbody tr')->first()->text());
    }

    #[Test]
    public function aProjectCannotBeMovedAwayFromItsActivitiesStays(): void
    {
        $client = static::createClient();
        // Both default to Nairobi (HQ): the activity's stay is there.
        $project = ProjectFactory::createOne(['name' => 'Peggy Lucas school']);
        ActivityFactory::createOne(['project' => $project]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/projects/{$project->getId()}/edit");
        $client->submit($crawler->selectButton('Save')->form([
            'project_form[branch]' => (string) BranchFactory::find(['name' => 'Mombasa'])->getId(),
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', '1 activity logged here belongs to stays at another branch.');
    }
}
