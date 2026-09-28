<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\BeneficiaryGroupFactory;
use App\Factory\ProgramFactory;
use App\Factory\UserFactory;
use App\Repository\BeneficiaryGroupRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class BeneficiaryGroupControllerTest extends WebTestCase
{
    use ReadsListExports;

    /**
     * Unlike Branch and Skill, nothing is seeded: the VM enters the list
     * (ADR 0030).
     */
    #[Test]
    public function theListStartsEmpty(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());

        self::assertSame([], self::exportedRows($client, '/beneficiary-groups/export.csv'));
    }

    #[Test]
    public function newWithValidDataPersists(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/beneficiary-groups/new');

        $client->submit($crawler->selectButton('Save')->form([
            'beneficiary_group_form[name]' => 'Adolescent girls (10–19)',
            'beneficiary_group_form[description]' => 'Girls in the mentoring programs',
        ]));

        self::assertResponseRedirects('/beneficiary-groups');
        self::assertSame('Girls in the mentoring programs', static::getContainer()->get(BeneficiaryGroupRepository::class)->findOneBy(['name' => 'Adolescent girls (10–19)'])?->getDescription());
    }

    #[Test]
    public function aDuplicateNameIsRefused(): void
    {
        $client = static::createClient();
        BeneficiaryGroupFactory::createOne(['name' => 'Families']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/beneficiary-groups/new');

        $client->submit($crawler->selectButton('Save')->form(['beneficiary_group_form[name]' => 'Families']));

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function editRenames(): void
    {
        $client = static::createClient();
        $group = BeneficiaryGroupFactory::createOne(['name' => 'Pupils']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', sprintf('/beneficiary-groups/%d/edit', $group->getId()));

        $client->submit($crawler->selectButton('Save')->form(['beneficiary_group_form[name]' => 'Primary school pupils']));

        self::assertResponseRedirects('/beneficiary-groups');
        self::assertNotNull(static::getContainer()->get(BeneficiaryGroupRepository::class)->findOneBy(['name' => 'Primary school pupils']));
    }

    #[Test]
    public function anUnusedGroupIsDeleted(): void
    {
        $client = static::createClient();
        BeneficiaryGroupFactory::createOne(['name' => 'Families']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/beneficiary-groups');
        $client->submit($crawler->filter('tr:contains("Families")')->selectButton('Delete')->form());

        self::assertResponseRedirects('/beneficiary-groups');
        self::assertNull(static::getContainer()->get(BeneficiaryGroupRepository::class)->findOneBy(['name' => 'Families']));
    }

    #[Test]
    public function deleteIsBlockedWhileAProgramServesTheGroup(): void
    {
        $client = static::createClient();
        ProgramFactory::createOne(['beneficiaryGroups' => [BeneficiaryGroupFactory::createOne(['name' => 'Families'])]]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/beneficiary-groups');
        $client->submit($crawler->filter('tr:contains("Families")')->selectButton('Delete')->form());

        self::assertResponseRedirects('/beneficiary-groups');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cannot delete Families — 1 program serves it.');
    }
}
