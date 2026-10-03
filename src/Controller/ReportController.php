<?php

declare(strict_types=1);

namespace App\Controller;

use App\Pagination\ListPaginator;
use App\Repository\ProgramRepository;
use App\Repository\StayRepository;
use App\Report\ActivitySummaryCalculator;
use App\Report\ReportMetricsCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @phpstan-type SummaryRow array{id: ?int, label: string, count: int, totalDays?: float, volunteers?: int, parent?: ?string, days?: int, outings?: int, present?: int, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}
 */
#[Route('/reports', name: 'report_')]
final class ReportController extends AbstractController
{
    private const string TAB_VOLUNTEER = 'volunteer';
    private const string TAB_PROJECT = 'project';
    private const string TAB_PROGRAM = 'program';
    private const string TAB_ACTIVITY_TYPE = 'type';
    private const string TAB_ESCORT = 'escort';
    private const string TAB_BRANCH = 'branch';
    private const string TAB_BENEFICIARY_GROUP = 'group';
    private const string TAB_SOURCE = 'source';

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
        'volunteers' => 'volunteers',
        'parent' => 'parent',
        'days' => 'days',
        'outings' => 'outings',
        'present' => 'present',
        'mostRecent' => 'mostRecent',
    ];

    public function __construct(
        private readonly ActivitySummaryCalculator $calculator,
        private readonly ReportMetricsCalculator $metrics,
        private readonly ListPaginator $paginator,
        private readonly ProgramRepository $programs,
        private readonly StayRepository $stays,
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
            self::TAB_PROJECT, self::TAB_PROGRAM, self::TAB_ACTIVITY_TYPE, self::TAB_ESCORT, self::TAB_BRANCH, self::TAB_BENEFICIARY_GROUP, self::TAB_SOURCE => $requestedTab,
            default => self::TAB_VOLUNTEER,
        };

        // Every breakdown is computed either way: each is an in-memory pass
        // over the activities, the "Top volunteers" card needs the whole
        // volunteer list, and the print panel needs all of them complete.
        // Paginating one of them costs no extra query.
        $byVolunteer = $this->calculator->summarizeByVolunteer();
        $byProject = $this->calculator->summarizeByProject();
        $byProgram = $this->calculator->summarizeByProgram();
        $byActivityType = $this->calculator->summarizeByActivityType();
        $byEscort = $this->calculator->summarizeByEscort();
        $byBranch = $this->calculator->summarizeByBranch();
        $byBeneficiaryGroup = $this->calculator->summarizeByBeneficiaryGroup();
        $yearOptions = $this->yearOptions($today);
        $year = $this->requestedYear($request, $yearOptions, $today);
        $bySource = $this->calculator->summarizeBySource($year);

        // Sorted before pagination, and across the whole breakdown rather than
        // the page — sorting a page would only shuffle the 25 rows already on
        // screen. $byVolunteer/$byProject themselves stay in the calculator's
        // totalDays order for the "Top volunteers" card and the print panel.
        $sorted = $this->paginator->sortArray(
            match ($tab) {
                self::TAB_PROJECT => $byProject,
                self::TAB_PROGRAM => $byProgram,
                self::TAB_ACTIVITY_TYPE => $byActivityType,
                self::TAB_ESCORT => $byEscort,
                self::TAB_BRANCH => $byBranch,
                self::TAB_BENEFICIARY_GROUP => $byBeneficiaryGroup,
                self::TAB_SOURCE => $bySource,
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
            'year' => $year,
            'yearOptions' => $yearOptions,
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
            'activityTypeRows' => $this->toRows($byActivityType, $today, self::TAB_ACTIVITY_TYPE),
            'escortRows' => $this->toRows($byEscort, $today, self::TAB_ESCORT),
            'branchRows' => $this->toRows($byBranch, $today, self::TAB_BRANCH),
            'groupRows' => $this->toRows($byBeneficiaryGroup, $today, self::TAB_BENEFICIARY_GROUP),
            'sourceRows' => $this->toRows($bySource, $today, self::TAB_SOURCE),
            'volunteerColumns' => $this->columnsFor(self::TAB_VOLUNTEER),
            'projectColumns' => $this->columnsFor(self::TAB_PROJECT),
            'programColumns' => $this->columnsFor(self::TAB_PROGRAM),
            'activityTypeColumns' => $this->columnsFor(self::TAB_ACTIVITY_TYPE),
            'escortColumns' => $this->columnsFor(self::TAB_ESCORT),
            'branchColumns' => $this->columnsFor(self::TAB_BRANCH),
            'groupColumns' => $this->columnsFor(self::TAB_BENEFICIARY_GROUP),
            'sourceColumns' => $this->columnsFor(self::TAB_SOURCE),
            // Free text, so it can't be a sortable column: printed as notes
            // under the program table instead (ADR 0030).
            'beneficiaries' => $this->programs->createOrderedQueryBuilder()
                ->andWhere('prg.beneficiariesReached IS NOT NULL')
                ->getQuery()
                ->getResult(),
        ]);
    }

    /**
     * Every year a stay touches, plus this one, newest first.
     *
     * @return non-empty-list<int>
     */
    private function yearOptions(\DateTimeImmutable $today): array
    {
        $thisYear = (int) $today->format('Y');
        [$first, $last] = $this->stays->findYearSpan() ?? [$thisYear, $thisYear];

        return range(max($last, $thisYear), min($first, $thisYear));
    }

    /**
     * The source tab's `?year=`. Anything not among the options, malformed
     * input included, means this year (ADR 0023).
     *
     * @param list<int> $options
     */
    private function requestedYear(Request $request, array $options, \DateTimeImmutable $today): int
    {
        $raw = $request->query->all()['year'] ?? null;
        $year = is_scalar($raw) ? (int) $raw : 0;

        return in_array($year, $options, true) ? $year : (int) $today->format('Y');
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

        $columns = [
            ['key' => 'label', 'label' => match ($tab) {
                self::TAB_PROJECT => 'Project',
                self::TAB_PROGRAM => 'Program',
                self::TAB_ACTIVITY_TYPE => 'Activity type',
                self::TAB_BRANCH => 'Branch',
                self::TAB_BENEFICIARY_GROUP => 'Beneficiary group',
                self::TAB_SOURCE => 'Source',
                default => 'Volunteer',
            }],
        ];
        if (self::TAB_SOURCE === $tab) {
            $columns[] = ['key' => 'present', 'label' => 'Volunteers present'];
        }
        // What the row belongs to: program names repeat across projects.
        if (self::TAB_PROJECT === $tab) {
            $columns[] = ['key' => 'parent', 'label' => 'Branch'];
        }
        if (self::TAB_PROGRAM === $tab) {
            $columns[] = ['key' => 'parent', 'label' => 'Project'];
        }
        array_push(
            $columns,
            ['key' => 'count', 'label' => 'Activities'],
            ['key' => 'totalDays', 'label' => 'Total days'],
        );
        // A volunteer's own row would always say 1.
        if (self::TAB_VOLUNTEER !== $tab) {
            $columns[] = ['key' => 'volunteers', 'label' => 'Volunteers engaged'];
        }
        $columns[] = ['key' => 'mostRecent', 'label' => 'Most recent'];

        return $columns;
    }

    /**
     * Summary rows in DataTable's shape. No 'actions' key — these rows are
     * read-only, which is what DataTable's withActions=false is for.
     *
     * $tab is what the caller knows and this method doesn't: which breakdown
     * these rows are. A volunteer's name links to their page and a program's
     * or a branch's to its activities (`/activities?program=<id>`,
     * `?branch=<id>`), a source's to the volunteers holding it
     * (`/volunteers?source=<id>`). A project's goes to its edit form, the only project
     * page there is. The Unknown bucket carries no id, so it stays plain text. The Most recent date links on every tab, to
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
            if (self::TAB_PROJECT === $tab || self::TAB_PROGRAM === $tab) {
                $cells['parent'] = $summary['parent'] ?? '—';
            }
            if (isset($summary['volunteers'])) {
                $cells['volunteers'] = (string) $summary['volunteers'];
            }
            if (isset($summary['days'])) {
                $cells['days'] = (string) $summary['days'];
            }
            if (isset($summary['outings'])) {
                $cells['outings'] = (string) $summary['outings'];
            }
            if (isset($summary['present'])) {
                $cells['present'] = (string) $summary['present'];
            }

            $links = [];
            if (self::TAB_VOLUNTEER === $tab && null !== $id) {
                $links['label'] = $this->generateUrl('volunteer_show', ['id' => $id]);
            }
            if (self::TAB_PROJECT === $tab && null !== $id) {
                $links['label'] = $this->generateUrl('project_edit', ['id' => $id]);
            }
            if (self::TAB_PROGRAM === $tab && null !== $id) {
                $links['label'] = $this->generateUrl('activity_index', ['program' => $id]);
            }
            if (self::TAB_BRANCH === $tab && null !== $id) {
                $links['label'] = $this->generateUrl('activity_index', ['branch' => $id]);
            }
            if (self::TAB_SOURCE === $tab && null !== $id) {
                $links['label'] = $this->generateUrl('volunteer_index', ['source' => $id]);
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
