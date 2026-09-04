<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\ProjectLocation;
use App\Enum\ProjectOwnership;
use App\Factory\ActivityFactory;
use App\Factory\ProjectFactory;
use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ProjectControllerTest extends WebTestCase
{
    #[Test]
    public function newPartnerProjectWithoutAnOrganizationNameIsUnprocessable(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/projects/new');

        $form = $crawler->selectButton('Save')->form([
            'project_form[name]' => 'Test Partner No Org',
            'project_form[location]' => 'mombasa',
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
            'project_form[location]' => 'kibera',
            'project_form[ownership]' => 'partner',
            'project_form[partnerOrganizationName]' => 'Bright Achievers High School',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/projects');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Bright Achievers');
        self::assertSelectorTextContains('body', 'Kibera (Nairobi)');
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

        self::assertStringContainsString(
            '1 project on this page has logged activity',
            $crawler->filter('[data-delete-guard-note]')->text(),
        );
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

    /**
     * Location and Ownership sort by the enum's stored backing value rather
     * than its label(). For both enums today those two orders coincide, and
     * this pins that: kibera < mombasa reads as Kibera (Nairobi) first.
     */
    #[Test]
    public function theIndexSortsEnumColumnsInLabelOrder(): void
    {
        $client = static::createClient();
        ProjectFactory::createOne(['name' => 'Coast Project', 'location' => ProjectLocation::Mombasa]);
        ProjectFactory::createOne(['name' => 'Slum Project', 'location' => ProjectLocation::Kibera]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/projects?sort=location&direction=asc');

        self::assertStringContainsString('Kibera (Nairobi)', $crawler->filter('table tbody tr')->first()->text());
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
}
