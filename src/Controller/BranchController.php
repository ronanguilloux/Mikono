<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Branch;
use App\Form\BranchFormType;
use App\Pagination\ListPaginator;
use App\Repository\BranchRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Same shape as ProjectController, minus the delete-guard: nothing references
 * a branch yet. The first relation brings the guard with it.
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

    public function __construct(
        private readonly BranchRepository $branches,
        private readonly EntityManagerInterface $entityManager,
        private readonly ListPaginator $paginator,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $queryBuilder = $this->branches->createOrderedByNameQueryBuilder();
        $this->paginator->applySort($queryBuilder, $request, self::SORT_MAP);

        $pagination = $this->paginator->paginateQuery($queryBuilder, Branch::class, $request);

        $rows = [];
        /** @var Branch $branch */
        foreach ($pagination as $branch) {
            $rows[] = [
                'cells' => [
                    'name' => $branch->getName(),
                    'physicalLocation' => $branch->getPhysicalLocation(),
                    'status' => $branch->isActive() ? 'Active' : 'Inactive',
                ],
                'actions' => [
                    ['label' => 'Edit', 'url' => $this->generateUrl('branch_edit', ['id' => $branch->getId()])],
                    [
                        'label' => 'Delete',
                        'url' => $this->generateUrl('branch_delete', ['id' => $branch->getId()]),
                        'method' => 'post',
                        'confirm' => sprintf('Delete %s?', $branch->getName()),
                        'csrfTokenId' => $this->csrfTokenId($branch),
                    ],
                ],
            ];
        }

        return $this->render('branch/index.html.twig', [
            'columns' => [
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'physicalLocation', 'label' => 'Physical location'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $rows,
            'pagination' => $pagination,
            'sortState' => $this->paginator->sortState($request, self::SORT_MAP),
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

        return $this->render('branch/edit.html.twig', ['form' => $form, 'branch' => $branch]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Branch $branch): Response
    {
        $token = $request->request->all()['_token'] ?? null;
        if (!\is_string($token) || !$this->isCsrfTokenValid($this->csrfTokenId($branch), $token)) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $this->redirectToRoute('branch_index');
        }

        $this->entityManager->remove($branch);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s was deleted.', $branch->getName()));

        return $this->redirectToRoute('branch_index');
    }

    private function csrfTokenId(Branch $branch): string
    {
        return 'delete-branch-' . $branch->getId();
    }
}
