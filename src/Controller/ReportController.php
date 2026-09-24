<?php

declare(strict_types=1);

namespace App\Controller;

use App\Pagination\ListPaginator;
use App\Report\ActivitySummaryCalculator;
use App\Report\ReportMetricsCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @phpstan-type SummaryRow array{id: ?int, label: string, count: int, totalDays?: float, days?: int, outings?: int, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}
 */
#[Route('/reports', name: 'report_')]
final class ReportController extends AbstractController
{
    private const string TAB_VOLUNTEER = 'volunteer';
    private const string TAB_PROJECT = 'project';
    private const string TAB_PROGRAM = 'program';
    private const string TAB_ESCORT = 'escort';
    private const string TAB_BRANCH = 'branch';

    /**
     * Column key => SummaryRow key for the breakdowns' sortable headers. The
     * tabs share label/count/mostRecent, which is why a sort survives a tab
     * switch intact; totalDays, days and outings each exist on some tabs only, and
     * sortArray() leaves rows missing the key in their original order. Unlike the CRUD indexes this sorts an array rather than a query,
     * so the values are array keys, not DQL paths. See ADR 0011.
     *
     * @var array<string, string>
     */
    private const array SORT_MAP = [
        'label' => 'label',
        'count' => 'count',
        'totalDays' => 'totalDays',
        'days' => 'days',
        'outings' => 'outings',
        'mostRecent' => 'mostRecent',
    ];

    public function __construct(
        private readonly ActivitySummaryCalculator $calculator,
        private readonly ReportMetricsCalculator $metrics,
        private readonly ListPaginator $paginator,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $today = new \DateTimeImmutable('today');

        // Anything unrecognised is the volunteer breakdown, so a mistyped or
        // stale ?tab= lands on the default rather than an error page. Read
        // through all(), because InputBag::get() throws on `?tab[]=project`,
        // which would be the error page this line exists to avoid.
        $requestedTab = $request->query->all()['tab'] ?? null;
        $tab = match ($requestedTab) {
            self::TAB_PROJECT, self::TAB_PROGRAM, self::TAB_ESCORT, self::TAB_BRANCH => $requestedTab,
            default => self::TAB_VOLUNTEER,
        };

        // Every breakdown is computed either way: each is an in-memory pass
        // over the activities, the "Top volunteers" card needs the whole
        // volunteer list, and the print panel needs all of them complete.
        // Paginating one of them costs no extra query.
        $byVolunteer = $this->calculator->summarizeByVolunteer();
        $byProject = $this->calculator->summarizeByProject();
        $byProgram = $this->calculator->summarizeByProgram();
        $byEscort = $this->calculator->summarizeByEscort();
        $byBranch = $this->calculator->summarizeByBranch();

        // Sorted before pagination, and across the whole breakdown rather than
        // the page — sorting a page would only shuffle the 25 rows already on
        // screen. $byVolunteer/$byProject themselves stay in the calculator's
        // totalDays order for the "Top volunteers" card and the print panel.
        $sorted = $this->paginator->sortArray(
            match ($tab) {
                self::TAB_PROJECT => $byProject,
                self::TAB_PROGRAM => $byProgram,
                self::TAB_ESCORT => $byEscort,
                self::TAB_BRANCH => $byBranch,
                default => $byVolunteer,
            },
            $request,
            self::SORT_MAP,
        );

        $pagination = $this->paginator->paginateArray($sorted, $request);

        /** @var list<SummaryRow> $pageOfRows */
        $pageOfRows = iterator_to_array($pagination, false);

        return $this->render('report/index.html.twig', [
            'metrics' => $this->metrics->calculate($today),
            // summarizeByVolunteer() already sorts by total days descending, so the
            // "Top volunteers" card is the head of this same list — no second pass.
            'byVolunteer' => $byVolunteer,
            'tab' => $tab,
            'columns' => $this->columnsFor($tab),
            'rows' => $this->toRows($pageOfRows, $today, $tab),
            'pagination' => $pagination,
            'sortState' => $this->paginator->sortState($request, self::SORT_MAP),
            // Complete and unpaginated, for the print-only panel. The
            // print-friendly view has always put every breakdown on paper in
            // full, and tabbing the screen mustn't quietly halve that.
            'volunteerRows' => $this->toRows($byVolunteer, $today, self::TAB_VOLUNTEER),
            'projectRows' => $this->toRows($byProject, $today, self::TAB_PROJECT),
            'programRows' => $this->toRows($byProgram, $today, self::TAB_PROGRAM),
            'escortRows' => $this->toRows($byEscort, $today, self::TAB_ESCORT),
            'branchRows' => $this->toRows($byBranch, $today, self::TAB_BRANCH),
            'volunteerColumns' => $this->columnsFor(self::TAB_VOLUNTEER),
            'projectColumns' => $this->columnsFor(self::TAB_PROJECT),
            'programColumns' => $this->columnsFor(self::TAB_PROGRAM),
            'escortColumns' => $this->columnsFor(self::TAB_ESCORT),
            'branchColumns' => $this->columnsFor(self::TAB_BRANCH),
        ]);
    }

