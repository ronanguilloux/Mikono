<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Export\ListExport;
use App\Form\ProjectFormType;
use App\Pagination\ListPaginator;
use App\Repository\ProgramRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects', name: 'project_')]
final class ProjectController extends AbstractController
{
    /**
     * Column key => DQL field(s) for the index's sortable headers; the map is
     * the whitelist. `branch` sorts by name, through the join the index query
     * carries. `ownership` sorts by the enum's stored backing value, not its
     * label() — the same order today (partner < ucesco). A future case whose
     * backing value and label disagree would need its own column.
     * See ADR 0011.
     *
     * @var array<string, non-empty-list<string>>
     */
    private const array SORT_MAP = [
        'name' => ['p.name'],
        'branch' => ['b.name'],
        'ownership' => ['p.ownership'],
        'status' => ['p.isActive'],
    ];

    /** @var list<array{key: string, label: string}> */
    private const array COLUMNS = [
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'branch', 'label' => 'Branch'],
        ['key' => 'ownership', 'label' => 'Ownership'],
        ['key' => 'status', 'label' => 'Status'],
    ];

    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ProgramRepository $programs,
        private readonly EntityManagerInterface $entityManager,
        private readonly ListPaginator $paginator,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $pagination = $this->paginator->paginateQuery($this->listQueryBuilder($request), Project::class, $request);

        /** @var list<Project> $projectsOnPage */
        $projectsOnPage = iterator_to_array($pagination, false);
        // One query for the whole page. The delete-guard's own count stays
        // per-entity in delete() — this is the same rule read ahead of time so
        // the index can show Delete as unavailable rather than let the reader
        // discover it from a flash after confirming.
        $activityCounts = $this->projects->countReferencingActivitiesFor($projectsOnPage);
        $programCounts = $this->programs->countForProjects($projectsOnPage);

        $rows = [];
        foreach ($projectsOnPage as $project) {
            $id = $project->getId();
            $guardReason = null === $id ? null : $this->guardReason($project, $activityCounts[$id] ?? 0, $programCounts[$id] ?? 0);

            $rows[] = [
                'cells' => $this->cells($project),
                'actions' => [
                    ['label' => 'Edit', 'url' => $this->generateUrl('project_edit', ['id' => $id])],
                    null !== $guardReason
                        ? ['label' => 'Delete', 'disabledReason' => $guardReason]
                        : [
                            'label' => 'Delete',
                            'url' => $this->generateUrl('project_delete', ['id' => $id]),
                            'method' => 'post',
                            'confirm' => sprintf('Delete %s?', $project->getName()),
                            'csrfTokenId' => $this->csrfTokenId($project),
                        ],
                ],
            ];
        }

        return $this->render('project/index.html.twig', [
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'pagination' => $pagination,
            'sortState' => $this->paginator->sortState($request, self::SORT_MAP),
        ]);
    }

    #[Route('/export.{format}', name: 'export', requirements: ['format' => 'csv|xlsx'], defaults: ['format' => 'csv'], methods: ['GET'])]
    public function export(Request $request, string $format): StreamedResponse
    {
        return ListExport::response('projects', $format, self::COLUMNS, (function () use ($request): \Generator {
            /** @var Project $project */
            foreach ($this->listQueryBuilder($request)->getQuery()->toIterable() as $project) {
                yield $this->cells($project);
            }
        })());
    }

    /**
     * The one query behind both the index and its export.
     */
    private function listQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->projects->createOrderedByNameQueryBuilder();
        $this->paginator->applySort($queryBuilder, $request, self::SORT_MAP);

        return $queryBuilder;
    }

    /**
     * @return array<string, string>
     */
    private function cells(Project $project): array
    {
        return [
            'name' => $project->getName(),
            'branch' => $project->getBranch()?->getName() ?? '—',
            'ownership' => $project->getOwnership()?->label() ?? '—',
            'status' => $project->isActive() ? 'Active' : 'Inactive',
        ];
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $project = new Project();
        $form = $this->createForm(ProjectFormType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($project);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was added.', $project->getName()));

            return $this->redirectToRoute('project_index');
        }

        return $this->render('project/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Project $project): Response
    {
        $form = $this->createForm(ProjectFormType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->keepsItsActivitiesAtItsBranch($form, $project)) {
            $project->touch();
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was updated.', $project->getName()));

            return $this->redirectToRoute('project_index');
        }

        // Said here rather than on the index: a reader who wants to delete one
        // project is on that project's screen, and the list already renders
        // Delete inert on the rows this would block.
        return $this->render('project/edit.html.twig', [
            'form' => $form,
            'project' => $project,
            'deleteGuardReason' => $this->currentGuardReason($project),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Project $project): Response
    {
        if (!$this->isCsrfTokenValid($this->csrfTokenId($project), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $this->redirectToRoute('project_index');
        }

        $guardReason = $this->currentGuardReason($project);
        if (null !== $guardReason) {
            $this->addFlash('error', $guardReason);

            return $this->redirectToRoute('project_index');
        }

        $this->entityManager->remove($project);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s was deleted.', $project->getName()));

        return $this->redirectToRoute('project_index');
    }

    /**
     * Why this project can't be deleted, in one sentence, or null if it can.
     * Shared by the index's greyed-out Delete, the note on the edit screen,
     * and the flash raised if a delete is attempted anyway, so the warning
     * and the refusal can't drift apart. Programs count too: deleting the
     * project would orphan them (ADR 0030).
     */
    private function guardReason(Project $project, int $activityCount, int $programCount): ?string
    {
        if ($activityCount > 0) {
            return sprintf(
                'Cannot delete %s — %d activit%s reference%s it. Mark it inactive instead.',
                $project->getName(),
                $activityCount,
                1 === $activityCount ? 'y' : 'ies',
                1 === $activityCount ? 's' : '',
            );
        }

        if ($programCount > 0) {
            return sprintf(
                'Cannot delete %s — it has %d program%s. Delete %s first, or mark the project inactive.',
                $project->getName(),
                $programCount,
                1 === $programCount ? '' : 's',
                1 === $programCount ? 'it' : 'them',
            );
        }

        return null;
    }

    private function currentGuardReason(Project $project): ?string
    {
        return $this->guardReason(
            $project,
            $this->projects->countReferencingActivities($project),
            $this->programs->countForProject($project),
        );
    }

    /**
     * Every activity logged at a project belongs to a stay at the project's
     * branch (ADR 0027), so moving a project to another branch while its
     * activities' stays are elsewhere is refused. The error makes the form
     * invalid, so render() answers 422.
     *
     * @param FormInterface<mixed> $form
     */
    private function keepsItsActivitiesAtItsBranch(FormInterface $form, Project $project): bool
    {
        $elsewhere = $this->projects->countActivitiesAtOtherBranch($project);
        if (0 === $elsewhere) {
            return true;
        }

        $form->get('branch')->addError(new FormError(sprintf(
            '%d activit%s logged here belong%s to stays at another branch.',
            $elsewhere,
            1 === $elsewhere ? 'y' : 'ies',
            1 === $elsewhere ? 's' : '',
        )));

        return false;
    }

    private function csrfTokenId(Project $project): string
    {
        return 'delete-project-' . $project->getId();
    }
}
