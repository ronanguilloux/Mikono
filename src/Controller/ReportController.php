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
 * @phpstan-type SummaryRow array{label: string, count: int, totalDays: float, mostRecent: ?\DateTimeImmutable}
 */
#[Route('/reports', name: 'report_')]
final class ReportController extends AbstractController
{
    private const string TAB_VOLUNTEER = 'volunteer';
    private const string TAB_PROJECT = 'project';

    /**
     * Column key => SummaryRow key for the breakdowns' sortable headers. Both
     * tabs share these four keys, which is why a sort survives a tab switch
     * intact. Unlike the CRUD indexes this sorts an array rather than a query,
     * so the values are array keys, not DQL paths. See ADR 0011.
     *
     * @var array<string, string>
     */
    private const array SORT_MAP = [
        'label' => 'label',
        'count' => 'count',
        'totalDays' => 'totalDays',
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

        // Anything but "project" is the volunteer breakdown, so a mistyped or
        // stale ?tab= lands on the default rather than an error page.
        $tab = self::TAB_PROJECT === $request->query->get('tab')
            ? self::TAB_PROJECT
            : self::TAB_VOLUNTEER;

        // Both breakdowns are computed either way: they come from one in-memory
        // pass over the activities, the "Top volunteers" card needs the whole
        // volunteer list, and the print panel needs both complete. Paginating
        // one of them costs no extra query.
        $byVolunteer = $this->calculator->summarizeByVolunteer();
        $byProject = $this->calculator->summarizeByProject();

        // Sorted before pagination, and across the whole breakdown rather than
        // the page — sorting a page would only shuffle the 25 rows already on
        // screen. $byVolunteer/$byProject themselves stay in the calculator's
        // totalDays order for the "Top volunteers" card and the print panel.
        $sorted = $this->paginator->sortArray(
            self::TAB_PROJECT === $tab ? $byProject : $byVolunteer,
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
            'rows' => $this->toRows($pageOfRows, $today),
            'pagination' => $pagination,
            'sortState' => $this->paginator->sortState($request, self::SORT_MAP),
            // Complete and unpaginated, for the print-only panel. The
            // print-friendly view has always put both breakdowns on paper in
            // full, and tabbing the screen mustn't quietly halve that.
            'volunteerRows' => $this->toRows($byVolunteer, $today),
            'projectRows' => $this->toRows($byProject, $today),
            'volunteerColumns' => $this->columnsFor(self::TAB_VOLUNTEER),
            'projectColumns' => $this->columnsFor(self::TAB_PROJECT),
        ]);
    }

    /** @return list<array{key: string, label: string}> */
    private function columnsFor(string $tab): array
    {
        return [
            ['key' => 'label', 'label' => self::TAB_PROJECT === $tab ? 'Project' : 'Volunteer'],
            ['key' => 'count', 'label' => 'Activities'],
            ['key' => 'totalDays', 'label' => 'Total days'],
            ['key' => 'mostRecent', 'label' => 'Most recent'],
        ];
    }

    /**
     * Summary rows in DataTable's shape. No 'actions' key — these rows are
     * read-only, which is what DataTable's withActions=false is for.
     *
     * @param list<SummaryRow> $summaries
     *
     * @return list<array{cells: array<string, string>, badges: array<string, string>}>
     */
    private function toRows(array $summaries, \DateTimeImmutable $today): array
    {
        $rows = [];
        foreach ($summaries as $summary) {
            $mostRecent = $summary['mostRecent'];
            // Same rule as the Activities cards, the home screen's tomorrow
            // roster and the "incl. N planned" tile: dated after today means
            // planned rather than done. Derived here rather than in
            // ActivitySummaryCalculator — the calculator already reports the
            // bucket's latest date, and comparing it to today adds no domain
            // knowledge, only a label this view happens to draw.
            $isPlanned = null !== $mostRecent && $mostRecent > $today;

            $rows[] = [
                'cells' => [
                    'label' => $summary['label'],
                    'count' => (string) $summary['count'],
                    // One decimal throughout, matching the Top volunteers card
                    // and the "Total days contributed" tile. Before pagination
                    // this table alone printed the raw float.
                    'totalDays' => number_format($summary['totalDays'], 1),
                    'mostRecent' => $mostRecent?->format('j M Y') ?? '—',
                ],
                'badges' => $isPlanned ? ['mostRecent' => 'Planned'] : [],
            ];
        }

        return $rows;
    }
}
