<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Form\ProjectFormType;
use App\Pagination\ListPaginator;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/projects', name: 'project_')]
final class ProjectController extends AbstractController
{
    /**
     * Column key => DQL field(s) for the index's sortable headers; the map is
     * the whitelist. `location` and `ownership` sort by the enum's stored
     * backing value, not its label() — which happens to give the same order
     * for both enums today (kibera < mombasa, partner < ucesco). A future case
     * whose backing value and label disagree would need its own column.
     * See ADR 0011.
     *
     * @var array<string, non-empty-list<string>>
     */
    private const array SORT_MAP = [
        'name' => ['p.name'],
        'location' => ['p.location'],
        'ownership' => ['p.ownership'],
        'status' => ['p.isActive'],
    ];

    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly ListPaginator $paginator,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $queryBuilder = $this->projects->createOrderedByNameQueryBuilder();
        $this->paginator->applySort($queryBuilder, $request, self::SORT_MAP);

        $pagination = $this->paginator->paginateQuery($queryBuilder, Project::class, $request);

        /** @var list<Project> $projectsOnPage */
        $projectsOnPage = iterator_to_array($pagination, false);
        // One query for the whole page. The delete-guard's own count stays
        // per-entity in delete() — this is the same rule read ahead of time so
        // the index can show Delete as unavailable rather than let the reader
        // discover it from a flash after confirming.
        $activityCounts = $this->projects->countReferencingActivitiesFor($projectsOnPage);

        $rows = [];
        $guardedCount = 0;
        foreach ($projectsOnPage as $project) {
            $id = $project->getId();
            $referencingCount = null === $id ? 0 : ($activityCounts[$id] ?? 0);
            if ($referencingCount > 0) {
                ++$guardedCount;
            }

            $rows[] = [
                'cells' => [
                    'name' => $project->getName(),
                    'location' => $project->getLocation()?->label() ?? '—',
                    'ownership' => $project->getOwnership()?->label() ?? '—',
                    'status' => $project->isActive() ? 'Active' : 'Inactive',
                ],
                'actions' => [
                    ['label' => 'Edit', 'url' => $this->generateUrl('project_edit', ['id' => $id])],
                    $referencingCount > 0
                        ? ['label' => 'Delete', 'disabledReason' => $this->guardReason($project, $referencingCount)]
                        : [
                            'label' => 'Delete',
                            'url' => $this->generateUrl('project_delete', ['id' => $id]),
                            'method' => 'post',
                            'confirm' => sprintf('Delete %s?', $project->getName()),
                            'csrfToken' => $this->csrfToken($project),
                        ],
                ],
            ];
        }

        return $this->render('project/index.html.twig', [
            'columns' => [
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'location', 'label' => 'Location'],
                ['key' => 'ownership', 'label' => 'Ownership'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $rows,
            'pagination' => $pagination,
            'sortState' => $this->paginator->sortState($request, self::SORT_MAP),
            'guardedCount' => $guardedCount,
        ]);
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

        if ($form->isSubmitted() && $form->isValid()) {
            $project->touch();
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was updated.', $project->getName()));

            return $this->redirectToRoute('project_index');
        }

        return $this->render('project/edit.html.twig', ['form' => $form, 'project' => $project]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Project $project): Response
    {
        if (!$this->isCsrfTokenValid($this->csrfTokenId($project), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $this->redirectToRoute('project_index');
        }

        $referencingCount = $this->projects->countReferencingActivities($project);
        if ($referencingCount > 0) {
            $this->addFlash('error', $this->guardReason($project, $referencingCount));

            return $this->redirectToRoute('project_index');
        }

        $this->entityManager->remove($project);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s was deleted.', $project->getName()));

        return $this->redirectToRoute('project_index');
    }

    /**
     * Why this project can't be deleted, in one sentence. Shared by the
     * index's greyed-out Delete and the flash raised if a delete is attempted
     * anyway, so the warning and the refusal can't drift apart.
     */
    private function guardReason(Project $project, int $referencingCount): string
    {
        return sprintf(
            'Cannot delete %s — %d activit%s reference%s it. Mark it inactive instead.',
            $project->getName(),
            $referencingCount,
            1 === $referencingCount ? 'y' : 'ies',
            1 === $referencingCount ? 's' : '',
        );
    }

    private function csrfTokenId(Project $project): string
    {
        return 'delete-project-' . $project->getId();
    }

    private function csrfToken(Project $project): string
    {
        return $this->csrfTokenManager->getToken($this->csrfTokenId($project))->getValue();
    }
}
