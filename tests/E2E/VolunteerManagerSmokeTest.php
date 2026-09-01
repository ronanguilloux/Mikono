<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Enum\ProjectLocation;
use App\Factory\ActivityTypeFactory;
use App\Factory\ProjectFactory;
use App\Factory\UserFactory;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverSelect;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Panther\PantherTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
#[SkipDatabaseRollback]
final class VolunteerManagerSmokeTest extends PantherTestCase
{
    #[Test]
    public function loginCreateVolunteerAndActivityShowUpInListAndReports(): void
    {
        UserFactory::createOne(['email' => 'e2e@example.test']);
        $project = ProjectFactory::new()->partner()->create([
            'name' => 'Bright Achievers',
            'location' => ProjectLocation::Kibera,
            'partnerOrganizationName' => 'Bright Achievers High School',
        ]);
        $activityType = ActivityTypeFactory::createOne(['name' => 'Computer lessons']);

        $client = static::createPantherClient();

        // Turbo Drive intercepts every form submission and swaps the page in
        // asynchronously, so each submit needs an explicit wait for the
        // resulting navigation before asserting on the new page.
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'e2e@example.test',
            '_password' => 'password-1234',
        ]);
        $client->submit($form);
        // Signing in lands on the work-focused home screen, not Reports.
        $client->waitForVisibility('#today-heading');
        self::assertSelectorTextContains('#today-heading', "Today's roster");

        $crawler = $client->request('GET', '/volunteers/new');
        $form = $crawler->selectButton('Save')->form([
            'volunteer_form[firstName]' => 'Ronan',
            'volunteer_form[lastName]' => 'Guilloux',
            'volunteer_form[email]' => 'ronan.guilloux@gmail.com',
        ]);
        $client->submit($form);
        $client->wait()->until(static fn(RemoteWebDriver $driver) => !str_contains($driver->getCurrentURL(), '/new'));
        self::assertSelectorTextContains('body', 'Ronan Guilloux');

        $volunteerRepository = self::getContainer()->get('doctrine')->getRepository(\App\Entity\Volunteer::class);
        $volunteer = $volunteerRepository->findOneBy(['firstName' => 'Ronan', 'lastName' => 'Guilloux']);
        \assert($volunteer instanceof \App\Entity\Volunteer);

        $client->request('GET', '/activities/new');
        $client->executeScript(
            "document.querySelector('input[name=\"activity_form[date]\"]').value = '2026-08-11';",
        );
        (new WebDriverSelect($client->findElement(WebDriverBy::name('activity_form[volunteer]'))))
            ->selectByValue((string) $volunteer->getId());
        (new WebDriverSelect($client->findElement(WebDriverBy::name('activity_form[project]'))))
            ->selectByValue((string) $project->getId());
        (new WebDriverSelect($client->findElement(WebDriverBy::name('activity_form[activityType]'))))
            ->selectByValue((string) $activityType->getId());
        $client->findElement(WebDriverBy::cssSelector('input[value="full_day"]'))->click();
        $client->findElement(WebDriverBy::cssSelector('button[type=submit]'))->click();
        $client->wait()->until(static fn(RemoteWebDriver $driver) => !str_contains($driver->getCurrentURL(), '/new'));

        self::assertSelectorTextContains('body', 'Ronan Guilloux');
        self::assertSelectorTextContains('body', 'Bright Achievers');
        self::assertSelectorTextContains('body', 'Computer lessons');

        $client->request('GET', '/reports');
        self::assertSelectorTextContains('body', 'Ronan Guilloux');

        // The breakdowns are tabbed now, and unlike the DomCrawler used by the
        // functional tests, Panther reads only what is actually visible — the
        // project name lives on the other tab (and in the print-only panel).
        $client->request('GET', '/reports?tab=project');
        self::assertSelectorTextContains('body', 'Bright Achievers');

        $client->getWebDriver()->manage()->window()->setSize(new WebDriverDimension(375, 812));
        self::assertSelectorIsNotVisible('[data-nav-target="menu"]');
        $client->findElement(WebDriverBy::cssSelector('[data-action="nav#toggle"]'))->click();
        self::assertSelectorIsVisible('[data-nav-target="menu"]');
    }
}
