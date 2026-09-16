<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Activity;
use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\BranchFactory;
use App\Factory\EscortFactory;
use App\Factory\ProgramFactory;
use App\Factory\ProjectFactory;
use App\Factory\StayFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ActivityControllerTest extends WebTestCase
{
    use ReadsListExports;

    /**
     * Current view keeps the volunteer filter; whole list drops it (ADR 0029).
     */
    #[Test]
    public function theExportKeepsTheVolunteerFilterOrExportsEveryRow(): void
    {
        $client = static::createClient();
        $aisha = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Achieng']);
        ActivityFactory::createMany(2, ['volunteer' => $aisha]);
        ActivityFactory::createOne(['volunteer' => VolunteerFactory::createOne(['firstName' => 'Zawadi', 'lastName' => 'Zuma'])]);
        $client->loginUser(UserFactory::createOne());

        $filtered = self::exportedRows($client, '/activities/export.csv?volunteer=' . $aisha->getId() . '&page=2');
        self::assertSame(['Aisha Achieng', 'Aisha Achieng'], array_column($filtered, 1));

        self::assertCount(3, self::exportedRows($client, '/activities/export.csv'));
    }

    /**
     * ADR 0023: a malformed parameter on the export URL degrades to the
     * default, exactly as it does on the list.
     */
    #[Test]
    public function theExportShrugsOffMalformedParameters(): void
    {
        $client = static::createClient();
        ActivityFactory::createMany(3);
        $client->loginUser(UserFactory::createOne());

        $rows = self::exportedRows($client, '/activities/export.csv?volunteer[]=1&sort[]=x&direction[]=y&page[]=1&perPage=abc&format[]=z');
        self::assertCount(3, $rows);
    }

    #[Test]
    public function theExportAlsoComesAsARealSpreadsheet(): void
    {
        $client = static::createClient();
        ActivityFactory::createOne();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/activities/export.xlsx');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        self::assertResponseHeaderSame(
            'Content-Disposition',
            sprintf('attachment; filename=activities-%s.xlsx', new \DateTimeImmutable('today')->format('Y-m-d')),
        );
        // A zip archive, which is what an .xlsx file is.
        self::assertStringStartsWith("PK\x03\x04", $client->getInternalResponse()->getContent());
    }

    /**
     * Escorts are a collection (ADR 0013): the column lists every name, has no
     * sort link, reaches the export, and shows on a mobile card only when set.
     */
    #[Test]
    public function theIndexAndExportListEveryEscortWithoutMakingThemSortable(): void
    {
        $client = static::createClient();
        ActivityFactory::createOne([
            'date' => new \DateTimeImmutable('2026-08-31'),
            'escorts' => [EscortFactory::createOne(['name' => 'Edna']), EscortFactory::createOne(['name' => 'Sam'])],
        ]);
        ActivityFactory::createOne(['date' => new \DateTimeImmutable('2026-08-30'), 'escorts' => []]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/activities');

        self::assertSame('Accompanied by', $crawler->filter('thead th')->eq(4)->text());
        self::assertCount(0, $crawler->filter('[data-sort-link="escorts"]'));
        self::assertSame('Edna, Sam', $crawler->filter('table tbody tr')->eq(0)->filter('td')->eq(4)->text());
        self::assertSame('—', $crawler->filter('table tbody tr')->eq(1)->filter('td')->eq(4)->text());

        $cards = $crawler->filter('[data-activity-cards] > li');
        self::assertStringContainsString('Accompanied by Edna, Sam', $cards->eq(0)->text());
        self::assertStringNotContainsString('Accompanied by', $cards->eq(1)->text());

        self::assertSame(['Edna, Sam', '—'], array_column(self::exportedRows($client, '/activities/export.csv'), 4));
        self::assertStringContainsString('Accompanied by', $client->getInternalResponse()->getContent());
    }

    /**
     * The batch form's "Who attended?" field is a group of same-named
     * checkboxes (one per volunteer) — DomCrawler's array-value form
     * shorthand can't target them by entity id (it matches by DOM
     * position instead), so we tick the right DOM nodes directly before
     * building the Form object.
     *
     * @param array<\App\Entity\Volunteer> $volunteers
     */
    private function checkVolunteers(Crawler $crawler, array $volunteers): void
    {
        $ids = array_map(static fn($volunteer) => (string) $volunteer->getId(), $volunteers);
        $crawler->filter('[data-batch-activity-form-target="checkboxes"] input[type="checkbox"]')->each(
            static function (Crawler $node) use ($ids): void {
                $domNode = $node->getNode(0);
                if ($domNode instanceof \DOMElement && \in_array($node->attr('value'), $ids, true)) {
                    $domNode->setAttribute('checked', 'checked');
                }
            },
        );
    }

    /**
     * Ticks exactly the given escort checkboxes, by value — the expanded
     * field's indexes follow the escorts' alphabetical order, which is not
     * something a test should have to know. Passing an empty list clears
     * them: handing form() an empty array is a no-op, not a clear.
     *
     * @param list<int> $ids
     */
    private function checkEscorts(Crawler $crawler, string $field, array $ids): void
    {
        $wanted = array_map(static fn(int $id) => (string) $id, $ids);
        $crawler->filter(sprintf('input[type="checkbox"][name^="%s"]', $field))->each(
            static function (Crawler $node) use ($wanted): void {
                $domNode = $node->getNode(0);
                if (!$domNode instanceof \DOMElement) {
                    return;
                }

                if (\in_array($node->attr('value'), $wanted, true)) {
                    $domNode->setAttribute('checked', 'checked');
                } else {
                    $domNode->removeAttribute('checked');
                }
            },
        );
    }

    /**
     * Re-reads an Activity through a cleared entity manager. The manager
     * still holds the pre-submission object in its identity map, so a
     * plain find() after a form submission can hand back stale state.
     */
    private function reloadActivity(KernelBrowser $client, int $id): Activity
    {
        $manager = $client->getContainer()->get('doctrine')->getManager();
        $manager->clear();
        $activity = $manager->getRepository(Activity::class)->find($id);
        self::assertInstanceOf(Activity::class, $activity);

        return $activity;
    }

    #[Test]
    public function theBrightAchieversWorkedExampleCanBeLoggedEndToEnd(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Ronan', 'lastName' => 'Guilloux']);
        $project = ProjectFactory::new()->partner()->create([
            'name' => 'Bright Achievers',
            'partnerOrganizationName' => 'Bright Achievers High School',
        ]);
        $activityType = ActivityTypeFactory::createOne(['name' => 'Computer lessons']);
        $program = ProgramFactory::createOne(['project' => $project, 'activityTypes' => [$activityType]]);
        $escort = EscortFactory::createOne(['name' => 'Mr Maeba']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new');

        $form = $crawler->selectButton('Save')->form([
            'activity_form[date]' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'activity_form[volunteer]' => (string) $volunteer->getId(),
            'activity_form[program]' => (string) $program->getId(),
            'activity_form[activityType]' => (string) $activityType->getId(),
            'activity_form[duration]' => 'full_day',
            'activity_form[escorts]' => [(string) $escort->getId()],
            'activity_form[notes]' => 'Delivered Computer lessons to students',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/activities');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Ronan Guilloux');
        self::assertSelectorTextContains('body', 'Bright Achievers');
        self::assertSelectorTextContains('body', 'Computer lessons');
        self::assertSelectorTextContains('body', 'Full day');

        $loggedActivity = $client->getContainer()->get('doctrine')->getRepository(Activity::class)->findOneBy([]);
        self::assertInstanceOf(Activity::class, $loggedActivity);
        self::assertSame(['Mr Maeba'], $loggedActivity->getEscortNames());

        $client->request('GET', '/reports');
        self::assertSelectorTextContains('body', 'Ronan Guilloux');
        self::assertSelectorTextContains('body', 'Bright Achievers');
    }

    #[Test]
    public function loggedByIsSetFromTheAuthenticatedUserAndUntouchedOnEdit(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne();
        $project = ProjectFactory::createOne();
        $activityType = ActivityTypeFactory::createOne();
        $program = ProgramFactory::createOne(['project' => $project, 'activityTypes' => [$activityType]]);
        $creator = UserFactory::createOne(['fullName' => 'Original Logger']);
        $editor = UserFactory::createOne(['fullName' => 'Different Editor']);
        $client->loginUser($creator);
        $crawler = $client->request('GET', '/activities/new');
        $form = $crawler->selectButton('Save')->form([
            'activity_form[date]' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'activity_form[volunteer]' => (string) $volunteer->getId(),
            'activity_form[program]' => (string) $program->getId(),
            'activity_form[activityType]' => (string) $activityType->getId(),
            'activity_form[duration]' => 'half_day',
        ]);
        $client->submit($form);

        $activityRepository = $client->getContainer()->get('doctrine')->getRepository(Activity::class);
        $activityId = $activityRepository->findOneBy([])->getId();

        $client->loginUser($editor);
        $crawler = $client->request('GET', "/activities/{$activityId}/edit");
        $form = $crawler->selectButton('Save')->form([
            'activity_form[notes]' => 'fixed a typo',
        ]);
        $client->submit($form);

        $activity = $activityRepository->find($activityId);
        self::assertSame('Original Logger', $activity->getLoggedBy()->getFullName());
    }

    #[Test]
    public function escortsCanBeSetAndClearedFromTheSingleActivityEditForm(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne();
        $project = ProjectFactory::createOne();
        $activityType = ActivityTypeFactory::createOne();
        $program = ProgramFactory::createOne(['project' => $project, 'activityTypes' => [$activityType]]);
        $escort = EscortFactory::createOne(['name' => 'Mr Maeba']);
        // Two escorts on one group is a real roster line — see ADR 0013.
        $secondEscort = EscortFactory::createOne(['name' => 'Ms Njeri']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new');
        $form = $crawler->selectButton('Save')->form([
            'activity_form[date]' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'activity_form[volunteer]' => (string) $volunteer->getId(),
            'activity_form[program]' => (string) $program->getId(),
            'activity_form[activityType]' => (string) $activityType->getId(),
            'activity_form[duration]' => 'half_day',
        ]);
        $client->submit($form);

        $logged = $client->getContainer()->get('doctrine')->getRepository(Activity::class)->findOneBy([]);
        self::assertInstanceOf(Activity::class, $logged);
        $activityId = (int) $logged->getId();
        self::assertSame([], $logged->getEscortNames());

        $crawler = $client->request('GET', "/activities/{$activityId}/edit");
        $this->checkEscorts($crawler, 'activity_form[escorts]', [(int) $escort->getId(), (int) $secondEscort->getId()]);
        $client->submit($crawler->selectButton('Save')->form());

        self::assertSame(['Mr Maeba', 'Ms Njeri'], $this->reloadActivity($client, $activityId)->getEscortNames());

        $crawler = $client->request('GET', "/activities/{$activityId}/edit");
        $this->checkEscorts($crawler, 'activity_form[escorts]', []);
        $client->submit($crawler->selectButton('Save')->form());

        self::assertSame([], $this->reloadActivity($client, $activityId)->getEscortNames());
    }

    #[Test]
    public function otherDurationRequiresAFreeTextValue(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne();
        $project = ProjectFactory::createOne();
        $activityType = ActivityTypeFactory::createOne();
        $program = ProgramFactory::createOne(['project' => $project, 'activityTypes' => [$activityType]]);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new');

        $form = $crawler->selectButton('Save')->form([
            'activity_form[date]' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'activity_form[volunteer]' => (string) $volunteer->getId(),
            'activity_form[program]' => (string) $program->getId(),
            'activity_form[activityType]' => (string) $activityType->getId(),
            'activity_form[duration]' => 'other',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Please specify the duration when choosing "Other".');
    }

    #[Test]
    public function otherDurationWithAFreeTextValueIsAccepted(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne();
        $project = ProjectFactory::createOne();
        $activityType = ActivityTypeFactory::createOne();
        $program = ProgramFactory::createOne(['project' => $project, 'activityTypes' => [$activityType]]);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new');

        $form = $crawler->selectButton('Save')->form([
            'activity_form[date]' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'activity_form[volunteer]' => (string) $volunteer->getId(),
            'activity_form[program]' => (string) $program->getId(),
            'activity_form[activityType]' => (string) $activityType->getId(),
            'activity_form[duration]' => 'other',
            'activity_form[durationOther]' => '2.5h',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/activities');
        $client->followRedirect();
        self::assertSelectorTextContains('body', '2.5h');
    }

    #[Test]
    public function aBatchLogsOneActivityPerSelectedVolunteer(): void
    {
        $client = static::createClient();
        $volunteerA = VolunteerFactory::createOne(['firstName' => 'Ann', 'lastName' => 'Wambui']);
        $volunteerB = VolunteerFactory::createOne(['firstName' => 'Daniel', 'lastName' => 'Otieno']);
        $project = ProjectFactory::createOne(['name' => 'Beyond Zero clinic']);
        $activityType = ActivityTypeFactory::createOne(['name' => 'Clinic support']);
        $program = ProgramFactory::createOne(['project' => $project, 'activityTypes' => [$activityType]]);
        $escort = EscortFactory::createOne(['name' => 'Mr Maeba']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new-batch');
        $this->checkVolunteers($crawler, [$volunteerA, $volunteerB]);

        $form = $crawler->selectButton('Save')->form([
            'batch_activity_form[date]' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'batch_activity_form[program]' => (string) $program->getId(),
            'batch_activity_form[activityType]' => (string) $activityType->getId(),
            'batch_activity_form[duration]' => 'half_day',
            'batch_activity_form[escorts]' => [(string) $escort->getId()],
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/activities');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Logged 2 activities');

        $activityRepository = $client->getContainer()->get('doctrine')->getRepository(Activity::class);
        $activities = $activityRepository->findAll();
        self::assertCount(2, $activities);
        foreach ($activities as $activity) {
            $activityProject = $activity->getProject();
            self::assertNotNull($activityProject);
            self::assertSame('Beyond Zero clinic', $activityProject->getName());
            self::assertSame(['Mr Maeba'], $activity->getEscortNames());
        }
    }

    #[Test]
    public function savingAndAddingAnotherReturnsToTheBatchForm(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne();
        $project = ProjectFactory::createOne();
        $activityType = ActivityTypeFactory::createOne();
        $program = ProgramFactory::createOne(['project' => $project, 'activityTypes' => [$activityType]]);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new-batch');
        $this->checkVolunteers($crawler, [$volunteer]);

        $form = $crawler->selectButton('Save and add another')->form([
            'batch_activity_form[date]' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'batch_activity_form[program]' => (string) $program->getId(),
            'batch_activity_form[activityType]' => (string) $activityType->getId(),
            'batch_activity_form[duration]' => 'half_day',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/activities/new-batch');
    }

    #[Test]
    public function aBatchRequiresAtLeastOneVolunteer(): void
    {
        $client = static::createClient();
        $project = ProjectFactory::createOne();
        $activityType = ActivityTypeFactory::createOne();
        $program = ProgramFactory::createOne(['project' => $project, 'activityTypes' => [$activityType]]);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new-batch');

        $form = $crawler->selectButton('Save')->form([
            'batch_activity_form[date]' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'batch_activity_form[program]' => (string) $program->getId(),
            'batch_activity_form[activityType]' => (string) $activityType->getId(),
            'batch_activity_form[duration]' => 'half_day',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Select at least one volunteer.');
    }

    #[Test]
    public function aBatchWithOtherDurationRequiresAFreeTextValue(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne();
        $project = ProjectFactory::createOne();
        $activityType = ActivityTypeFactory::createOne();
        $program = ProgramFactory::createOne(['project' => $project, 'activityTypes' => [$activityType]]);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new-batch');
        $this->checkVolunteers($crawler, [$volunteer]);

        $form = $crawler->selectButton('Save')->form([
            'batch_activity_form[date]' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'batch_activity_form[program]' => (string) $program->getId(),
            'batch_activity_form[activityType]' => (string) $activityType->getId(),
            'batch_activity_form[duration]' => 'other',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Please specify the duration when choosing "Other".');
    }

    #[Test]
    public function inactiveVolunteersAreNotOfferedOnTheSingleActivityForm(): void
    {
        $client = static::createClient();
        $active = VolunteerFactory::createOne(['firstName' => 'Still', 'lastName' => 'Here']);
        $gone = VolunteerFactory::new()->inactive()->create(['firstName' => 'Long', 'lastName' => 'Gone']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new');

        $offered = $crawler->filter('#activity_form_volunteer option')->extract(['value']);
        self::assertContains((string) $active->getId(), $offered);
        self::assertNotContains((string) $gone->getId(), $offered);
    }

    #[Test]
    public function inactiveVolunteersAreNotOfferedOnTheBatchForm(): void
    {
        $client = static::createClient();
        $active = VolunteerFactory::createOne(['firstName' => 'Still', 'lastName' => 'Here']);
        $gone = VolunteerFactory::new()->inactive()->create(['firstName' => 'Long', 'lastName' => 'Gone']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new-batch');

        $offered = $crawler
            ->filter('[data-batch-activity-form-target="checkboxes"] input[type="checkbox"]')
            ->extract(['value']);
        self::assertContains((string) $active->getId(), $offered);
        self::assertNotContains((string) $gone->getId(), $offered);
    }

    #[Test]
    public function editingAnOldActivityKeepsItsSinceDeactivatedVolunteerSelectable(): void
    {
        // Otherwise fixing a typo on a historical entry would force
        // reassigning it to somebody who wasn't there.
        $client = static::createClient();
        $gone = VolunteerFactory::new()->inactive()->create(['firstName' => 'Long', 'lastName' => 'Gone']);
        $activity = ActivityFactory::createOne(['volunteer' => $gone]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', sprintf('/activities/%d/edit', $activity->getId()));

        self::assertResponseIsSuccessful();
        self::assertContains(
            (string) $gone->getId(),
            $crawler->filter('#activity_form_volunteer option')->extract(['value']),
        );
        self::assertSame(
            (string) $gone->getId(),
            $crawler->filter('#activity_form_volunteer option[selected]')->attr('value'),
        );
    }

    #[Test]
    public function theIndexRendersOneMobileCardPerActivityAlongsideTheDesktopTable(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Naomi', 'lastName' => 'Cherop']);
        $activityType = ActivityTypeFactory::createOne(['name' => 'Sports']);
        $program = ProgramFactory::createOne([
            'name' => 'Sports afternoons',
            'project' => ProjectFactory::createOne(['name' => 'Toi School Field']),
            'activityTypes' => [$activityType],
        ]);
        ActivityFactory::createMany(2, [
            'volunteer' => $volunteer,
            'program' => $program,
            'activityType' => $activityType,
            'date' => new \DateTimeImmutable('yesterday'),
        ]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities');

        self::assertResponseIsSuccessful();
        // Both renderings ship in the same response — CSS decides which one
        // is displayed, so the desktop table must survive the card addition.
        self::assertCount(2, $crawler->filter('table tbody tr'));
        $cards = $crawler->filter('[data-activity-cards] > li');
        self::assertCount(2, $cards);
        self::assertStringContainsString('Naomi Cherop', $cards->first()->text());
        self::assertStringContainsString('Toi School Field — Sports afternoons · Sports', $cards->first()->text());
        // Delete stays a real CSRF-protected POST inside the card too.
        self::assertCount(2, $crawler->filter('[data-activity-cards] form[method="post"] input[name="_token"]'));
    }

    #[Test]
    public function deleteRemovesAnActivity(): void
    {
        $client = static::createClient();
        ActivityFactory::createOne();
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/activities');
        $client->submitForm('Delete');

        self::assertResponseRedirects('/activities');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'No activities logged yet');
    }

    #[Test]
    public function deleteWithATamperedTokenIsRefused(): void
    {
        $client = static::createClient();
        $activity = ActivityFactory::createOne();
        $client->loginUser(UserFactory::createOne());

        // The token now comes from Twig's csrf_token() rather than from a
        // manager injected into the controller — the branch that catches a
        // wrong one has to stay a flash and a redirect, not a 500.
        $client->request('POST', sprintf('/activities/%d/delete', $activity->getId()), ['_token' => 'not-the-token']);

        self::assertResponseRedirects('/activities');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Invalid security token');
        self::assertCount(1, $client->getCrawler()->filter('table tbody tr'));
    }

    #[Test]
    public function aFutureDatedActivityIsTaggedAsPlannedOnItsMobileCard(): void
    {
        $client = static::createClient();
        ActivityFactory::createOne(['date' => new \DateTimeImmutable('tomorrow')]);
        ActivityFactory::createOne(['date' => new \DateTimeImmutable('today')]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities');

        $cards = $crawler->filter('[data-activity-cards] > li');
        self::assertCount(2, $cards);
        self::assertStringContainsString('Planned', $cards->eq(0)->text());
        self::assertStringNotContainsString('Planned', $cards->eq(1)->text());
    }

    #[Test]
    public function theIndexPaginatesAndTheMobileCardsFollowTheSamePage(): void
    {
        // The two renderings read the same rows, so a page that moved one and
        // not the other would be a silent desktop/mobile split.
        $client = static::createClient();
        ActivityFactory::createMany(26);

        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/activities');
        self::assertCount(25, $crawler->filter('table tbody tr'));
        self::assertCount(25, $crawler->filter('[data-activity-cards] > li'));

        $crawler = $client->request('GET', '/activities?page=2');
        self::assertCount(1, $crawler->filter('table tbody tr'));
        self::assertCount(1, $crawler->filter('[data-activity-cards] > li'));
    }

    #[Test]
    public function thePaginationControlsSitOutsideTheTableAndStayReachableOnMobile(): void
    {
        $client = static::createClient();
        ActivityFactory::createMany(26);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities');

        // Page links inside <table> would be invalid markup and would break
        // every `table tbody tr` count in this file.
        self::assertCount(0, $crawler->filter('table [data-pagination]'));
        self::assertCount(1, $crawler->filter('[data-pagination]'));
        // ...and outside the `hidden md:block` wrapper, or the card list would
        // be capped at one page with no way forward.
        self::assertCount(0, $crawler->filter('.md\\:hidden [data-pagination-bar]'));
        self::assertCount(1, $crawler->filter('[data-pagination-bar]'));
    }

    /**
     * Volunteer, Project and Activity type all sort on a joined name. The
     * three joins are to-one, so the page size still means what it says.
     */
    #[Test]
    public function theIndexSortsOnAJoinedColumn(): void
    {
        $client = static::createClient();
        ActivityFactory::createOne(['volunteer' => VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Achieng'])]);
        ActivityFactory::createOne(['volunteer' => VolunteerFactory::createOne(['firstName' => 'Zawadi', 'lastName' => 'Zuma'])]);

        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/activities?sort=volunteer&direction=asc');
        self::assertStringContainsString('Aisha Achieng', $crawler->filter('table tbody tr')->first()->text());

        $crawler = $client->request('GET', '/activities?sort=volunteer&direction=desc');
        self::assertStringContainsString('Zawadi Zuma', $crawler->filter('table tbody tr')->first()->text());
    }

    /**
     * Duration is stored as the enum values half_day/full_day/other alongside
     * a free-text durationOther, so any ORDER BY on it is arbitrary — it stays
     * out of the sort map, and the header stays plain text.
     */
    #[Test]
    public function theDurationHeaderIsNotSortable(): void
    {
        $client = static::createClient();
        ActivityFactory::createOne();

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities');

        self::assertCount(1, $crawler->filter('[data-sort-link="date"]'));
        self::assertCount(0, $crawler->filter('[data-sort-link="duration"]'));
        self::assertSame('Duration', $crawler->filter('thead th')->eq(6)->text());
    }

    #[Test]
    public function theIndexShrugsOffAnUnknownSortColumn(): void
    {
        $client = static::createClient();
        ActivityFactory::createOne(['date' => new \DateTimeImmutable('2026-01-05')]);
        ActivityFactory::createOne(['date' => new \DateTimeImmutable('2026-02-05')]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities?sort=duration&direction=asc');

        self::assertResponseIsSuccessful();
        // Untouched default order: newest first.
        self::assertStringContainsString('5 Feb 2026', $crawler->filter('table tbody tr')->first()->text());
    }

    /**
     * The cards have no header row to click, so the mobile control has to
     * emit the same two params — and it has to live outside the table's
     * `hidden md:block` wrapper, like the pagination bar does.
     */
    #[Test]
    public function theMobileSortControlOffersTheSameColumnsAsTheHeaders(): void
    {
        $client = static::createClient();
        ActivityFactory::createOne();

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities');

        self::assertCount(1, $crawler->filter('[data-sort-select]'));
        self::assertCount(0, $crawler->filter('.hidden.md\\:block [data-sort-select]'));

        // The five sortable columns plus the "Newest first" default, and never
        // Duration — the desktop headers and this control offer the same set.
        $options = $crawler->filter('[data-sort-select] select[name="sort"] option')->each(
            static fn(Crawler $option) => $option->attr('value'),
        );
        self::assertSame(['', 'date', 'volunteer', 'project', 'program', 'activityType'], $options);

        self::assertCount(1, $crawler->filter('[data-sort-select] select[name="direction"]'));
    }

    /**
     * Both renderings read the same rows, so a sort that moved one and not the
     * other would be a silent desktop/mobile split.
     */
    #[Test]
    public function theMobileCardsFollowTheSameSortAsTheTable(): void
    {
        $client = static::createClient();
        ActivityFactory::createOne(['volunteer' => VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Achieng'])]);
        ActivityFactory::createOne(['volunteer' => VolunteerFactory::createOne(['firstName' => 'Zawadi', 'lastName' => 'Zuma'])]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities?sort=volunteer&direction=desc');

        self::assertStringContainsString('Zawadi Zuma', $crawler->filter('table tbody tr')->first()->text());
        self::assertStringContainsString('Zawadi Zuma', $crawler->filter('[data-activity-cards] > li')->first()->text());
    }

    /**
     * Both renderings read the same rows, so a filter that narrowed one and
     * not the other would be a silent desktop/mobile split.
     */
    #[Test]
    public function theIndexFiltersByASingleVolunteer(): void
    {
        $client = static::createClient();
        $aisha = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Achieng']);
        ActivityFactory::createMany(2, ['volunteer' => $aisha]);
        ActivityFactory::createOne(['volunteer' => VolunteerFactory::createOne(['firstName' => 'Zawadi', 'lastName' => 'Zuma'])]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities?volunteer=' . $aisha->getId());

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('table tbody tr'));
        self::assertCount(2, $crawler->filter('[data-activity-cards] > li'));
        self::assertStringNotContainsString('Zawadi Zuma', $crawler->filter('table')->text());
        // The heading says whose activities these are.
        self::assertStringContainsString('Aisha Achieng', $crawler->filter('h1')->text());
    }

    /**
     * /reports' program tab links here; the filter narrows both renderings
     * and the export, and keeps the volunteer filter alongside it.
     */
    #[Test]
    public function theIndexAndExportFilterByAProgram(): void
    {
        $client = static::createClient();
        $aisha = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Achieng']);
        $tuition = ProgramFactory::createOne([
            'name' => 'Computer Tuition',
            'project' => ProjectFactory::createOne(['name' => 'Peggy Lucas school']),
        ]);
        ActivityFactory::createMany(2, ['program' => $tuition, 'volunteer' => $aisha]);
        ActivityFactory::createOne(['program' => $tuition]);
        ActivityFactory::createOne(['volunteer' => $aisha]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities?program=' . $tuition->getId());

        self::assertCount(3, $crawler->filter('table tbody tr'));
        self::assertCount(3, $crawler->filter('[data-activity-cards] > li'));
        self::assertStringContainsString('Peggy Lucas school, Computer Tuition', $crawler->filter('h1')->text());
        self::assertSame(
            (string) $tuition->getId(),
            $crawler->filter('#program-filter option[selected]')->attr('value'),
        );
        self::assertSame('Nairobi (HQ)', $crawler->filter('#program-filter optgroup')->attr('label'));

        $both = sprintf('/activities?program=%d&volunteer=%d', $tuition->getId(), $aisha->getId());
        self::assertCount(2, $client->request('GET', $both)->filter('table tbody tr'));

        self::assertCount(3, self::exportedRows($client, '/activities/export.csv?program=' . $tuition->getId()));
    }

    #[Test]
    public function theIndexShrugsOffAnUnusableProgramFilter(): void
    {
        $client = static::createClient();
        ActivityFactory::createMany(3);
        $client->loginUser(UserFactory::createOne());

        foreach (['abc', '0', '999999', '', '[]=1'] as $value) {
            $url = str_starts_with($value, '[') ? '/activities?program' . $value : '/activities?program=' . $value;
            $crawler = $client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertCount(3, $crawler->filter('table tbody tr'), sprintf('%s should not filter', $url));
        }
    }

    /**
     * Same contract as the sort and page params: bad input never 400s or 404s,
     * it just leaves the list alone. `volunteer[]=1` is the one that would
     * throw if the controller read it through InputBag::get()/getInt().
     */
    #[Test]
    public function theIndexShrugsOffAnUnusableVolunteerFilter(): void
    {
        $client = static::createClient();
        ActivityFactory::createMany(3);

        $client->loginUser(UserFactory::createOne());

        foreach (['abc', '0', '999999', ''] as $value) {
            $crawler = $client->request('GET', '/activities?volunteer=' . $value);
            self::assertResponseIsSuccessful();
            self::assertCount(3, $crawler->filter('table tbody tr'), sprintf('?volunteer=%s should not filter', $value));
        }

        $crawler = $client->request('GET', '/activities?volunteer[]=1');
        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('table tbody tr'));
    }

    /**
     * The batch form's two prefill params get the same contract as the index's
     * filter. These matter more than they look: the home screen links straight
     * to `?project=<id>` and `?date=<Y-m-d>`, so a truncated or hand-edited
     * bookmark of a real link is the failure mode, and `getInt()`/`get()` would
     * answer it with a 400.
     */
    #[Test]
    public function theBatchFormShrugsOffUnusablePrefillParams(): void
    {
        $client = static::createClient();
        ProjectFactory::createOne(['name' => 'Kibera Library']);

        $client->loginUser(UserFactory::createOne());

        foreach (['?project=abc', '?project[]=1', '?project=0', '?project=999999', '?date[]=x', '?date=nonsense'] as $query) {
            $client->request('GET', '/activities/new-batch' . $query);
            self::assertResponseIsSuccessful(sprintf('%s should render the form, not fail', $query));
        }
    }

    /**
     * The filter lives in the query string beside `page` and `sort`, so every
     * other control has to carry it — drop it and the filter dies on the
     * second page or the first sort click.
     */
    #[Test]
    public function theVolunteerFilterSurvivesSortingAndPaging(): void
    {
        $client = static::createClient();
        $aisha = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Achieng']);
        ActivityFactory::createMany(26, ['volunteer' => $aisha]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities?volunteer=' . $aisha->getId());

        $needle = 'volunteer=' . $aisha->getId();
        self::assertStringContainsString($needle, (string) $crawler->filter('[data-sort-link="date"]')->attr('href'));
        self::assertStringContainsString($needle, (string) $crawler->filter('[data-pagination] a')->first()->attr('href'));
        self::assertCount(1, $crawler->filter('[data-sort-select] input[name="volunteer"]'));
        self::assertCount(1, $crawler->filter('[data-pagination-bar] input[name="volunteer"]'));

        // ...and the filter still holds once you actually follow one.
        $crawler = $client->request('GET', '/activities?volunteer=' . $aisha->getId() . '&page=2');
        self::assertCount(1, $crawler->filter('table tbody tr'));
    }

    /**
     * Unlike the activity forms, which offer active volunteers only: this
     * reads history, so someone who has finished their stint stays findable.
     */
    #[Test]
    public function theVolunteerFilterOffersInactiveVolunteersToo(): void
    {
        $client = static::createClient();
        VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Achieng']);
        VolunteerFactory::new()->inactive()->create(['firstName' => 'Zawadi', 'lastName' => 'Zuma']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities');

        $options = $crawler->filter('[data-volunteer-filter] select[name="volunteer"] option')->each(
            static fn(Crawler $option) => trim($option->text()),
        );
        self::assertSame(['All volunteers', 'Aisha Achieng', 'Zawadi Zuma (inactive)'], $options);
        // Outside the `hidden md:block` wrapper, like the pagination bar.
        self::assertCount(0, $crawler->filter('.hidden.md\\:block [data-volunteer-filter]'));
    }

    /**
     * The return trip for the show page's "See in Activities" link. Only a
     * filtered list has one profile to point at — unfiltered, there is no
     * volunteer to view.
     */
    #[Test]
    public function theFilterOffersAProfileLinkOnlyWhileAVolunteerIsSelected(): void
    {
        $client = static::createClient();
        $aisha = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Achieng']);
        ActivityFactory::createOne(['volunteer' => $aisha]);
        $client->loginUser(UserFactory::createOne());

        self::assertCount(
            0,
            $client->request('GET', '/activities')->filter('[data-volunteer-filter] a'),
            'The unfiltered list has no volunteer to view.',
        );

        $crawler = $client->request('GET', '/activities?volunteer=' . $aisha->getId());
        $link = $crawler->filter('[data-volunteer-filter] a');
        self::assertCount(1, $link);
        self::assertSame("/volunteers/{$aisha->getId()}", $link->attr('href'));

        $client->click($link->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Aisha Achieng');
    }

    #[Test]
    public function aBatchTiesEachActivityToTheStayCoveringItsDate(): void
    {
        // Two stays at two branches; the activity's date decides which one.
        $client = static::createClient();
        $today = new \DateTimeImmutable('today');
        $volunteer = VolunteerFactory::new()->withoutStay()->create(['firstName' => 'Ann', 'lastName' => 'Wambui']);
        StayFactory::createOne([
            'volunteer' => $volunteer,
            'branch' => BranchFactory::find(['name' => 'Nairobi (HQ)']),
            'startDate' => $today->modify('-40 days'),
            'endDate' => $today->modify('-10 days'),
        ]);
        StayFactory::createOne([
            'volunteer' => $volunteer,
            'branch' => BranchFactory::find(['name' => 'Mombasa']),
            'startDate' => $today->modify('-5 days'),
            'endDate' => $today->modify('+30 days'),
        ]);
        $project = ProjectFactory::createOne();
        $activityType = ActivityTypeFactory::createOne();
        $program = ProgramFactory::createOne(['project' => $project, 'activityTypes' => [$activityType]]);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new-batch');
        $this->checkVolunteers($crawler, [$volunteer]);

        $client->submit($crawler->selectButton('Save')->form([
            'batch_activity_form[date]' => $today->modify('-20 days')->format('Y-m-d'),
            'batch_activity_form[program]' => (string) $program->getId(),
            'batch_activity_form[activityType]' => (string) $activityType->getId(),
            'batch_activity_form[duration]' => 'half_day',
        ]));

        self::assertResponseRedirects('/activities');
        $activities = $client->getContainer()->get('doctrine')->getRepository(Activity::class)->findAll();
        self::assertCount(1, $activities);
        self::assertSame('Nairobi (HQ)', $activities[0]->getStay()?->getBranch()?->getName());
    }

    #[Test]
    public function aBatchIsRefusedWholeWhenAVolunteerHasNoStayThatDay(): void
    {
        $client = static::createClient();
        $today = new \DateTimeImmutable('today');
        $here = VolunteerFactory::createOne(['firstName' => 'Ann', 'lastName' => 'Wambui']);
        // Arriving next week: offered by the picker, but not staying today.
        $arriving = VolunteerFactory::new()->withoutStay()->create(['firstName' => 'Daniel', 'lastName' => 'Otieno']);
        StayFactory::createOne(['volunteer' => $arriving, 'startDate' => $today->modify('+7 days'), 'endDate' => $today->modify('+30 days')]);
        $project = ProjectFactory::createOne();
        $activityType = ActivityTypeFactory::createOne();
        $program = ProgramFactory::createOne(['project' => $project, 'activityTypes' => [$activityType]]);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new-batch');
        $this->checkVolunteers($crawler, [$here, $arriving]);

        $client->submit($crawler->selectButton('Save')->form([
            'batch_activity_form[date]' => $today->format('Y-m-d'),
            'batch_activity_form[program]' => (string) $program->getId(),
            'batch_activity_form[activityType]' => (string) $activityType->getId(),
            'batch_activity_form[duration]' => 'half_day',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Daniel Otieno has no stay covering ' . $today->format('j M Y'));
        ActivityFactory::assert()->count(0);
    }

    #[Test]
    public function theSingleActivityFormRefusesADateOutsideTheVolunteersStays(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Ann', 'lastName' => 'Wambui']);
        $project = ProjectFactory::createOne();
        $activityType = ActivityTypeFactory::createOne();
        $program = ProgramFactory::createOne(['project' => $project, 'activityTypes' => [$activityType]]);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new');

        $client->submit($crawler->selectButton('Save')->form([
            'activity_form[date]' => (new \DateTimeImmutable('today'))->modify('+6 months')->format('Y-m-d'),
            'activity_form[volunteer]' => (string) $volunteer->getId(),
            'activity_form[program]' => (string) $program->getId(),
            'activity_form[activityType]' => (string) $activityType->getId(),
            'activity_form[duration]' => 'half_day',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Ann Wambui has no stay covering');
        ActivityFactory::assert()->count(0);
    }

    #[Test]
    public function theSingleActivityFormRefusesAProjectAtAnotherBranchThanTheStay(): void
    {
        $client = static::createClient();
        // Staying at Nairobi (HQ), the factory default.
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Ann', 'lastName' => 'Wambui']);
        $project = ProjectFactory::createOne(['name' => 'Minto', 'branch' => BranchFactory::find(['name' => 'Mombasa'])]);
        $activityType = ActivityTypeFactory::createOne();
        $program = ProgramFactory::createOne(['project' => $project, 'activityTypes' => [$activityType]]);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new');

        $client->submit($crawler->selectButton('Save')->form([
            'activity_form[date]' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'activity_form[volunteer]' => (string) $volunteer->getId(),
            'activity_form[program]' => (string) $program->getId(),
            'activity_form[activityType]' => (string) $activityType->getId(),
            'activity_form[duration]' => 'half_day',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Minto — ' . $program->getName() . ' is a Mombasa program, but on');
        self::assertSelectorTextContains('body', 'Ann Wambui is staying at Nairobi (HQ)');
        ActivityFactory::assert()->count(0);
    }

    #[Test]
    public function aBatchIsRefusedWholeWhenAVolunteerStaysAtAnotherBranch(): void
    {
        $client = static::createClient();
        $today = new \DateTimeImmutable('today');
        $mombasa = BranchFactory::find(['name' => 'Mombasa']);
        $coast = VolunteerFactory::new()->withoutStay()->create(['firstName' => 'Daniel', 'lastName' => 'Otieno']);
        StayFactory::createOne(['volunteer' => $coast, 'branch' => $mombasa]);
        // Staying at Nairobi (HQ), the factory default.
        $city = VolunteerFactory::createOne(['firstName' => 'Ann', 'lastName' => 'Wambui']);
        $project = ProjectFactory::createOne(['branch' => $mombasa]);
        $activityType = ActivityTypeFactory::createOne();
        $program = ProgramFactory::createOne(['project' => $project, 'activityTypes' => [$activityType]]);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new-batch');
        $this->checkVolunteers($crawler, [$coast, $city]);

        $client->submit($crawler->selectButton('Save')->form([
            'batch_activity_form[date]' => $today->format('Y-m-d'),
            'batch_activity_form[program]' => (string) $program->getId(),
            'batch_activity_form[activityType]' => (string) $activityType->getId(),
            'batch_activity_form[duration]' => 'half_day',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Ann Wambui is staying at Nairobi (HQ)');
        ActivityFactory::assert()->count(0);
    }

    #[Test]
    public function theBatchFormGroupsProgramsByBranch(): void
    {
        $client = static::createClient();
        ProgramFactory::createOne([
            'name' => 'Orphanage support',
            'project' => ProjectFactory::createOne(['name' => 'Minto', 'branch' => BranchFactory::find(['name' => 'Mombasa'])]),
        ]);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/activities/new-batch');

        self::assertSelectorTextContains('select[name="batch_activity_form[program]"] optgroup[label="Mombasa"]', 'Minto — Orphanage support');
    }

    /** The type must be one the program offers (ADR 0030). */
    #[Test]
    public function theSingleActivityFormRefusesATypeTheProgramDoesNotOffer(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne();
        $program = ProgramFactory::createOne(['name' => 'School support']);
        // Offered elsewhere, so the picker lists it.
        $clinic = ActivityTypeFactory::createOne(['name' => 'Clinic support']);
        ProgramFactory::createOne(['activityTypes' => [$clinic]]);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new');

        $client->submit($crawler->selectButton('Save')->form([
            'activity_form[date]' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'activity_form[volunteer]' => (string) $volunteer->getId(),
            'activity_form[program]' => (string) $program->getId(),
            'activity_form[activityType]' => (string) $clinic->getId(),
            'activity_form[duration]' => 'half_day',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', "School support doesn't offer Clinic support.");
        ActivityFactory::assert()->count(0);
    }

    #[Test]
    public function aBatchIsRefusedWholeOnADateTheProgramDoesNotCover(): void
    {
        $client = static::createClient();
        $today = new \DateTimeImmutable('today');
        $volunteerA = VolunteerFactory::createOne();
        $volunteerB = VolunteerFactory::createOne();
        $activityType = ActivityTypeFactory::createOne();
        $program = ProgramFactory::createOne([
            'name' => 'Medical camp',
            'activityTypes' => [$activityType],
            'startDate' => $today->modify('+1 day'),
            'endDate' => $today->modify('+3 days'),
        ]);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new-batch');
        $this->checkVolunteers($crawler, [$volunteerA, $volunteerB]);

        $client->submit($crawler->selectButton('Save')->form([
            'batch_activity_form[date]' => $today->format('Y-m-d'),
            'batch_activity_form[program]' => (string) $program->getId(),
            'batch_activity_form[activityType]' => (string) $activityType->getId(),
            'batch_activity_form[duration]' => 'half_day',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', "Medical camp doesn't run on " . $today->format('j M Y') . '.');
        ActivityFactory::assert()->count(0);
    }

    #[Test]
    public function theTypePickerListsOnlyTypesSomeProgramOffers(): void
    {
        $client = static::createClient();
        ProgramFactory::createOne(['activityTypes' => [ActivityTypeFactory::createOne(['name' => 'School support'])]]);
        ActivityTypeFactory::createOne(['name' => 'Unused type']);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/activities/new-batch');

        self::assertSelectorTextContains('#batch_activity_form_activityType', 'School support');
        self::assertSelectorTextNotContains('#batch_activity_form_activityType', 'Unused type');
    }

    /**
     * The filtering itself is Stimulus; what a functional test can hold is
     * the wiring: the program select carries the controller, and each type
     * names the programs offering it.
     */
    #[Test]
    public function bothActivityFormsWireTheTypeFilterToThePrograms(): void
    {
        $client = static::createClient();
        $shared = ActivityTypeFactory::createOne(['name' => 'School support']);
        $a = ProgramFactory::createOne(['activityTypes' => [$shared]]);
        $b = ProgramFactory::createOne(['activityTypes' => [$shared]]);
        $client->loginUser(UserFactory::createOne());

        foreach (['/activities/new' => 'activity_form', '/activities/new-batch' => 'batch_activity_form'] as $url => $name) {
            $crawler = $client->request('GET', $url);

            self::assertCount(1, $crawler->filter("select#{$name}_program[data-controller=\"program-types\"][data-action=\"program-types#filter\"]"));
            self::assertSame(
                $a->getId() . ' ' . $b->getId(),
                $crawler->filter("#{$name}_activityType option[value=\"{$shared->getId()}\"]")->attr('data-programs'),
            );
        }
    }
}
