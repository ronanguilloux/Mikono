<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ActivityTypeFactory;
use App\Factory\EscortFactory;
use App\Factory\ProjectFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use App\Enum\ProjectLocation;
use PHPUnit\Framework\Attributes\Test;
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
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activities/new');

        $form = $crawler->selectButton('Save')->form([
            'activity_form[date]' => '2026-08-11',
            'activity_form[volunteer]' => (string) $volunteer->getId(),
            'activity_form[project]' => (string) $project->getId(),
            'activity_form[activityType]' => (string) $activityType->getId(),
            'activity_form[duration]' => 'full_day',
            'activity_form[notes]' => 'Delivered Computer lessons to students',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/activities');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Ronan Guilloux');
        self::assertSelectorTextContains('body', 'Bright Achievers');
        self::assertSelectorTextContains('body', 'Computer lessons');
        self::assertSelectorTextContains('body', 'Full day');

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

        $activityRepository = $client->getContainer()->get('doctrine')->getRepository(\App\Entity\Activity::class);
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
            'batch_activity_form[escort]' => (string) $escort->getId(),
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/activities');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Logged 2 activities');

        $activityRepository = $client->getContainer()->get('doctrine')->getRepository(\App\Entity\Activity::class);
        $activities = $activityRepository->findAll();
        self::assertCount(2, $activities);
        foreach ($activities as $activity) {
            $activityProject = $activity->getProject();
            $activityEscort = $activity->getAccompaniedBy();
            self::assertNotNull($activityProject);
            self::assertNotNull($activityEscort);
            self::assertSame('Beyond Zero clinic', $activityProject->getName());
            self::assertSame('Mr Maeba', $activityEscort->getName());
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
}
