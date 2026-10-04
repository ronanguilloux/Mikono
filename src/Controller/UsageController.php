<?php

declare(strict_types=1);

namespace App\Controller;

use App\Pagination\ListPaginator;
use App\Repository\LoginAttemptRepository;
use App\Repository\UsageEventRepository;
use App\Security\LoginAttemptRecorder;
use App\Usage\AccessLogReader;
use App\Usage\UsageDateRange;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Which screens get used, and which of them are fighting the reader.
 *
 * ROLE_ADMIN like /users: these rows describe a named colleague's working day,
 * and while the route patterns carry no record identifiers, who-uses-what is
 * still not everyone's business.
 *
 * @phpstan-import-type UsageRow from AccessLogReader
 * @phpstan-import-type UsageReport from AccessLogReader
 *
 * @phpstan-type SignInRow array{account: string, accountSort: string, ip: ?string, succeeded: int, failed: int, lastAttempt: \DateTimeImmutable, userId: ?int}
 * @phpstan-type EventRow array{label: string, count: int, lastSeen: \DateTimeImmutable}
 */
#[Route('/usage', name: 'usage_')]
#[IsGranted('ROLE_ADMIN')]
final class UsageController extends AbstractController
{
    /**
     * Column key => UsageRow key, array keys rather than DQL paths because
     * this sorts an in-memory report, exactly like /reports/volunteers. See ADR 0011 —
     * the map IS the whitelist, so nothing a reader types reaches anything.
     *
     * @var array<string, string>
     */
    private const array SORT_MAP = [
        'path' => 'path',
        'views' => 'views',
        'rejected' => 'rejected',
        'errors' => 'serverErrors',
        'p95' => 'p95',
        'lastSeen' => 'lastSeen',
    ];

    /**
     * The Sign-ins table's, keyed into the rows signInRows() builds. Its params
     * are prefixed `signIns` (ListPaginator::param()) so the two tables on this
     * page don't share one `sort` and one `page`.
     *
     * @var array<string, string>
     */
    private const array LOGIN_SORT_MAP = [
        'account' => 'accountSort',
        'ip' => 'ip',
        'succeeded' => 'succeeded',
        'failed' => 'failed',
        'lastAttempt' => 'lastAttempt',
    ];

    private const string LOGIN_PARAM_PREFIX = 'signIns';

    /**
     * The In-page actions table's, keyed into the rows eventRows() builds and
     * prefixed `events` for the same reason Sign-ins is prefixed `signIns`.
     *
     * @var array<string, string>
     */
    private const array EVENT_SORT_MAP = [
        'label' => 'label',
        'count' => 'count',
        'lastSeen' => 'lastSeen',
    ];

    private const string EVENT_PARAM_PREFIX = 'events';

    public function __construct(
        private readonly AccessLogReader $reader,
        private readonly UsageEventRepository $events,
        private readonly LoginAttemptRepository $loginAttempts,
        private readonly ListPaginator $paginator,
        private readonly CacheInterface $cache,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $reader = $this->reader;
        $range = UsageDateRange::fromRequest($request);

        // One pass over the log would otherwise be paid again on every sort
        // click and every page link. A minute still answers "did anyone use
        // the thing I shipped last week".
        //
        // The resolved range is part of the key, not just of the closure:
        // without it every filter would serve whatever the previous one
        // cached, for up to a minute.
        //
        // ponytail: fixed TTL, and one entry per distinct range. A custom
        // from/to pair can mint a new key, but this is an admin-only screen
        // and each entry expires in 60s — not worth bounding the key set.
        // Keying on filemtime() would miss on every single request — Caddy
        // appends to this file continuously, including the very request that
        // renders this page.
        /** @var UsageReport $report */
        $report = $this->cache->get(
            'usage.access_log.' . $range->cacheKey(),
            static function (ItemInterface $item) use ($reader, $range): array {
                $item->expiresAfter(60);

                return $reader->read($range);
            },
        );

        $sorted = $this->paginator->sortArray($report['rows'], $request, self::SORT_MAP);
        $pagination = $this->paginator->paginateArray($sorted, $request);

        /** @var list<UsageRow> $pageOfRows */
        $pageOfRows = iterator_to_array($pagination, false);

        $loginPagination = $this->paginator->paginateArray(
            $this->paginator->sortArray(
                $this->signInRows($range),
                $request,
                self::LOGIN_SORT_MAP,
                self::LOGIN_PARAM_PREFIX,
            ),
            $request,
            self::LOGIN_PARAM_PREFIX,
        );

        /** @var list<SignInRow> $pageOfLogins */
        $pageOfLogins = iterator_to_array($loginPagination, false);

        $eventPagination = $this->paginator->paginateArray(
            $this->paginator->sortArray(
                $this->eventRows($range),
                $request,
                self::EVENT_SORT_MAP,
                self::EVENT_PARAM_PREFIX,
            ),
            $request,
            self::EVENT_PARAM_PREFIX,
        );

        /** @var list<EventRow> $pageOfEvents */
        $pageOfEvents = iterator_to_array($eventPagination, false);

        return $this->render('usage/index.html.twig', [
            'report' => $report,
            'range' => $range,
            'presets' => UsageDateRange::PRESETS,
            'columns' => [
                ['key' => 'path', 'label' => 'Screen'],
                ['key' => 'views', 'label' => 'Views'],
                ['key' => 'rejected', 'label' => 'Rejected'],
                ['key' => 'errors', 'label' => 'Errors'],
                ['key' => 'p95', 'label' => 'p95'],
                ['key' => 'lastSeen', 'label' => 'Last seen'],
            ],
            'rows' => $this->toRows($pageOfRows),
            'pagination' => $pagination,
            'sortState' => $this->paginator->sortState($request, self::SORT_MAP),
            // The in-page half: gestures that never reach the server, so no
            // access log could ever show them.
            'eventColumns' => [
                ['key' => 'label', 'label' => 'In-page action'],
                ['key' => 'count', 'label' => 'Times'],
                ['key' => 'lastSeen', 'label' => 'Last seen'],
            ],
            'eventRows' => array_map(
                static fn(array $event): array => [
                    'cells' => [
                        'label' => $event['label'],
                        'count' => (string) $event['count'],
                        'lastSeen' => $event['lastSeen']->format('j M, H:i'),
                    ],
                    'badges' => [],
                    'links' => [],
                ],
                $pageOfEvents,
            ),
            'eventPagination' => $eventPagination,
            'eventSortState' => $this->paginator->sortState($request, self::EVENT_SORT_MAP, self::EVENT_PARAM_PREFIX),
            // The personal half (ADR 0028): who tried to sign in, from where.
            'loginColumns' => [
                ['key' => 'account', 'label' => 'Account'],
                ['key' => 'ip', 'label' => 'IP'],
                ['key' => 'succeeded', 'label' => 'Signed in'],
                ['key' => 'failed', 'label' => 'Failed'],
                ['key' => 'lastAttempt', 'label' => 'Last attempt'],
            ],
            'loginRows' => array_map(
                fn(array $row): array => [
                    'cells' => [
                        'account' => $row['account'],
                        'ip' => $row['ip'] ?? '—',
                        'succeeded' => (string) $row['succeeded'],
                        'failed' => (string) $row['failed'],
                        'lastAttempt' => $row['lastAttempt']->format('j M, H:i'),
                    ],
                    // Badges rather than colour alone: the amber row says
                    // nothing to a screen reader or on paper.
                    'badges' => array_filter([
                        'account' => null === $row['userId'] ? 'Unknown account' : null,
                        'failed' => $row['failed'] > 0 ? 'Failed' : null,
                    ]),
                    'links' => null === $row['userId']
                        ? []
                        : ['account' => $this->generateUrl('user_show', ['id' => $row['userId']])],
                ] + (null === $row['userId'] ? ['tone' => 'warning'] : []),
                $pageOfLogins,
            ),
            'loginPagination' => $loginPagination,
            'loginSortState' => $this->paginator->sortState($request, self::LOGIN_SORT_MAP, self::LOGIN_PARAM_PREFIX),
            'loginRetentionDays' => LoginAttemptRecorder::RETENTION_DAYS,
        ]);
    }

