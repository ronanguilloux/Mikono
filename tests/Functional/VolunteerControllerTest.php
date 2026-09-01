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

    #[Test]
    public function deleteIsBlockedWhenAnActivityReferencesTheVolunteer(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        ActivityFactory::createOne(['volunteer' => $volunteer]);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/volunteers');
        $client->submitForm('Delete');

        self::assertResponseRedirects('/volunteers');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cannot delete Aisha Njoroge');
        self::assertSelectorTextContains('body', 'Aisha Njoroge');
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
}
