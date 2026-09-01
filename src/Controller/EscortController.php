<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Escort;
use App\Form\EscortFormType;
use App\Pagination\ListPaginator;
use App\Repository\EscortRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/escorts', name: 'escort_')]
final class EscortController extends AbstractController
{
    public function __construct(
        private readonly EscortRepository $escorts,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly ListPaginator $paginator,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $pagination = $this->paginator->paginateQuery(
            $this->escorts->createOrderedByNameQueryBuilder(),
            Escort::class,
            $request,
        );

        $rows = [];
        foreach ($pagination as $escort) {
            $rows[] = [
                'cells' => [
                    'name' => $escort->getName(),
                    'status' => $escort->isActive() ? 'Active' : 'Inactive',
                ],
                'actions' => [
                    ['label' => 'Edit', 'url' => $this->generateUrl('escort_edit', ['id' => $escort->getId()])],
                    [
                        'label' => 'Delete',
                        'url' => $this->generateUrl('escort_delete', ['id' => $escort->getId()]),
                        'method' => 'post',
                        'confirm' => sprintf('Delete %s?', $escort->getName()),
                        'csrfToken' => $this->csrfToken($escort),
                    ],
                ],
            ];
        }

        return $this->render('escort/index.html.twig', [
            'columns' => [
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $rows,
            'pagination' => $pagination,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $escort = new Escort();
        $form = $this->createForm(EscortFormType::class, $escort);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($escort);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was added.', $escort->getName()));

            return $this->redirectToRoute('escort_index');
        }

        return $this->render('escort/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Escort $escort): Response
    {
        $form = $this->createForm(EscortFormType::class, $escort);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was updated.', $escort->getName()));

            return $this->redirectToRoute('escort_index');
        }

        return $this->render('escort/edit.html.twig', ['form' => $form, 'escort' => $escort]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Escort $escort): Response
    {
        if (!$this->isCsrfTokenValid($this->csrfTokenId($escort), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $this->redirectToRoute('escort_index');
        }

        $referencingCount = $this->escorts->countReferencingActivities($escort);
        if ($referencingCount > 0) {
            $this->addFlash('error', sprintf(
                'Cannot delete %s — %d activit%s use%s it.',
                $escort->getName(),
                $referencingCount,
                1 === $referencingCount ? 'y' : 'ies',
                1 === $referencingCount ? 's' : '',
            ));

            return $this->redirectToRoute('escort_index');
        }

        $this->entityManager->remove($escort);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s was deleted.', $escort->getName()));

        return $this->redirectToRoute('escort_index');
    }

    private function csrfTokenId(Escort $escort): string
    {
        return 'delete-escort-' . $escort->getId();
    }

    private function csrfToken(Escort $escort): string
    {
        return $this->csrfTokenManager->getToken($this->csrfTokenId($escort))->getValue();
    }
}
