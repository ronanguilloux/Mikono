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

    #[Test]
    public function theIndexSortsByName(): void
    {
        $client = static::createClient();
        ActivityTypeFactory::createOne(['name' => 'Art class']);
        ActivityTypeFactory::createOne(['name' => 'Zumba']);

        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/activity-types?sort=name&direction=desc');
        self::assertStringContainsString('Zumba', $crawler->filter('table tbody tr')->first()->text());

        $crawler = $client->request('GET', '/activity-types?sort=name&direction=asc');
        self::assertStringContainsString('Art class', $crawler->filter('table tbody tr')->first()->text());
    }

    /**
     * Description is free text — ordering it surfaces nothing anyone is
     * looking for, so it is left out of the controller's sort map. Being out
     * of the map is the whole opt-out; nothing special-cases it in DataTable.
     */
    #[Test]
    public function theDescriptionHeaderIsNotSortable(): void
    {
        $client = static::createClient();
        ActivityTypeFactory::createOne(['name' => 'Art class', 'description' => 'Painting and drawing']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activity-types');

        self::assertCount(1, $crawler->filter('[data-sort-link="name"]'));
        self::assertCount(0, $crawler->filter('[data-sort-link="description"]'));
        // Still a header, just a plain one.
        self::assertSame('Description', $crawler->filter('thead th')->eq(1)->text());
    }

    #[Test]
    public function theIndexShrugsOffAnUnknownSortColumn(): void
    {
        $client = static::createClient();
        ActivityTypeFactory::createOne(['name' => 'Art class']);
        ActivityTypeFactory::createOne(['name' => 'Zumba']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activity-types?sort=description&direction=desc');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Art class', $crawler->filter('table tbody tr')->first()->text());
    }
}