    /** @return list<array{key: string, label: string}> */
    private function columnsFor(string $tab): array
    {
        // No "Total days" for escorts: days on duty are distinct dates, never
        // summed volunteer durations. See
        // ActivitySummaryCalculator::summarizeByEscort().
        if (self::TAB_ESCORT === $tab) {
            return [
                ['key' => 'label', 'label' => 'Escort'],
                ['key' => 'days', 'label' => 'Days on duty'],
                ['key' => 'outings', 'label' => 'Site visits'],
                ['key' => 'count', 'label' => 'Activities'],
                ['key' => 'mostRecent', 'label' => 'Most recent'],
            ];
        }

        return [
            ['key' => 'label', 'label' => match ($tab) {
                self::TAB_PROJECT => 'Project',
                self::TAB_PROGRAM => 'Program',
                self::TAB_BRANCH => 'Branch',
                default => 'Volunteer',
            }],
            ['key' => 'count', 'label' => 'Activities'],
            ['key' => 'totalDays', 'label' => 'Total days'],
            ['key' => 'mostRecent', 'label' => 'Most recent'],
        ];
    }

    /**
     * Summary rows in DataTable's shape. No 'actions' key — these rows are
     * read-only, which is what DataTable's withActions=false is for.
     *
     * $tab is what the caller knows and this method doesn't: which breakdown
     * these rows are. A volunteer's name links to their page and a program's
     * or a branch's to its activities (`/activities?program=<id>`,
     * `?branch=<id>`). A project's doesn't — it
     * has no show page, only an edit form, which is not where a report name
     * should land. The Unknown bucket carries no id, so it stays plain text. The Most recent date links on every tab, to
     * the edit form of the activity it came from: an activity has no other
     * page. On a date with several activities, that is one of them.
     *
     * @param list<SummaryRow> $summaries
     *
     * @return list<array{cells: array<string, string>, badges: array<string, string>, links: array<string, string>}>
     */
    private function toRows(array $summaries, \DateTimeImmutable $today, string $tab): array
    {
        $rows = [];
        foreach ($summaries as $summary) {
            $mostRecent = $summary['mostRecent'];
            $id = $summary['id'];
            // Same rule as the Activities cards, the home screen's tomorrow
            // roster and the "incl. N planned" tile: dated after today means
            // planned rather than done. Derived here rather than in
            // ActivitySummaryCalculator — the calculator already reports the
            // bucket's latest date, and comparing it to today adds no domain
            // knowledge, only a label this view happens to draw.
            $isPlanned = null !== $mostRecent && $mostRecent > $today;

            $cells = [
                'label' => $summary['label'],
                'count' => (string) $summary['count'],
                'mostRecent' => $mostRecent?->format('j M Y') ?? '—',
            ];
            if (isset($summary['totalDays'])) {
                // One decimal throughout, matching the Top volunteers card
                // and the "Total days contributed" tile. Before pagination
                // this table alone printed the raw float.
                $cells['totalDays'] = number_format($summary['totalDays'], 1);
            }
            if (isset($summary['days'])) {
                $cells['days'] = (string) $summary['days'];
            }
            if (isset($summary['outings'])) {
                $cells['outings'] = (string) $summary['outings'];
            }

            $links = [];
            if (self::TAB_VOLUNTEER === $tab && null !== $id) {
                $links['label'] = $this->generateUrl('volunteer_show', ['id' => $id]);
            }
            if (self::TAB_PROGRAM === $tab && null !== $id) {
                $links['label'] = $this->generateUrl('activity_index', ['program' => $id]);
            }
            if (self::TAB_BRANCH === $tab && null !== $id) {
                $links['label'] = $this->generateUrl('activity_index', ['branch' => $id]);
            }
            if (null !== $summary['mostRecentActivityId']) {
                $links['mostRecent'] = $this->generateUrl('activity_edit', ['id' => $summary['mostRecentActivityId']]);
            }

            $rows[] = [
                'cells' => $cells,
                'badges' => $isPlanned ? ['mostRecent' => 'Planned'] : [],
                'links' => $links,
            ];
        }

        return $rows;
    }
}
