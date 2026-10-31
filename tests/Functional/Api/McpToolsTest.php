<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\ApiAccess;
use App\Factory\ActivityFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** Speaks JSON-RPC to /mcp the way a client does, session handshake included. */
#[ResetDatabase]
final class McpToolsTest extends WebTestCase
{
    use UsesOAuthToken;

    private KernelBrowser $client;
    private string $token;
    private string $session = '';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        self::createOAuthClient($this->client);
        $this->token = self::obtainTokens($this->client, UserFactory::new()->admin()->create())['access_token'];

        $this->rpc('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'test', 'version' => '1']]);
        $this->session = (string) $this->client->getResponse()->headers->get('Mcp-Session-Id');
        self::assertNotSame('', $this->session);
        $this->rpc('notifications/initialized', notification: true);
    }

    #[Test]
    public function everyToolIsListedAndReadOnly(): void
    {
        $tools = $this->rpc('tools/list')['result']['tools'];

        $names = array_map(static fn(array $tool): string => $tool['name'], $tools);
        sort($names);
        self::assertSame(['get_record', 'get_report', 'get_volunteer_photo', 'list_records'], $names);
        foreach ($tools as $tool) {
            self::assertTrue($tool['annotations']['readOnlyHint'], $tool['name']);
        }
    }

    #[Test]
    public function listRecordsFiltersAndLinksEachRecordToItsScreen(): void
    {
        VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Otieno']);
        VolunteerFactory::createOne(['firstName' => 'Brian', 'lastName' => 'Mwangi']);

        $list = $this->callTool('list_records', ['resource' => 'volunteers', 'filters' => ['search' => 'aisha']]);

        self::assertSame(1, $list['totalItems']);
        self::assertSame('Aisha Otieno', $list['member'][0]['fullName']);
        self::assertStringEndsWith('/volunteers/' . $list['member'][0]['id'], $list['member'][0]['appUrl']);
    }

    #[Test]
    public function getRecordReturnsAVolunteerInFullExceptThePassportNumber(): void
    {
        $volunteer = VolunteerFactory::createOne(['emergencyContacts' => 'Mum', 'notes' => 'Vegetarian']);

        $record = $this->callTool('get_record', ['resource' => 'volunteers', 'id' => $volunteer->getId()]);

        self::assertSame('Mum', $record['emergencyContacts']);
        self::assertSame('Vegetarian', $record['notes']);
        self::assertArrayNotHasKey('passportNumber', $record);
        self::assertArrayNotHasKey('passportNumberCiphertext', $record);
    }

    #[Test]
    public function aMissingRecordIsAToolErrorNotAProtocolOne(): void
    {
        $result = $this->rpc('tools/call', ['name' => 'get_record', 'arguments' => ['resource' => 'volunteers', 'id' => 999999]])['result'];

        self::assertTrue($result['isError'] ?? false);
        self::assertStringContainsString('HTTP 404', $result['content'][0]['text']);
    }

    #[Test]
    public function aMisspeltFilterIsAToolErrorNotEveryRecord(): void
    {
        VolunteerFactory::createOne();

        $result = $this->rpc('tools/call', ['name' => 'list_records', 'arguments' => ['resource' => 'volunteers', 'filters' => ['statuss' => 'present']]])['result'];

        self::assertTrue($result['isError'] ?? false);
    }

    #[Test]
    public function aResourceOutsideTheListIsRefusedBeforeAnyPathIsBuilt(): void
    {
        $response = $this->rpc('tools/call', ['name' => 'get_record', 'arguments' => ['resource' => '../login_attempts', 'id' => 1]]);

        self::assertTrue(isset($response['error']) || true === ($response['result']['isError'] ?? false), 'A traversal must not succeed.');
    }

    #[Test]
    public function getReportReturnsTheScreensFigures(): void
    {
        ActivityFactory::createMany(2);

        $metrics = $this->callTool('get_report', ['name' => 'metrics']);

        self::assertSame(2, $metrics['report']['activityCount']);
        self::assertStringEndsWith('/reports/volunteers', $metrics['screen']);
    }

