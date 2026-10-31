<?php

declare(strict_types=1);

namespace App\Mcp;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Content\ImageContent;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The MCP surface: a few tools, each a sub-request into /api, so a tool can't
 * show a field, filter differently or skip the audit that REST wouldn't. The
 * paths are built here: a resource must be a key of SCREENS, an id is an int
 * and a report name is URL-encoded into one segment, which ApiController answers
 * with a 404 unless it knows it. The schemas' enums are a hint to Claude, not
 * this class's guard. See ADR 0050.
 *
 * Sub-requests skip the firewall, which is fine: it already ran for /mcp, and
 * access_control holds both paths to the same rule.
 */
final readonly class MikonoTools
{
    /** API collection => the screen a person opens to check a record. */
    private const array SCREENS = [
        'volunteers' => 'volunteer_show',
        'stays' => 'stay_show',
        'activities' => 'activity_edit',
        'achievements' => 'achievement_edit',
        'projects' => 'project_show',
        'programs' => 'program_show',
        'branches' => 'branch_show',
        'skills' => 'skill_edit',
        'sources' => 'source_edit',
        'activity_types' => 'activity_type_edit',
        'beneficiary_groups' => 'beneficiary_group_edit',
        'indicators' => 'indicator_edit',
        'escorts' => 'escort_edit',
        'users' => 'user_show',
    ];

    /** Report => the screen showing it. */
    private const array REPORT_SCREENS = [
        'metrics' => 'report_volunteers',
        'activity-summary' => 'report_volunteers',
        'periods' => 'report_volunteers',
        'roster' => 'app_home',
        'quiet-projects' => 'app_home',
        'program-matches' => 'match_index',
        'volunteer-impact' => 'volunteer_show',
        'stay-impact' => 'stay_show',
        'impact-by-month' => 'achievement_index',
        'occupancy' => 'app_home',
        'birthdays' => 'app_home',
        'arrivals' => 'app_home',
        'departures' => 'app_home',
        'anniversaries' => 'app_home',
    ];

    /** A report row's id field => the screen it opens. */
    private const array ID_SCREENS = [
        'volunteerId' => 'volunteer_show',
        'stayId' => 'stay_show',
        'projectId' => 'project_show',
        'mostRecentActivityId' => 'activity_edit',
    ];

    private const string RULES = ' A volunteer\'s status is derived from their stays. An activity\'s project comes through its program. Records carry appUrl: cite it, so a person can check the answer.';

    public function __construct(
        private HttpKernelInterface $kernel,
        private UrlGeneratorInterface $urls,
    ) {}

    /**
     * @param array<string, scalar> $filters
     */
    #[McpTool(
        name: 'list_records',
        title: 'List records',
        description: 'List UCESCO records, newest or most relevant first, 30 a page, with totalItems. Filters: volunteers: search, status (present|upcoming|past|none), branch, skill, source. stays: volunteer, branch, startDate[after|before], endDate[after|before]. activities: volunteer, program, project, activityType, date[after|before]. achievements: volunteer, stay, project, indicator, achievedOn[after|before]. projects: name, branch. programs: name, project. Dates are YYYY-MM-DD, relations take an id.' . self::RULES,
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listRecords(
        #[Schema(enum: ['volunteers', 'stays', 'activities', 'achievements', 'projects', 'programs', 'branches', 'skills', 'sources', 'activity_types', 'beneficiary_groups', 'indicators', 'escorts', 'users'])]
        string $resource,
        #[Schema(type: 'object', additionalProperties: true)]
        array $filters = [],
        int $page = 1,
        #[Schema(minimum: 1, maximum: 100)]
        int $perPage = 30,
    ): CallToolResult {
        if (!isset(self::SCREENS[$resource])) {
            return self::unknown($resource);
        }

        $response = $this->get('/api/' . $resource, [...$filters, 'page' => $page, 'itemsPerPage' => $perPage]);
        if (!$response->isSuccessful()) {
            return self::failure($response);
        }

        return self::json($this->withLinks(self::decode($response)));
    }

    #[McpTool(
        name: 'get_record',
        title: 'Get a record',
        description: 'One UCESCO record in full, by id. A volunteer includes personal data; the passport number is never available.' . self::RULES,
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getRecord(
        #[Schema(enum: ['volunteers', 'stays', 'activities', 'achievements', 'projects', 'programs', 'branches', 'skills', 'sources', 'activity_types', 'beneficiary_groups', 'indicators', 'escorts', 'users'])]
        string $resource,
        int $id,
    ): CallToolResult {
        if (!isset(self::SCREENS[$resource])) {
            return self::unknown($resource);
        }

        $response = $this->get(\sprintf('/api/%s/%d', $resource, $id));

        return $response->isSuccessful() ? self::json($this->withLinks(self::decode($response))) : self::failure($response);
    }

    /**
     * @param array<string, scalar> $params
     */
    #[McpTool(
        name: 'get_report',
        title: 'Get a report',
        description: 'The figures shown on Mikono\'s screens: screen is the page to check them on, and rows carry appUrl, volunteerUrl, stayUrl or projectUrl where they name a record. Cite those. Params by report: activity-summary: by (volunteer|project|program|activity_type|escort|branch|beneficiary_group|source), year with source. periods: step (month|year), branch. roster: date (YYYY-MM-DD, default today). program-matches: branch, program, who (present|upcoming). volunteer-impact: volunteer. stay-impact: stay. The rest take none. On activity-summary a half day counts 0.5 and a full day 1, and an Other duration counts 0, so totalDays undercounts it. On volunteer-impact and stay-impact every activity counts as one day. Hours are not recorded.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getReport(
        #[Schema(enum: ['metrics', 'activity-summary', 'periods', 'roster', 'quiet-projects', 'program-matches', 'volunteer-impact', 'stay-impact', 'impact-by-month', 'occupancy', 'birthdays', 'arrivals', 'departures', 'anniversaries'])]
        string $name,
        #[Schema(type: 'object', additionalProperties: true)]
        array $params = [],
    ): CallToolResult {
        $response = $this->get('/api/reports/' . rawurlencode($name), $params);

        if (!$response->isSuccessful()) {
            return self::failure($response);
        }

        $screen = match ($name) {
            'volunteer-impact' => ['id' => $params['volunteer']],
            'stay-impact' => ['id' => $params['stay']],
            default => [],
        };

        return self::json([
            'screen' => $this->urls->generate(self::REPORT_SCREENS[$name], $screen, UrlGeneratorInterface::ABSOLUTE_URL),
            'report' => $this->withLinks(self::decode($response)),
        ]);
    }

    #[McpTool(
        name: 'get_volunteer_photo',
        title: 'Get a volunteer\'s photo',
        description: 'A volunteer\'s photo, when their record has hasPhoto true.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getVolunteerPhoto(int $id): CallToolResult
    {
        $response = $this->get(\sprintf('/api/volunteers/%d/photo', $id));

        return $response->isSuccessful()
            ? new CallToolResult([new ImageContent(base64_encode((string) $response->getContent()), 'image/jpeg')])
            : self::failure($response);
    }

    /**
     * @param array<string, scalar> $query
     */
    private function get(string $path, array $query = []): Response
    {
        return $this->kernel->handle(
            Request::create($path . '?' . http_build_query($query), 'GET', server: ['HTTP_ACCEPT' => 'application/ld+json']),
            HttpKernelInterface::SUB_REQUEST,
        );
    }

    /**
     * Adds the screen a person opens to check each record, at any depth, so an
     * embedded relation and a report row are linked like a top-level record.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function withLinks(array $data): array
    {
        $data = array_map(fn(mixed $value): mixed => \is_array($value) ? $this->withLinks($value) : $value, $data);

        $iri = $data['@id'] ?? null;
        if (\is_string($iri) && 1 === preg_match('#^/api/([a-z_]+)/(\d+)$#', $iri, $match) && isset(self::SCREENS[$match[1]])) {
            $data['appUrl'] = $this->urls->generate(self::SCREENS[$match[1]], ['id' => (int) $match[2]], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        foreach (self::ID_SCREENS as $key => $route) {
            if (\is_int($data[$key] ?? null)) {
                $data[substr($key, 0, -2) . 'Url'] = $this->urls->generate($route, ['id' => $data[$key]], UrlGeneratorInterface::ABSOLUTE_URL);
            }
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private static function decode(Response $response): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /** @param array<array-key, mixed> $data */
    private static function json(array $data): CallToolResult
    {
        return new CallToolResult([new TextContent(json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES))]);
    }

    private static function unknown(string $resource): CallToolResult
    {
        return new CallToolResult([new TextContent(\sprintf('Unknown resource "%s".', $resource))], isError: true);
    }

    private static function failure(Response $response): CallToolResult
    {
        return new CallToolResult([new TextContent(\sprintf('HTTP %d: %s', $response->getStatusCode(), mb_substr((string) $response->getContent(), 0, 500)))], isError: true);
    }
}
