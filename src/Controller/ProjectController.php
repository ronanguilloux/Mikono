<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Form\ProjectFormType;
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
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $rows = [];
        foreach ($this->projects->findAllOrderedByName() as $project) {
            $rows[] = [
                'cells' => [
                    'name' => $project->getName(),
                    'location' => $project->getLocation()?->label() ?? '—',
                    'ownership' => $project->getOwnership()?->label() ?? '—',
                    'status' => $project->isActive() ? 'Active' : 'Inactive',
                ],
                'actions' => [
                    ['label' => 'Edit', 'url' => $this->generateUrl('project_edit', ['id' => $project->getId()])],
                    [
                        'label' => 'Delete',
                        'url' => $this->generateUrl('project_delete', ['id' => $project->getId()]),
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
            $this->addFlash('error', sprintf(
                'Cannot delete %s — %d activit%s reference%s it. Mark it inactive instead.',
                $project->getName(),
                $referencingCount,
                1 === $referencingCount ? 'y' : 'ies',
                1 === $referencingCount ? 's' : '',
            ));

            return $this->redirectToRoute('project_index');
        }

        $this->entityManager->remove($project);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s was deleted.', $project->getName()));

        return $this->redirectToRoute('project_index');
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
