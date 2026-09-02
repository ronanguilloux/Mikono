<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Activity;
use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\EscortFactory;
use App\Factory\ProjectFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use App\Enum\ProjectLocation;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ActivityControllerTest extends WebTestCase
{
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
            'location' => ProjectLocation::Kibera,
            'partnerOrganizationName' => 'Bright Achievers High School',
        ]);
        $activityType = ActivityTypeFactory::createOne(['name' => 'Computer lessons']);
        $escort = EscortFactory::createOne(['name' => 'Mr Maeba']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new');

        $form = $crawler->selectButton('Save')->form([
            'activity_form[date]' => '2026-08-11',
            'activity_form[volunteer]' => (string) $volunteer->getId(),
            'activity_form[project]' => (string) $project->getId(),
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
        $creator = UserFactory::createOne(['fullName' => 'Original Logger']);
        $editor = UserFactory::createOne(['fullName' => 'Different Editor']);
        $client->loginUser($creator);
        $crawler = $client->request('GET', '/activities/new');
        $form = $crawler->selectButton('Save')->form([
            'activity_form[date]' => '2026-01-15',
            'activity_form[volunteer]' => (string) $volunteer->getId(),
            'activity_form[project]' => (string) $project->getId(),
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
        $escort = EscortFactory::createOne(['name' => 'Mr Maeba']);
        // Two escorts on one group is a real roster line — see ADR 0013.
        $secondEscort = EscortFactory::createOne(['name' => 'Ms Njeri']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new');
        $form = $crawler->selectButton('Save')->form([
            'activity_form[date]' => '2026-08-11',
            'activity_form[volunteer]' => (string) $volunteer->getId(),
            'activity_form[project]' => (string) $project->getId(),
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
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new');

        $form = $crawler->selectButton('Save')->form([
            'activity_form[date]' => '2026-08-11',
            'activity_form[volunteer]' => (string) $volunteer->getId(),
            'activity_form[project]' => (string) $project->getId(),
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
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new');

        $form = $crawler->selectButton('Save')->form([
            'activity_form[date]' => '2026-08-11',
            'activity_form[volunteer]' => (string) $volunteer->getId(),
            'activity_form[project]' => (string) $project->getId(),
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
        $escort = EscortFactory::createOne(['name' => 'Mr Maeba']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new-batch');
        $this->checkVolunteers($crawler, [$volunteerA, $volunteerB]);

        $form = $crawler->selectButton('Save')->form([
            'batch_activity_form[date]' => '2026-08-28',
            'batch_activity_form[project]' => (string) $project->getId(),
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
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new-batch');
        $this->checkVolunteers($crawler, [$volunteer]);

        $form = $crawler->selectButton('Save and add another')->form([
            'batch_activity_form[date]' => '2026-08-28',
            'batch_activity_form[project]' => (string) $project->getId(),
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
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new-batch');

        $form = $crawler->selectButton('Save')->form([
            'batch_activity_form[date]' => '2026-08-28',
            'batch_activity_form[project]' => (string) $project->getId(),
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
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new-batch');
        $this->checkVolunteers($crawler, [$volunteer]);

        $form = $crawler->selectButton('Save')->form([
            'batch_activity_form[date]' => '2026-08-28',
            'batch_activity_form[project]' => (string) $project->getId(),
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
        $active = VolunteerFactory::createOne(['firstName' => 'Still', 'lastName' => 'Here', 'isActive' => true]);
        $gone = VolunteerFactory::createOne(['firstName' => 'Long', 'lastName' => 'Gone', 'isActive' => false]);

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
        $active = VolunteerFactory::createOne(['firstName' => 'Still', 'lastName' => 'Here', 'isActive' => true]);
        $gone = VolunteerFactory::createOne(['firstName' => 'Long', 'lastName' => 'Gone', 'isActive' => false]);

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
        $gone = VolunteerFactory::createOne(['firstName' => 'Long', 'lastName' => 'Gone', 'isActive' => false]);
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
        $project = ProjectFactory::createOne(['name' => 'Toi School Field']);
        $activityType = ActivityTypeFactory::createOne(['name' => 'Sports']);
        ActivityFactory::createMany(2, [
            'volunteer' => $volunteer,
            'project' => $project,
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
        self::assertStringContainsString('Toi School Field · Sports', $cards->first()->text());
        // Delete stays a real CSRF-protected POST inside the card too.
        self::assertCount(2, $crawler->filter('[data-activity-cards] form[method="post"] input[name="_token"]'));
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
        self::assertSame('Duration', $crawler->filter('thead th')->eq(4)->text());
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

        // The four sortable columns plus the "Newest first" default, and never
        // Duration — the desktop headers and this control offer the same set.
        $options = $crawler->filter('[data-sort-select] select[name="sort"] option')->each(
            static fn(Crawler $option) => $option->attr('value'),
        );
        self::assertSame(['', 'date', 'volunteer', 'project', 'activityType'], $options);

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
}