    #[Test]
    public function aReportsRowsLinkToTheirRecords(): void
    {
        $activity = ActivityFactory::createOne();

        $byProject = $this->callTool('get_report', ['name' => 'activity-summary', 'params' => ['by' => 'project']]);

        self::assertStringEndsWith('/projects/' . $activity->getProject()?->getId(), $byProject['report'][0]['appUrl']);
    }

    #[Test]
    public function aReminderLinksToItsVolunteer(): void
    {
        $volunteer = VolunteerFactory::createOne(['dateOfBirth' => new \DateTimeImmutable('today -20 years')]);

        $text = $this->rpc('tools/call', ['name' => 'get_report', 'arguments' => ['name' => 'birthdays']])['result']['content'][0]['text'];

        self::assertStringContainsString('"volunteerUrl":', $text);
        self::assertStringContainsString('/volunteers/' . $volunteer->getId(), $text);
    }

    #[Test]
    public function anEmbeddedRelationLinksToItsScreenToo(): void
    {
        $activity = ActivityFactory::createOne();

        $text = $this->rpc('tools/call', ['name' => 'get_record', 'arguments' => ['resource' => 'activities', 'id' => $activity->getId()]])['result']['content'][0]['text'];

        self::assertStringContainsString('/volunteers/' . $activity->getVolunteer()?->getId() . '"', $text);
    }

    #[Test]
    public function aPhotoComesBackAsAnImage(): void
    {
        $volunteer = VolunteerFactory::new()->withPhoto()->create();

        $content = $this->rpc('tools/call', ['name' => 'get_volunteer_photo', 'arguments' => ['id' => $volunteer->getId()]])['result']['content'][0];

        self::assertSame('image', $content['type']);
        self::assertSame('image/jpeg', $content['mimeType']);
    }

    #[Test]
    public function aToolCallIsAuditedAsMcpWithoutItsSearchTerm(): void
    {
        $this->callTool('list_records', ['resource' => 'volunteers', 'filters' => ['search' => 'aisha']]);

        $rows = self::getContainer()->get(EntityManagerInterface::class)->getRepository(ApiAccess::class)->findAll();
        self::assertCount(1, $rows);
        self::assertSame('/api/volunteers', $rows[0]->getPath());
        self::assertTrue($rows[0]->isViaMcp());
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array{totalItems: int, member: list<array{id: int, fullName: string, appUrl: string}>, screen: string, report: array{activityCount: int, 0: array{appUrl: string}}, emergencyContacts: string, notes: string}
     */
    private function callTool(string $name, array $arguments): array
    {
        $result = $this->rpc('tools/call', ['name' => $name, 'arguments' => $arguments])['result'];
        self::assertFalse($result['isError'] ?? false, $result['content'][0]['text'] ?? '');

        /** @var array{totalItems: int, member: list<array{id: int, fullName: string, appUrl: string}>, screen: string, report: array{activityCount: int, 0: array{appUrl: string}}, emergencyContacts: string, notes: string} $decoded */
        $decoded = json_decode($result['content'][0]['text'], true, flags: \JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{result: array{tools: list<array{name: string, annotations: array{readOnlyHint: bool}}>, isError?: bool, content: list<array{type: string, text: string, mimeType: string}>}, error?: mixed}
     */
    private function rpc(string $method, array $params = [], bool $notification = false): array
    {
        $server = ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token, 'HTTP_ACCEPT' => 'application/json, text/event-stream', 'CONTENT_TYPE' => 'application/json'];
        if ('' !== $this->session) {
            $server['HTTP_MCP_SESSION_ID'] = $this->session;
            $server['HTTP_MCP_PROTOCOL_VERSION'] = '2025-06-18';
        }

        $message = ['jsonrpc' => '2.0', 'method' => $method] + ([] === $params ? [] : ['params' => $params]) + ($notification ? [] : ['id' => 1]);
        $this->client->request('POST', '/mcp', server: $server, content: (string) json_encode($message));

        /** @var array{result: array{tools: list<array{name: string, annotations: array{readOnlyHint: bool}}>, isError?: bool, content: list<array{type: string, text: string, mimeType: string}>}, error?: mixed} $response */
        $response = $notification ? ['result' => ['tools' => [], 'content' => []]] : json_decode((string) $this->client->getResponse()->getContent(), true);

        return $response;
    }
}
