<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ActivityTypeControllerTest extends WebTestCase
{
    #[Test]
    public function newWithValidDataPersists(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activity-types/new');

        $form = $crawler->selectButton('Save')->form([
            'activity_type_form[name]' => 'Computer lessons',
            'activity_type_form[description]' => 'Basic computer literacy for students',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/activity-types');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Computer lessons');
    }

    #[Test]
    public function deleteIsBlockedWhenAnActivityReferencesTheType(): void
    {
        $client = static::createClient();
        $activityType = ActivityTypeFactory::createOne(['name' => 'Computer lessons']);
        ActivityFactory::createOne(['activityType' => $activityType]);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/activity-types');
        $client->submitForm('Delete');

        self::assertResponseRedirects('/activity-types');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cannot delete Computer lessons');
    }

    #[Test]
    public function theIndexPaginatesAtTwentyFivePerPage(): void
    {
        $client = static::createClient();
        ActivityTypeFactory::createMany(26);

        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/activity-types');
        self::assertCount(25, $crawler->filter('table tbody tr'));

        $crawler = $client->request('GET', '/activity-types?page=2');
        self::assertCount(1, $crawler->filter('table tbody tr'));
    }
}