    /**
     * Sign-in summaries keyed for LOGIN_SORT_MAP. `account` is the text the
     * reader sees — the user's name when the address is a known account, the
     * address as typed otherwise — so sorting follows what's on screen.
     *
     * @return list<SignInRow>
     */
    private function signInRows(UsageDateRange $range): array
    {
        return array_map(
            static function (array $attempt): array {
                $account = $attempt['userName'] ?? $attempt['identifier'] ?? 'not an email address';

                return [
                    'account' => $account,
                    // Lowercased, or every capitalised name sorts ahead of every
                    // lowercase address.
                    'accountSort' => mb_strtolower($account),
                    'ip' => $attempt['ip'],
                    'succeeded' => $attempt['succeeded'],
                    'failed' => $attempt['failed'],
                    'lastAttempt' => $attempt['lastAttempt'],
                    'userId' => $attempt['userId'],
                ];
            },
            $this->loginAttempts->summarize($range),
        );
    }

    /**
     * In-page action summaries keyed for EVENT_SORT_MAP. `label` is the text
     * the reader sees, so sorting follows what's on screen, not the enum value.
     *
     * @return list<EventRow>
     */
    private function eventRows(UsageDateRange $range): array
    {
        return array_map(
            static fn(array $event): array => [
                'label' => $event['name']->label(),
                'count' => $event['count'],
                'lastSeen' => $event['lastSeen'],
            ],
            $this->events->summarize($range),
        );
    }

    /**
     * Report rows in DataTable's shape. No 'actions' key — there is nothing to
     * do to a row here, which is what withActions=false is for.
     *
     * @param list<UsageRow> $usageRows
     *
     * @return list<array{cells: array<string, string>, badges: array<string, string>, links: array<string, string>}>
     */
    private function toRows(array $usageRows): array
    {
        $rows = [];

        foreach ($usageRows as $row) {
            $errors = $row['clientErrors'] + $row['serverErrors'];

            // Badges, not concatenation: `cells` must stay the plain formatted
            // value or sorting and every test matching a cell by its text
            // starts seeing the decoration too (see CLAUDE.md on DataTable).
            $badges = [];
            if ($row['serverErrors'] > 0) {
                $badges['errors'] = 'Server';
            }
            if ($row['p95'] >= 1.0) {
                $badges['p95'] = 'Slow';
            }
            if ('GET' !== $row['method']) {
                $badges['path'] = $row['method'];
            }

            $rows[] = [
                'cells' => [
                    'path' => $row['path'],
                    'views' => (string) $row['views'],
                    'rejected' => $row['rejected'] > 0 ? (string) $row['rejected'] : '—',
                    'errors' => $errors > 0 ? (string) $errors : '—',
                    'p95' => number_format($row['p95'], 2) . 's',
                    'lastSeen' => $row['lastSeen']?->format('j M, H:i') ?? '—',
                ],
                'badges' => $badges,
                'links' => [],
            ];
        }

        return $rows;
    }
}
