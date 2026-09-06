<?php

declare(strict_types=1);

namespace App\Controller;

use App\Pagination\ListPaginator;
use App\Repository\UsageEventRepository;
use App\Usage\AccessLogReader;
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
 */
#[Route('/usage', name: 'usage_')]
#[IsGranted('ROLE_ADMIN')]
final class UsageController extends AbstractController
{
    /**
     * Column key => UsageRow key, array keys rather than DQL paths because
     * this sorts an in-memory report, exactly like /reports. See ADR 0011 —
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

    public function __construct(
        private readonly AccessLogReader $reader,
        private readonly UsageEventRepository $events,
        private readonly ListPaginator $paginator,
        private readonly CacheInterface $cache,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $reader = $this->reader;

        // One pass over the log would otherwise be paid again on every sort
        // click and every page link. A minute still answers "did anyone use
        // the thing I shipped last week".
        //
        // ponytail: fixed TTL. Keying on filemtime() would miss on every
        // single request — Caddy appends to this file continuously, including
        // the very request that renders this page.
        /** @var UsageReport $report */
        $report = $this->cache->get(
            'usage.access_log',
            static function (ItemInterface $item) use ($reader): array {
                $item->expiresAfter(60);

                return $reader->read();
            },
        );

        $sorted = $this->paginator->sortArray($report['rows'], $request, self::SORT_MAP);
        $pagination = $this->paginator->paginateArray($sorted, $request);

        /** @var list<UsageRow> $pageOfRows */
        $pageOfRows = iterator_to_array($pagination, false);

        return $this->render('usage/index.html.twig', [
            'report' => $report,
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
            // access log could ever show them. Small, unpaginated and
            // unsorted — there are four possible rows.
            'eventColumns' => [
                ['key' => 'label', 'label' => 'In-page action'],
                ['key' => 'count', 'label' => 'Times'],
                ['key' => 'lastSeen', 'label' => 'Last seen'],
            ],
            'eventRows' => array_map(
                static fn(array $event): array => [
                    'cells' => [
                        'label' => $event['name']->label(),
                        'count' => (string) $event['count'],
                        'lastSeen' => $event['lastSeen']->format('j M, H:i'),
                    ],
                    'badges' => [],
                    'links' => [],
                ],
                $this->events->summarize(),
            ),
        ]);
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
