<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Api\ApiDataMap;
use App\Entity\ApiAccess;
use App\Factory\AchievementFactory;
use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\BeneficiaryGroupFactory;
use App\Factory\BranchFactory;
use App\Factory\EscortFactory;
use App\Factory\IndicatorFactory;
use App\Factory\ProgramFactory;
use App\Factory\ProjectFactory;
use App\Factory\SkillFactory;
use App\Factory\SourceFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use App\Security\PassportNumberCipher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ApiResourceTest extends WebTestCase
{
    use UsesOAuthToken;

    private const string PASSPORT_NUMBER = 'AB1234567';

    private const array RESOURCE_BOXES = [
        'volunteers' => 'Volunteer',
        'stays' => 'Stay',
        'activities' => 'Activity',
        'achievements' => 'Achievement',
        'users' => 'User',
        'escorts' => 'Escort',
        'branches' => 'Branch',
        'projects' => 'Project',
        'programs' => 'Program',
        'activity_types' => 'Activity type',
        'skills' => 'Skill',
        'sources' => 'Source',
        'beneficiary_groups' => 'Beneficiary group',
        'indicators' => 'Indicator',
    ];

    #[Test]
    public function everyResourceAndReportAnswersAndNothingSecretLeaves(): void
    {
        [$client, $token] = self::connected();
        $seeded = self::seedOneOfEach();

        $seen = '';
        foreach (self::apiGetPaths($client, $seeded) as $path) {
            $client->request('GET', $path, server: self::bearer($token));
            self::assertResponseIsSuccessful($path);
            $seen .= $client->getResponse()->getContent();
        }

        // Its own format: the published schema mustn't name a secret field either.
        $client->request('GET', '/api/docs.jsonopenapi', server: ['HTTP_ACCEPT' => 'application/vnd.openapi+json'] + self::bearer($token));
        self::assertResponseIsSuccessful();
        $seen .= $client->getResponse()->getContent();

        self::assertStringContainsString('"emergencyContacts"', $seen, 'The walk must reach a full volunteer.');
        self::assertStringNotContainsString(self::PASSPORT_NUMBER, $seen);
        // Field names, not values: a key appearing at all is the failure.
        foreach (['"passportNumber"', 'iphertext', '"password"', '"bytes"', 'oauth2'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $seen);
        }
    }

    #[Test]
    public function theConnectAiMapListsEveryFieldTheApiReturns(): void
    {
        [$client, $token] = self::connected();
        $ids = self::seedOneOfEach();
        $map = array_column(self::getContainer()->get(ApiDataMap::class)->boxes(), 'fields', 'name');

        foreach (self::RESOURCE_BOXES as $resource => $name) {
            $client->request('GET', \sprintf('/api/%s/%d', $resource, $ids[$resource]), server: self::bearer($token));
            self::assertResponseIsSuccessful($resource);
            /** @var array<string, mixed> $record */
            $record = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

            $returned = array_filter(array_keys($record), static fn(string $key): bool => !str_starts_with($key, '@'));
            self::assertSame([], array_values(array_diff($returned, array_column($map[$name], 'key'))), $resource . ' returns a field /connect-ai does not show.');
        }
    }

    #[Test]
    public function writesAreRefused(): void
    {
        [$client, $token] = self::connected();
        $volunteer = VolunteerFactory::createOne();
        $path = '/api/volunteers/' . $volunteer->getId();

        foreach ([['POST', '/api/volunteers'], ['PUT', $path], ['PATCH', $path], ['DELETE', $path]] as [$method, $url]) {
            $client->request($method, $url, server: self::bearer($token) + ['CONTENT_TYPE' => 'application/ld+json'], content: '{}');
            self::assertResponseStatusCodeSame(405, $method);
        }
    }

    #[Test]
    public function aRelationShowsItsLabelNotItsWholeRecord(): void
    {
        [$client, $token] = self::connected();
        $activity = ActivityFactory::createOne(['volunteer' => VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Otieno', 'emergencyContacts' => 'Mum'])]);

        $client->request('GET', '/api/activities/' . $activity->getId(), server: self::bearer($token));

        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('"fullName":"Aisha Otieno"', $body);
        self::assertStringNotContainsString('emergencyContacts', $body);
        self::assertStringContainsString('"project"', $body, 'The derived project must be exposed.');
    }

    #[Test]
    public function volunteersFilterByTheScreensRules(): void
    {
        [$client, $token] = self::connected();
        VolunteerFactory::createOne(['firstName' => 'Present']);
        VolunteerFactory::new()->inactive()->create(['firstName' => 'Finished']);

        $client->request('GET', '/api/volunteers?status=present', server: self::bearer($token));
        self::assertSame(1, self::totalItems($client));
        self::assertStringContainsString('"status":"present"', (string) $client->getResponse()->getContent());

        $client->request('GET', '/api/volunteers?search=finish', server: self::bearer($token));
        self::assertSame(1, self::totalItems($client));

        $client->request('GET', '/api/volunteers?branch=999999', server: self::bearer($token));
        self::assertSame(0, self::totalItems($client));

        // A misspelt status must not read as "every volunteer".
        $client->request('GET', '/api/volunteers?status=presnt', server: self::bearer($token));
        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function activitiesFilterByVolunteerProjectAndDate(): void
    {
        [$client, $token] = self::connected();
        $mine = ActivityFactory::createOne(['date' => new \DateTimeImmutable('2026-03-10')]);
        ActivityFactory::createOne(['date' => new \DateTimeImmutable('2026-06-10')]);
        $count = static function (string $query) use ($client, $token): int {
            $client->request('GET', '/api/activities?' . $query, server: self::bearer($token));
            self::assertResponseIsSuccessful($query);

            return self::totalItems($client);
        };

        self::assertSame(2, $count(''));
        self::assertSame(1, $count('volunteer=' . $mine->getVolunteer()?->getId()));
        self::assertSame(1, $count('project=' . $mine->getProject()?->getId()));
        self::assertSame(1, $count('date[before]=2026-04-01'));
        self::assertSame(1, $count('date[after]=2026-04-01'));
    }

    #[Test]
    public function reportsMatchTheServicesBehindTheScreens(): void
    {
        [$client, $token] = self::connected();
        ActivityFactory::createMany(2);

        $client->request('GET', '/api/reports/metrics', server: self::bearer($token));

        self::assertSame(2, ((array) json_decode((string) $client->getResponse()->getContent(), true))['activityCount']);
    }

    #[Test]
    public function malformedInputIsRefusedNotIgnored(): void
    {
        [$client, $token] = self::connected();

        foreach ([
            '/api/reports/nope' => 404,
            '/api/reports/activity-summary?by=nope' => 400,
            '/api/reports/activity-summary?by[]=volunteer' => 400,
            '/api/reports/activity-summary?by=source&year=abc' => 400,
            '/api/reports/metrics?nope=1' => 400,
            '/api/reports/roster?date=nope' => 400,
            '/api/reports/roster?date=2026-02-31' => 400,
            '/api/reports/roster?date=2026-13-45' => 400,
            '/api/reports/roster?date[]=2026-01-01' => 400,
            '/api/reports/volunteer-impact' => 400,
            '/api/reports/volunteer-impact?volunteer=999999' => 400,
            '/api/reports/periods?step=nope' => 400,
            '/api/reports/periods?branch=abc' => 400,
            '/api/reports/periods?branch=999999' => 400,
            '/api/reports/program-matches?who=nope' => 400,
            '/api/reports/program-matches?program=999999' => 400,
            '/api/volunteers?branch=abc' => 400,
            '/api/volunteers?skill=abc' => 400,
            '/api/volunteers?source=abc' => 400,
            '/api/volunteers?search[]=x' => 400,
            '/api/volunteers?statuss=present' => 400,
            '/api/activities?nope=1' => 400,
            '/api/activities?date[after]=nonsense' => 422,
            '/api/activities?date=nonsense' => 422,
            '/api/stays?startDate[after]=nonsense' => 422,
            '/api/stays?endDate[before]=2026-02-31' => 422,
            '/api/achievements?achievedOn[after]=nonsense' => 422,
        ] as $path => $status) {
            $client->request('GET', $path, server: self::bearer($token));
            self::assertResponseStatusCodeSame($status, $path);
        }
    }

    #[Test]
    public function aReadIsRecordedWithoutItsQuery(): void
    {
        [$client, $token] = self::connected();

        $client->request('GET', '/api/volunteers?search=Aisha', server: self::bearer($token));
        $client->request('GET', '/api/docs.jsonopenapi', server: self::bearer($token));

        $rows = self::getContainer()->get(EntityManagerInterface::class)->getRepository(ApiAccess::class)->findAll();
        self::assertCount(1, $rows, 'The OpenAPI document is not a read of UCESCO data.');
        self::assertSame('/api/volunteers', $rows[0]->getPath());
        self::assertFalse($rows[0]->isViaMcp());
    }

    #[Test]
    public function oldReadsArePruned(): void
    {
        [$client, $token] = self::connected();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new ApiAccess('old@example.invalid', '/api/skills', false, new \DateTimeImmutable('-91 days')));
        $entityManager->flush();

        $client->request('GET', '/api/skills', server: self::bearer($token));

        $identifiers = array_map(static fn(ApiAccess $row): string => $row->getIdentifier(), $entityManager->getRepository(ApiAccess::class)->findAll());
        self::assertNotContains('old@example.invalid', $identifiers);
    }

    private static function totalItems(KernelBrowser $client): int
    {
        /** @var array{totalItems: int} $list */
        $list = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $list['totalItems'];
    }

    /** @return array{KernelBrowser, string} */
    private static function connected(): array
    {
        $client = static::createClient();
        // Keeps the in-memory kernel, and so the entity manager a test seeds with.
        $client->disableReboot();
        self::createOAuthClient($client);

        return [$client, self::obtainTokens($client, UserFactory::new()->admin()->create())['access_token']];
    }

    /** @return array<string, int> */
    private static function seedOneOfEach(): array
    {
        $project = ProjectFactory::createOne();
        $activityType = ActivityTypeFactory::createOne();
        $activity = ActivityFactory::createOne([
            'volunteer' => $volunteer = VolunteerFactory::new()->withPhoto()->create(['emergencyContacts' => 'Mum']),
            'project' => $project,
            'activityType' => $activityType,
            'escorts' => [$escort = EscortFactory::createOne()],
        ]);
        $volunteer->setPassportNumberCiphertext(self::getContainer()->get(PassportNumberCipher::class)->encrypt(self::PASSPORT_NUMBER));
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        return [
            'achievements' => (int) AchievementFactory::createOne(['stay' => $activity->getStay(), 'project' => $project])->getId(),
            'activities' => (int) $activity->getId(),
            'activity_types' => (int) $activityType->getId(),
            'beneficiary_groups' => (int) BeneficiaryGroupFactory::createOne()->getId(),
            'branches' => (int) BranchFactory::createOne()->getId(),
            'escorts' => (int) $escort->getId(),
            'indicators' => (int) IndicatorFactory::createOne()->getId(),
            'programs' => (int) ProgramFactory::createOne(['project' => $project, 'activityTypes' => [$activityType]])->getId(),
            'projects' => (int) $project->getId(),
            'skills' => (int) SkillFactory::createOne()->getId(),
            'sources' => (int) SourceFactory::createOne()->getId(),
            'stays' => (int) $activity->getStay()?->getId(),
            'users' => (int) UserFactory::createOne()->getId(),
            'volunteers' => (int) $volunteer->getId(),
        ];
    }

    /**
     * Every GET route the API has, read from the router, so a resource added
     * tomorrow is walked, and leak-checked, for free.
     *
     * @param array<string, int> $ids
     *
     * @return list<string>
     */
    private static function apiGetPaths(KernelBrowser $client, array $ids): array
    {
        $router = $client->getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        $paths = [];

        foreach ($router->getRouteCollection() as $name => $route) {
            $path = str_replace(['{._format}', '.{_format}'], '', $route->getPath());
            if (!str_starts_with($name, '_api_/') || !\in_array('GET', $route->getMethods(), true)) {
                continue;
            }

            $paths[] = (string) preg_replace_callback(
                '#^/api/([a-z_]+)/\{id\}#',
                static fn(array $match): string => \sprintf('/api/%s/%d', $match[1], $ids[$match[1]]),
                $path,
            );
        }

        self::assertCount(28, $paths, 'Fourteen resources, each a collection and an item.');

        return [
            ...$paths,
            '/api',
            '/api/volunteers/' . $ids['volunteers'] . '/photo',
            '/api/reports/metrics',
            '/api/reports/activity-summary?by=volunteer',
            '/api/reports/activity-summary?by=project',
            '/api/reports/activity-summary?by=program',
            '/api/reports/activity-summary?by=activity_type',
            '/api/reports/activity-summary?by=escort',
            '/api/reports/activity-summary?by=branch',
            '/api/reports/activity-summary?by=beneficiary_group',
            '/api/reports/activity-summary?by=source',
            '/api/reports/periods',
            '/api/reports/periods?step=year&branch=' . $ids['branches'],
            '/api/reports/roster',
            '/api/reports/quiet-projects',
            '/api/reports/program-matches',
            '/api/reports/volunteer-impact?volunteer=' . $ids['volunteers'],
            '/api/reports/stay-impact?stay=' . $ids['stays'],
            '/api/reports/impact-by-month',
            '/api/reports/occupancy',
            '/api/reports/birthdays',
            '/api/reports/arrivals',
            '/api/reports/departures',
            '/api/reports/anniversaries',
        ];
    }
}
