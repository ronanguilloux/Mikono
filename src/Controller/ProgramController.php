<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityType;
use App\Entity\BeneficiaryGroup;
use App\Entity\Branch;
use App\Entity\Program;
use App\Entity\Project;
use App\Entity\Skill;
use App\Export\ListExport;
use App\Form\ProgramFormType;
use App\Pagination\ListPaginator;
use App\Report\ActivitySummaryCalculator;
use App\Repository\ActivityRepository;
use App\Repository\ProgramRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/programs', name: 'program_')]
final class ProgramController extends AbstractController
{
    /**
     * Column key => DQL field(s); the map is the whitelist. Undated bounds
     * sort first in ascending order (SQLite puts NULL first). See ADR 0011.
     *
     * @var array<string, non-empty-list<string>>
     */
    public const array SORT_MAP = [
        'name' => ['prg.name'],
        'project' => ['p.name'],
        'branch' => ['b.name'],
        'dates' => ['prg.startDate', 'prg.endDate'],
    ];

    /** @var list<array{key: string, label: string}> */
    public const array COLUMNS = [
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'project', 'label' => 'Project'],
        ['key' => 'branch', 'label' => 'Branch'],
        ['key' => 'dates', 'label' => 'Dates'],
        ['key' => 'volunteersNeeded', 'label' => 'Volunteers needed'],
        ['key' => 'activityTypes', 'label' => 'Activity types'],
        ['key' => 'skills', 'label' => 'Recommended Skills'],
        ['key' => 'beneficiaryGroups', 'label' => 'Beneficiary groups'],
    ];

    /**
     * The program page's volunteers list: an in-memory breakdown, so column
     * key => array key, sorted by ListPaginator::sortArray() (ADR 0011).
     *
     * @var array<string, string>
     */
    private const array VOLUNTEER_SORT_MAP = [
        'label' => 'label',
        'count' => 'count',
        'totalDays' => 'totalDays',
        'mostRecent' => 'mostRecent',
    ];

    /** @var list<array{key: string, label: string}> */
    private const array VOLUNTEER_COLUMNS = [
        ['key' => 'label', 'label' => 'Volunteer'],
        ['key' => 'count', 'label' => 'Activities'],
        ['key' => 'totalDays', 'label' => 'Total days'],
        ['key' => 'mostRecent', 'label' => 'Most recent'],
    ];

    /** @var list<array{key: string, label: string}> */
    private const array ACTIVITY_TYPE_COLUMNS = [
        ['key' => 'label', 'label' => 'Activity type'],
        ['key' => 'count', 'label' => 'Activities'],
        ['key' => 'totalDays', 'label' => 'Total days'],
        ['key' => 'volunteers', 'label' => 'Volunteers'],
        ['key' => 'mostRecent', 'label' => 'Most recent'],
    ];

    /**
     * The program page's two paginated lists, kept apart in the URL as on
     * the project page (ListPaginator::param()).
     */
    private const string VOLUNTEERS = 'volunteers';
    private const string ACTIVITIES = 'activities';

    public function __construct(
        private readonly ProgramRepository $programs,
        private readonly EntityManagerInterface $entityManager,
        private readonly ListPaginator $paginator,
        private readonly ActivityRepository $activities,
        private readonly ActivitySummaryCalculator $summaries,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $pagination = $this->paginator->paginateQuery($this->listQueryBuilder($request), Program::class, $request);

        /** @var list<Program> $programsOnPage */
        $programsOnPage = iterator_to_array($pagination, false);
        // One query for the page; delete() re-counts per program regardless.
        $activityCounts = $this->programs->countActivitiesFor($programsOnPage);

        $rows = [];
        foreach ($programsOnPage as $program) {
            $id = $program->getId();
            $activityCount = null === $id ? 0 : ($activityCounts[$id] ?? 0);
            $rows[] = [
                'cells' => self::cells($program),
                'links' => ['name' => $this->generateUrl('program_show', ['id' => $id])],
                'actions' => [
                    ['label' => 'View', 'url' => $this->generateUrl('program_show', ['id' => $id])],
                    ['label' => 'Matches', 'url' => $this->generateUrl('match_index', ['program' => $id])],
                    ['label' => 'Edit', 'url' => $this->generateUrl('program_edit', ['id' => $id])],
                    $activityCount > 0
                        ? ['label' => 'Delete', 'disabledReason' => $this->guardReason($program, $activityCount)]
                        : [
                            'label' => 'Delete',
                            'url' => $this->generateUrl('program_delete', ['id' => $id]),
                            'method' => 'post',
                            'confirm' => sprintf('Delete %s?', $program->getName()),
                            'csrfTokenId' => $this->csrfTokenId($program),
                        ],
                ],
            ];
        }

        return $this->render('program/index.html.twig', [
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'pagination' => $pagination,
            'sortState' => $this->paginator->sortState($request, self::SORT_MAP),
            'filters' => $this->requestedFilters($request),
            // Everything, inactive and unused included: this reads history.
            'options' => [
                'project' => $this->entityManager->getRepository(Project::class)->findBy([], ['name' => 'ASC']),
                'branch' => $this->entityManager->getRepository(Branch::class)->findBy([], ['name' => 'ASC']),
                'activityType' => $this->entityManager->getRepository(ActivityType::class)->findBy([], ['name' => 'ASC']),
                'skill' => $this->entityManager->getRepository(Skill::class)->findBy([], ['name' => 'ASC']),
                'group' => $this->entityManager->getRepository(BeneficiaryGroup::class)->findBy([], ['name' => 'ASC']),
            ],
        ]);
    }

    #[Route('/export.{format}', name: 'export', requirements: ['format' => 'csv|xlsx'], defaults: ['format' => 'csv'], methods: ['GET'])]
    public function export(Request $request, string $format): StreamedResponse
    {
        return ListExport::response('programs', $format, self::COLUMNS, (function () use ($request): \Generator {
            /** @var Program $program */
            foreach ($this->listQueryBuilder($request)->getQuery()->toIterable() as $program) {
                yield self::cells($program);
            }
        })());
    }

    /**
     * The one query behind both the index and its export.
     */
    private function listQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->programs->createOrderedQueryBuilder(...$this->requestedFilters($request));
        $this->paginator->applySort($queryBuilder, $request, self::SORT_MAP);

        return $queryBuilder;
    }

    /**
     * One filter per column, keyed by createOrderedQueryBuilder()'s argument
     * names and read the ADR 0023 way: anything malformed, blank or unknown is
     * null, meaning no filter. The query-string keys are the same, bar `q`.
     *
     * @return array{search: ?string, project: ?Project, branch: ?Branch, period: ?string, activityType: ?ActivityType, skill: ?Skill, group: ?BeneficiaryGroup}
     */
    private function requestedFilters(Request $request): array
    {
        $query = $request->query->all();
        $search = is_scalar($query['q'] ?? null) ? trim((string) $query['q']) : '';
        $period = $query['period'] ?? null;

        return [
            'search' => '' === $search ? null : $search,
            'project' => $this->requestedEntity($query, 'project', Project::class),
            'branch' => $this->requestedEntity($query, 'branch', Branch::class),
            'period' => in_array($period, ['current', 'upcoming', 'ended'], true) ? $period : null,
            'activityType' => $this->requestedEntity($query, 'activityType', ActivityType::class),
            'skill' => $this->requestedEntity($query, 'skill', Skill::class),
            'group' => $this->requestedEntity($query, 'group', BeneficiaryGroup::class),
        ];
    }

    /**
     * @template T of object
     *
     * @param array<string, mixed> $query
     * @param class-string<T>      $class
     *
     * @return T|null
     */
    private function requestedEntity(array $query, string $key, string $class): ?object
    {
        $raw = $query[$key] ?? null;

        return is_scalar($raw) && (int) $raw >= 1 ? $this->entityManager->find($class, (int) $raw) : null;
    }

    /**
     * @return array<string, string>
     */
    public static function cells(Program $program): array
    {
        return [
            'name' => $program->getName(),
            'project' => $program->getProject()?->getName() ?? '—',
            'branch' => $program->getProject()?->getBranch()?->getName() ?? '—',
            'dates' => self::dates($program),
            'volunteersNeeded' => null === $program->getVolunteersNeeded() ? '—' : (string) $program->getVolunteersNeeded(),
            'activityTypes' => implode(', ', array_map(
                static fn(ActivityType $type) => $type->getName(),
                $program->getActivityTypes()->toArray(),
            )),
            'skills' => $program->getSkills()->isEmpty() ? '—' : self::skillNames($program->getSkills()->toArray()),
            'beneficiaryGroups' => $program->getBeneficiaryGroups()->isEmpty() ? '—' : implode(', ', $program->getBeneficiaryGroups()->map(
                static fn(BeneficiaryGroup $group): string => $group->getName(),
            )->toArray()),
        ];
    }

    /**
     * @param array<Skill> $skills
     */
    private static function skillNames(array $skills): string
    {
        $names = array_map(static fn(Skill $skill): string => $skill->getName(), $skills);
        sort($names);

        return implode(', ', $names);
    }

    private static function dates(Program $program): string
    {
        $start = $program->getStartDate()?->format('d/m/Y');
        $end = $program->getEndDate()?->format('d/m/Y');

        return match (true) {
            null === $start && null === $end => 'Always on',
            null === $end => 'From ' . $start,
            null === $start => 'Until ' . $end,
            default => $start . ' – ' . $end,
        };
    }

    /**
     * One program at a glance, the twin of the project page: what it offers
     * and needs, its totals, volunteers per month, then who worked on it and
     * its activities — each list paginated and sortable on its own. Read-only.
     */
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Request $request, Program $program): Response
    {
        $today = new \DateTimeImmutable('today');
        $url = $this->generateUrl(...);

        // Whole list, in the calculator's total-days order: the totals are
        // read off it before it is sorted and paged.
        $byVolunteer = $this->summaries->summarizeByVolunteer($program);
        $lastActivity = [] === $byVolunteer ? null : max(array_column($byVolunteer, 'mostRecent'));
        $volunteerPagination = $this->paginator->paginateArray(
            $this->paginator->sortArray($byVolunteer, $request, self::VOLUNTEER_SORT_MAP, self::VOLUNTEERS),
            $request,
            self::VOLUNTEERS,
        );
        $volunteerRows = [];
        foreach ($volunteerPagination as $summary) {
            $volunteerRows[] = self::summaryRow($summary, $today) + [
                'links' => null === $summary['id'] ? [] : ['label' => $url('volunteer_show', ['id' => $summary['id']])],
            ];
        }

        $typeRows = array_map(
            static fn(array $summary): array => self::summaryRow($summary, $today) + ['links' => []],
            $this->summaries->summarizeByActivityType($program),
        );

        $activityQuery = $this->activities->createOrderedByDateDescQueryBuilder(program: $program);
        $this->paginator->applySort($activityQuery, $request, ActivityController::SORT_MAP, self::ACTIVITIES);
        $activityPagination = $this->paginator->paginateQuery($activityQuery, Activity::class, $request, self::ACTIVITIES);
        $activityRows = [];
        foreach ($activityPagination as $activity) {
            $activityRows[] = ActivityController::linkedRow($activity, $today, $url);
        }

        return $this->render('program/show.html.twig', [
            'program' => $program,
            'period' => match (true) {
                null === $program->getStartDate() && null === $program->getEndDate() => 'Always on',
                $program->covers($today) => 'Current',
                null !== $program->getEndDate() && $program->getEndDate() < $today => 'Ended',
                default => 'Upcoming',
            },
            'dates' => self::dates($program),
            'booked' => $this->programs->countBookedVolunteers([$program], $today)[(int) $program->getId()] ?? 0,
            'totals' => [
                'activities' => array_sum(array_column($byVolunteer, 'count')),
                'volunteers' => \count($byVolunteer),
                'days' => array_sum(array_column($byVolunteer, 'totalDays')),
                'last' => $lastActivity,
            ],
            'today' => $today,
            'months' => ProjectController::volunteersPerMonth($this->activities->findVolunteerDates(program: $program), $today),
            'typeColumns' => self::ACTIVITY_TYPE_COLUMNS,
            'typeRows' => $typeRows,
            'volunteerColumns' => self::VOLUNTEER_COLUMNS,
            'volunteerRows' => $volunteerRows,
            'volunteerPagination' => $volunteerPagination,
            'volunteerSortState' => $this->paginator->sortState($request, self::VOLUNTEER_SORT_MAP, self::VOLUNTEERS),
            'activityColumns' => ProjectController::withoutColumns(ActivityController::COLUMNS, 'project', 'program'),
            'activityRows' => $activityRows,
            'activityPagination' => $activityPagination,
            'activitySortState' => $this->paginator->sortState($request, ActivityController::SORT_MAP, self::ACTIVITIES),
        ]);
    }

    /**
     * A breakdown row as /reports/volunteers draws it: one decimal for days,
     * a future "Most recent" tagged Planned.
     *
     * @param array{count: int, totalDays: float, mostRecent: ?\DateTimeImmutable, label: string, volunteers?: int} $summary
     *
     * @return array{cells: array<string, string>, badges: array<string, string>}
     */
    private static function summaryRow(array $summary, \DateTimeImmutable $today): array
    {
        $mostRecent = $summary['mostRecent'];

        return [
            'cells' => [
                'label' => $summary['label'],
                'count' => (string) $summary['count'],
                'totalDays' => number_format($summary['totalDays'], 1),
                'volunteers' => (string) ($summary['volunteers'] ?? ''),
                'mostRecent' => $mostRecent?->format('j M Y') ?? '—',
            ],
            'badges' => null !== $mostRecent && $mostRecent > $today ? ['mostRecent' => 'Planned'] : [],
        ];
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $program = new Program();
        $form = $this->createForm(ProgramFormType::class, $program);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($program);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was added.', $program->getName()));

            return $this->redirectToRoute('program_index');
        }

        return $this->render('program/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Program $program): Response
    {
        $originalProject = $program->getProject();
        $form = $this->createForm(ProgramFormType::class, $program);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->keepsItsActivities($form, $program, $originalProject)) {
            $program->touch();
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was updated.', $program->getName()));

            return $this->redirectToRoute('program_index');
        }

        $activityCount = $this->programs->countActivities($program);

        return $this->render('program/edit.html.twig', [
            'form' => $form,
            'program' => $program,
            'deleteGuardReason' => $activityCount > 0 ? $this->guardReason($program, $activityCount) : null,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Program $program): Response
    {
        if (!$this->isCsrfTokenValid($this->csrfTokenId($program), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $this->redirectToRoute('program_index');
        }

        $activityCount = $this->programs->countActivities($program);
        if ($activityCount > 0) {
            $this->addFlash('error', $this->guardReason($program, $activityCount));

            return $this->redirectToRoute('program_index');
        }

        $this->entityManager->remove($program);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s was deleted.', $program->getName()));

        return $this->redirectToRoute('program_index');
    }

    /**
     * Shared by the index's inert Delete, the edit screen's note and the
     * flash, so the three can't drift apart.
     */
    private function guardReason(Program $program, int $activityCount): string
    {
        return sprintf(
            'Cannot delete %s — %d activit%s belong%s to it.',
            $program->getName(),
            $activityCount,
            1 === $activityCount ? 'y' : 'ies',
            1 === $activityCount ? 's' : '',
        );
    }

    /**
     * An edit may not strand the program's activities (ADR 0030): not by
     * moving it to another project, not by dates that leave some outside,
     * not by dropping a type they use. Each error lands on its field, and
     * render() then answers 422.
     *
     * @param FormInterface<Program> $form
     */
    private function keepsItsActivities(FormInterface $form, Program $program, ?Project $originalProject): bool
    {
        $kept = true;

        if ($program->getProject() !== $originalProject && $this->programs->countActivities($program) > 0) {
            $form->get('project')->addError(new FormError('This program has activities, so it stays at its project.'));
            $kept = false;
        }

        $outside = $this->programs->countActivitiesOutsideItsDates($program);
        if ($outside > 0) {
            $form->get('startDate')->addError(new FormError(sprintf(
                '%d activit%s of this program would fall outside these dates.',
                $outside,
                1 === $outside ? 'y' : 'ies',
            )));
            $kept = false;
        }

        $dropped = $this->programs->findUsedActivityTypesNoLongerOffered($program);
        if ([] !== $dropped) {
            $form->get('activityTypes')->addError(new FormError(sprintf(
                'Activities of this program use %s, so %s stay%s.',
                implode(', ', array_map(static fn(ActivityType $type) => $type->getName(), $dropped)),
                1 === count($dropped) ? 'it' : 'they',
                1 === count($dropped) ? 's' : '',
            )));
            $kept = false;
        }

        return $kept;
    }

    private function csrfTokenId(Program $program): string
    {
        return 'delete-program-' . $program->getId();
    }
}
