<?php

declare(strict_types=1);

namespace App\Tests\Functional;

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
    public function new_partner_project_without_an_organization_name_is_unprocessable(): void
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

    #[Test]
    public function new_with_valid_partner_data_persists(): void
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

    #[Test]
    public function delete_is_blocked_when_an_activity_references_the_project(): void
    {
        $client = static::createClient();
        $project = ProjectFactory::createOne(['name' => 'Bright Achievers']);
        ActivityFactory::createOne(['project' => $project]);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/projects');
        $client->submitForm('Delete');

        self::assertResponseRedirects('/projects');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cannot delete Bright Achievers');
    }
}
