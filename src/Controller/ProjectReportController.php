<?php

declare(strict_types=1);

namespace App\Controller;

use App\Export\ListExport;
use App\Pagination\ListPaginator;
use App\Report\ProjectOverviewCalculator;
use App\Report\QuietProjectFinder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Reports → Projects Overview: where to send the next volunteer, where the
 * effort goes, and which branch is falling behind. The table is the full
 * list behind the two project blocks on the home screen.
 *
 * @phpstan-import-type ProjectRow from ProjectOverviewCalculator
 */
final class ProjectReportController extends AbstractController
{
    private const int TOP = 10;

    /** @var array<string, string> column key => row field */
    private const array SORT_MAP = [
        'name' => 'name',
        'branch' => 'branch',
        'lastActivity' => 'lastActivity',
        'volunteers' => 'volunteers',
        'days' => 'days',
        'booked' => 'booked',
        'placesToFill' => 'placesToFill',
        'openPrograms' => 'openPrograms',
        'achievements' => 'achievements',
    ];

    private const array COLUMNS = [
        ['key' => 'name', 'label' => 'Project'],
        ['key' => 'branch', 'label' => 'Branch'],
        ['key' => 'ownership', 'label' => 'Ownership'],
        ['key' => 'status', 'label' => 'Status'],
        ['key' => 'lastActivity', 'label' => 'Last activity'],
        ['key' => 'volunteers', 'label' => 'Volunteers'],
        ['key' => 'days', 'label' => 'Volunteer days'],
        ['key' => 'booked', 'label' => 'Booked'],
        ['key' => 'placesToFill', 'label' => 'Places to fill'],
        ['key' => 'openPrograms', 'label' => 'Open programs'],
        ['key' => 'achievements', 'label' => 'Achievements'],
    ];

    public function __construct(
        private readonly ProjectOverviewCalculator $overview,
        private readonly ListPaginator $paginator,
    ) {}

    #[Route('/reports/projects', name: 'report_projects', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $today = new \DateTimeImmutable('today');
        $overview = $this->overview->calculate($today);
        $rows = $overview['rows'];

        $tableRows = [];
        $pagination = $this->paginator->paginateArray($this->paginator->sortArray($rows, $request, self::SORT_MAP), $request);
        /** @var ProjectRow $row */
        foreach ($pagination as $row) {
            $tableRows[] = [
                'cells' => self::cells($row),
                'links' => ['name' => $this->generateUrl('project_show', ['id' => $row['id']])],
            ];
        }

        $needing = array_filter($rows, static fn(array $row): bool => \in_array($row['status'], ['Never visited', 'Quiet'], true));
        $totalDays = array_sum(array_column($rows, 'days'));

        return $this->render('report/projects.html.twig', [
            'active' => \count($rows),
            'branches' => \count(array_unique(array_column($rows, 'branch'))),
            'needing' => \count($needing),
            'neverVisited' => \count(array_filter($rows, static fn(array $row): bool => 'Never visited' === $row['status'])),
            'placesToFill' => array_sum(array_column($rows, 'placesToFill')),
            'openPrograms' => $overview['openPrograms'],
            'withTarget' => $overview['withTarget'],
            'quietAfterDays' => QuietProjectFinder::WARN_AFTER_DAYS,
            'months' => $overview['months'],
            'from' => $overview['from'],
            'totalDays' => $totalDays,
            'placeBars' => self::top($rows, 'placesToFill', static fn(array $row): array => ['label' => $row['name'], 'value' => $row['placesToFill']]),
            'dayBars' => self::top($rows, 'days', static fn(array $row): array => [
                'label' => $row['name'],
                'value' => $row['days'],
                'detail' => number_format($row['days'] / $totalDays * 100, 1) . '%',
            ]),
            'branchBars' => self::branchBars($rows),
            'columns' => self::COLUMNS,
            'rows' => $tableRows,
            'pagination' => $pagination,
            'sortState' => $this->paginator->sortState($request, self::SORT_MAP),
        ]);
    }

    #[Route('/reports/projects/export.{format}', name: 'report_projects_export', requirements: ['format' => 'csv|xlsx'], defaults: ['format' => 'csv'], methods: ['GET'])]
    public function export(string $format): StreamedResponse
    {
        $rows = $this->overview->calculate(new \DateTimeImmutable('today'))['rows'];

        return ListExport::response('projects-overview', $format, self::COLUMNS, array_map(self::cells(...), $rows));
    }

    /**
     * @param ProjectRow $row
     *
     * @return array<string, string>
     */
    private static function cells(array $row): array
    {
        return [
            'name' => $row['name'],
            'branch' => $row['branch'],
            'ownership' => $row['ownership'],
            'status' => $row['status'],
            'lastActivity' => $row['lastActivity']?->format('j M Y') ?? '—',
            'volunteers' => (string) $row['volunteers'],
            'days' => number_format($row['days'], 1),
            'booked' => (string) $row['booked'],
            'placesToFill' => (string) $row['placesToFill'],
            'openPrograms' => (string) $row['openPrograms'],
            'achievements' => (string) $row['achievements'],
        ];
    }

    /**
     * The largest non-zero rows on one field, as bars.
     *
     * @param list<ProjectRow>                                                              $rows
     * @param 'days'|'placesToFill'                                                         $field
     * @param callable(ProjectRow): array{label: string, value: float|int, detail?: string} $bar
     *
     * @return list<array{label: string, value: float|int, detail?: string}>
     */
    private static function top(array $rows, string $field, callable $bar): array
    {
        $rows = array_filter($rows, static fn(array $row): bool => $row[$field] > 0);
        usort($rows, static fn(array $a, array $b): int => $b[$field] <=> $a[$field]);

        return array_map($bar, \array_slice($rows, 0, self::TOP));
    }

    /**
     * Quiet projects per branch, most first, each against the branch's total.
     *
     * @param list<ProjectRow> $rows
     *
     * @return list<array{label: string, value: int, detail: string}>
     */
    private static function branchBars(array $rows): array
    {
        $totals = $quiet = [];
        foreach ($rows as $row) {
            $totals[$row['branch']] = ($totals[$row['branch']] ?? 0) + 1;
            if (\in_array($row['status'], ['Never visited', 'Quiet'], true)) {
                $quiet[$row['branch']] = ($quiet[$row['branch']] ?? 0) + 1;
            }
        }
        arsort($quiet);

        $bars = [];
        foreach ($quiet as $branch => $count) {
            $bars[] = ['label' => $branch, 'value' => $count, 'detail' => 'of ' . $totals[$branch]];
        }

        return $bars;
    }
}
