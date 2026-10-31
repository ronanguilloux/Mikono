<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Branch;
use App\Entity\Project;
use App\Entity\Stay;
use App\Export\ListExport;
use App\Form\BranchFormType;
use App\Pagination\ListPaginator;
use App\Report\OccupancyLookAhead;
use App\Report\PeriodTotalsCalculator;
use App\Repository\ActivityRepository;
use App\Repository\BranchRepository;
use App\Repository\ProjectRepository;
use App\Repository\StayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Same shape as ProjectController. The delete-guard counts stays and
 * projects: a branch either points to can't be deleted, only marked inactive
 * (ADR 0025, ADR 0027).
 */
#[Route('/branches', name: 'branch_')]
final class BranchController extends AbstractController
{
    /**
     * Column key => DQL field(s) for the index's sortable headers; the map is
     * the whitelist. See ADR 0011.
     *
     * @var array<string, non-empty-list<string>>
     */
    private const array SORT_MAP = [
        'name' => ['b.name'],
        'physicalLocation' => ['b.physicalLocation'],
        'status' => ['b.isActive'],
    ];

    /** @var list<array{key: string, label: string}> */
    private const array COLUMNS = [
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'physicalLocation', 'label' => 'Physical location'],
        ['key' => 'capacity', 'label' => 'Capacity'],
        ['key' => 'status', 'label' => 'Status'],
    ];

    /**
     * The branch page's three lists, each paginated and sorted on its own:
     * the prefix keeps their `page`, `perPage`, `sort` and `direction` apart
     * in the URL, as on /usage (ListPaginator::param()).
     */
    private const string PROJECTS = 'projects';
    private const string STAYS = 'stays';
    private const string ACTIVITIES = 'activities';

    /** @var array<string, non-empty-list<string>> */
    private const array STAY_SORT_MAP = [
        'volunteer' => ['v.lastName', 'v.firstName'],
        'startDate' => ['s.startDate'],
        'endDate' => ['s.endDate'],
    ];

    /** @var list<array{key: string, label: string}> */
    private const array STAY_COLUMNS = [
        ['key' => 'volunteer', 'label' => 'Volunteer'],
        ['key' => 'startDate', 'label' => 'Arrives'],
        ['key' => 'endDate', 'label' => 'Leaves'],
    ];

    public function __construct(
        private readonly BranchRepository $branches,
        private readonly EntityManagerInterface $entityManager,
        private readonly ListPaginator $paginator,
        private readonly OccupancyLookAhead $occupancy,
        private readonly PeriodTotalsCalculator $periods,
        private readonly StayRepository $stays,
        private readonly ProjectRepository $projects,
        private readonly ActivityRepository $activities,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $pagination = $this->paginator->paginateQuery($this->listQueryBuilder($request), Branch::class, $request);

        /** @var list<Branch> $branchesOnPage */
        $branchesOnPage = iterator_to_array($pagination, false);
        $referenceCounts = $this->branches->countReferencesFor($branchesOnPage);

        $rows = [];
        foreach ($branchesOnPage as $branch) {
            $id = $branch->getId();
            $referencingCount = null === $id ? 0 : ($referenceCounts[$id] ?? 0);

            $rows[] = [
                'cells' => $this->cells($branch),
                'links' => ['name' => $this->generateUrl('branch_show', ['id' => $id])],
                'actions' => [
                    ['label' => 'View', 'url' => $this->generateUrl('branch_show', ['id' => $id])],
                    ['label' => 'Edit', 'url' => $this->generateUrl('branch_edit', ['id' => $id])],
                    $referencingCount > 0
                        ? ['label' => 'Delete', 'disabledReason' => $this->guardReason($branch, $referencingCount)]
                        : [
                            'label' => 'Delete',
                            'url' => $this->generateUrl('branch_delete', ['id' => $id]),
                            'method' => 'post',
                            'confirm' => sprintf('Delete %s?', $branch->getName()),
                            'csrfTokenId' => $this->csrfTokenId($branch),
                        ],
                ],
            ];
        }

        return $this->render('branch/index.html.twig', [
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'pagination' => $pagination,
            'sortState' => $this->paginator->sortState($request, self::SORT_MAP),
        ]);
    }

    #[Route('/export.{format}', name: 'export', requirements: ['format' => 'csv|xlsx'], defaults: ['format' => 'csv'], methods: ['GET'])]
    public function export(Request $request, string $format): StreamedResponse
    {
        return ListExport::response('branches', $format, self::COLUMNS, (function () use ($request): \Generator {
            /** @var Branch $branch */
            foreach ($this->listQueryBuilder($request)->getQuery()->toIterable() as $branch) {
                yield $this->cells($branch);
            }
        })());
    }

    /**
     * The one query behind both the index and its export.
     */
    private function listQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->branches->createOrderedByNameQueryBuilder();
        $this->paginator->applySort($queryBuilder, $request, self::SORT_MAP);

        return $queryBuilder;
    }

    /**
     * @return array<string, string>
     */
    private function cells(Branch $branch): array
    {
        return [
            'name' => $branch->getName(),
            'physicalLocation' => $branch->getPhysicalLocation(),
            'capacity' => null === $branch->getCapacity() ? '—' : (string) $branch->getCapacity(),
            'status' => $branch->isActive() ? 'Active' : 'Inactive',
        ];
    }

    /**
     * One branch at a glance: the home screen's occupancy chart (ADR 0049),
     * volunteers present per month, then its projects, who is on site or
     * arriving, and its activities — each list paginated and sortable like
     * the indexes, reusing their columns. Read-only.
     */
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Request $request, Branch $branch): Response
    {
        $today = new \DateTimeImmutable('today');
        $url = $this->generateUrl(...);

        $occupancy = array_first(array_filter(
            $this->occupancy->calculate($today),
            static fn(array $row): bool => $row['branch'] === $branch,
        ));

        // Newest first and running on to the last booked stay: drop the
        // future months, then the first twelve are the last twelve.
        $months = array_reverse(\array_slice(array_values(array_filter(
            $this->periods->calculate($today, $branch),
            static fn(array $row): bool => $row['period'] <= $today,
        )), 0, 12));

        $projectQuery = $this->projects->createOrderedByNameQueryBuilder()
            ->andWhere('p.branch = :branch')
            ->setParameter('branch', $branch);
        $this->paginator->applySort($projectQuery, $request, ProjectController::SORT_MAP, self::PROJECTS);
        $projectPagination = $this->paginator->paginateQuery($projectQuery, Project::class, $request, self::PROJECTS);
        /** @var list<Project> $projectsOnPage */
        $projectsOnPage = iterator_to_array($projectPagination, false);
        $lastActivities = $this->projects->findLastActivityDates($projectsOnPage);
        $projectRows = [];
        foreach ($projectsOnPage as $project) {
            $last = $lastActivities[(int) $project->getId()] ?? null;
            $projectRows[] = [
                'cells' => ProjectController::cells($project) + ['lastActivity' => $last?->format('j M Y') ?? 'Never visited'],
                'links' => ['name' => $url('project_show', ['id' => $project->getId()])],
                'badges' => null !== $last && $last > $today ? ['lastActivity' => 'Planned'] : [],
            ];
        }

        $stayQuery = $this->stays->createNotEndedAtBranchQueryBuilder($branch, $today);
        $this->paginator->applySort($stayQuery, $request, self::STAY_SORT_MAP, self::STAYS);
        $stayPagination = $this->paginator->paginateQuery($stayQuery, Stay::class, $request, self::STAYS);
        $stayRows = [];
        foreach ($stayPagination as $stay) {
            $start = $stay->getStartDate();
            $stayRows[] = [
                'cells' => [
                    'volunteer' => $stay->getVolunteer()?->getFullName() ?? '—',
                    'startDate' => $start?->format('D j M Y') ?? '—',
                    'endDate' => $stay->getEndDate()?->format('D j M Y') ?? '—',
                ],
                'links' => [
                    'volunteer' => $url('volunteer_show', ['id' => $stay->getVolunteer()?->getId()]),
                    'startDate' => $url('stay_show', ['id' => $stay->getId()]),
                ],
                'badges' => null !== $start && $start > $today ? ['startDate' => 'Arriving'] : ['startDate' => 'On site'],
            ];
        }

        $activityQuery = $this->activities->createOrderedByDateDescQueryBuilder(branch: $branch);
        $this->paginator->applySort($activityQuery, $request, ActivityController::SORT_MAP, self::ACTIVITIES);
        $activityPagination = $this->paginator->paginateQuery($activityQuery, Activity::class, $request, self::ACTIVITIES);
        $activityRows = [];
        foreach ($activityPagination as $activity) {
            $activityRows[] = ActivityController::linkedRow($activity, $today, $url);
        }

        return $this->render('branch/show.html.twig', [
            'branch' => $branch,
            'occupancy' => $occupancy,
            'occupancy_weeks' => OccupancyLookAhead::WEEKS,
            'months' => $months,
            'projectColumns' => [
                ...array_values(array_filter(ProjectController::COLUMNS, static fn(array $column): bool => 'branch' !== $column['key'])),
                ['key' => 'lastActivity', 'label' => 'Last activity'],
            ],
            'projectRows' => $projectRows,
            'projectPagination' => $projectPagination,
            'projectSortState' => $this->paginator->sortState($request, ProjectController::SORT_MAP, self::PROJECTS),
            'stayColumns' => self::STAY_COLUMNS,
            'stayRows' => $stayRows,
            'stayPagination' => $stayPagination,
            'staySortState' => $this->paginator->sortState($request, self::STAY_SORT_MAP, self::STAYS),
            'activityColumns' => ActivityController::COLUMNS,
            'activityRows' => $activityRows,
            'activityPagination' => $activityPagination,
            'activitySortState' => $this->paginator->sortState($request, ActivityController::SORT_MAP, self::ACTIVITIES),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $branch = new Branch();
        $form = $this->createForm(BranchFormType::class, $branch);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($branch);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was added.', $branch->getName()));

            return $this->redirectToRoute('branch_index');
        }

        return $this->render('branch/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Branch $branch): Response
    {
        $form = $this->createForm(BranchFormType::class, $branch);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $branch->touch();
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was updated.', $branch->getName()));

            return $this->redirectToRoute('branch_index');
        }

        $referencingCount = $this->branches->countReferences($branch);

        return $this->render('branch/edit.html.twig', [
            'form' => $form,
            'branch' => $branch,
            'deleteGuardReason' => $referencingCount > 0
                ? $this->guardReason($branch, $referencingCount)
                : null,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Branch $branch): Response
    {
        $token = $request->request->all()['_token'] ?? null;
        if (!\is_string($token) || !$this->isCsrfTokenValid($this->csrfTokenId($branch), $token)) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $this->redirectToRoute('branch_index');
        }

        $referencingCount = $this->branches->countReferences($branch);
        if ($referencingCount > 0) {
            $this->addFlash('error', $this->guardReason($branch, $referencingCount));

            return $this->redirectToRoute('branch_index');
        }

        $this->entityManager->remove($branch);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s was deleted.', $branch->getName()));

        return $this->redirectToRoute('branch_index');
    }

    /**
     * Shared by the index's greyed-out Delete, the edit screen's note and the
     * refusal in delete(), so the three can't drift apart.
     */
    private function guardReason(Branch $branch, int $referencingCount): string
    {
        return sprintf(
            'Cannot delete %s — %d stay%s or project%s point%s to it. Mark it inactive instead.',
            $branch->getName(),
            $referencingCount,
            1 === $referencingCount ? '' : 's',
            1 === $referencingCount ? '' : 's',
            1 === $referencingCount ? 's' : '',
        );
    }

    private function csrfTokenId(Branch $branch): string
    {
        return 'delete-branch-' . $branch->getId();
    }
}
