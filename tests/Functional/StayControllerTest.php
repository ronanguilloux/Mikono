<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ActivityFactory;
use App\Factory\BranchFactory;
use App\Factory\StayFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class StayControllerTest extends WebTestCase
{
    #[Test]
    public function aStayAddedFromTheVolunteerPageShowsThereWithItsBranch(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::new()->withoutStay()->create(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        $mombasa = BranchFactory::find(['name' => 'Mombasa']);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/volunteers/{$volunteer->getId()}/stays/new");
        $client->submit($crawler->selectButton('Save')->form([
            'stay_form[branch]' => (string) $mombasa->getId(),
            'stay_form[startDate]' => '2026-09-01',
            'stay_form[endDate]' => '2026-09-30',
        ]));

        self::assertResponseRedirects("/volunteers/{$volunteer->getId()}");
        $client->followRedirect();
        self::assertSelectorTextContains('[data-stays]', 'Mombasa');
        self::assertSelectorTextContains('[data-stays]', '1 Sep 2026 – 30 Sep 2026');
        StayFactory::assert()->count(1);
    }

    #[Test]
    public function aStayCannotEndBeforeItStarts(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::new()->withoutStay()->create();
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/volunteers/{$volunteer->getId()}/stays/new");
        $client->submit($crawler->selectButton('Save')->form([
            'stay_form[branch]' => (string) BranchFactory::find(['name' => 'Mombasa'])->getId(),
            'stay_form[startDate]' => '2026-09-30',
            'stay_form[endDate]' => '2026-09-01',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'A stay cannot end before it starts.');
        StayFactory::assert()->count(0);
    }

    #[Test]
    public function aStayCannotOverlapAnotherStayOfTheSameVolunteer(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::new()->withoutStay()->create();
        StayFactory::createOne([
            'volunteer' => $volunteer,
            'branch' => BranchFactory::find(['name' => 'Nairobi (HQ)']),
            'startDate' => new \DateTimeImmutable('2026-09-01'),
            'endDate' => new \DateTimeImmutable('2026-09-30'),
        ]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/volunteers/{$volunteer->getId()}/stays/new");
        $client->submit($crawler->selectButton('Save')->form([
            'stay_form[branch]' => (string) BranchFactory::find(['name' => 'Mombasa'])->getId(),
            'stay_form[startDate]' => '2026-09-30',
            'stay_form[endDate]' => '2026-10-15',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'This overlaps the stay at Nairobi (HQ), 1 Sep 2026 – 30 Sep 2026.');
        StayFactory::assert()->count(1);
    }

    #[Test]
    public function aStayCannotBeShrunkAwayFromItsActivities(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::new()->withoutStay()->create();
        $stay = StayFactory::createOne([
            'volunteer' => $volunteer,
            'startDate' => new \DateTimeImmutable('2026-09-01'),
            'endDate' => new \DateTimeImmutable('2026-09-30'),
        ]);
        ActivityFactory::createOne(['volunteer' => $volunteer, 'date' => new \DateTimeImmutable('2026-09-25')]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/stays/{$stay->getId()}/edit");
        $client->submit($crawler->selectButton('Save')->form(['stay_form[endDate]' => '2026-09-20']));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', '1 activity logged in this stay would fall outside these dates.');
    }

    #[Test]
    public function editingAStayMovesItToAnotherBranch(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::new()->withoutStay()->create();
        $stay = StayFactory::createOne(['volunteer' => $volunteer, 'branch' => BranchFactory::find(['name' => 'Nairobi (HQ)'])]);
        $samburu = BranchFactory::find(['name' => 'Samburu']);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/stays/{$stay->getId()}/edit");
        $client->submit($crawler->selectButton('Save')->form(['stay_form[branch]' => (string) $samburu->getId()]));

        self::assertResponseRedirects("/volunteers/{$volunteer->getId()}");
        $client->followRedirect();
        self::assertSelectorTextContains('[data-stays]', 'Samburu');
    }

    #[Test]
    public function deleteRemovesAStayWithNoActivity(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::new()->withoutStay()->create();
        StayFactory::createOne(['volunteer' => $volunteer]);
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', "/volunteers/{$volunteer->getId()}");
        $client->submitForm('Delete');

        self::assertResponseRedirects("/volunteers/{$volunteer->getId()}");
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Stay was deleted.');
        StayFactory::assert()->count(0);
    }

    #[Test]
    public function theVolunteerPageShowsDeleteAsUnavailableForAStayWithActivity(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne();
        ActivityFactory::createOne(['volunteer' => $volunteer, 'date' => new \DateTimeImmutable('today')]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/volunteers/{$volunteer->getId()}");

        self::assertCount(0, $crawler->filter('[data-stays] form'));
        self::assertSelectorTextContains('[data-stays] [aria-disabled="true"]', 'Cannot delete this stay — 1 activity is logged in it.');
    }

    /** A page rendered before the activity existed still reaches the server-side guard. */
    #[Test]
    public function deleteIsRefusedWhileActivitiesAreLoggedInTheStay(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne();
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', "/volunteers/{$volunteer->getId()}");

        ActivityFactory::createOne(['volunteer' => $volunteer, 'date' => new \DateTimeImmutable('today')]);
        $client->submitForm('Delete');

        self::assertResponseRedirects("/volunteers/{$volunteer->getId()}");
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cannot delete this stay');
        StayFactory::assert()->count(1);
    }

    #[Test]
    public function aStayCannotBeMovedAwayFromItsActivitiesProjects(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::new()->withoutStay()->create();
        $stay = StayFactory::createOne(['volunteer' => $volunteer, 'branch' => BranchFactory::find(['name' => 'Nairobi (HQ)'])]);
        // ProjectFactory defaults to Nairobi (HQ) too.
        ActivityFactory::createOne(['volunteer' => $volunteer, 'date' => new \DateTimeImmutable('today')]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/stays/{$stay->getId()}/edit");
        $client->submit($crawler->selectButton('Save')->form([
            'stay_form[branch]' => (string) BranchFactory::find(['name' => 'Samburu'])->getId(),
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', "1 activity logged in this stay is at another branch's projects.");
    }
}
