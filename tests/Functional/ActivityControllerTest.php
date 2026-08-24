<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ActivityTypeFactory;
use App\Factory\ProjectFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use App\Enum\ProjectLocation;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ActivityControllerTest extends WebTestCase
{
    #[Test]
    public function the_bright_achievers_worked_example_can_be_logged_end_to_end(): void
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
    public function loggedBy_is_set_from_the_authenticated_user_and_untouched_on_edit(): void
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
}
