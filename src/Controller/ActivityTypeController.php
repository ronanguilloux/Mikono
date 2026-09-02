<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ActivityType;
use App\Form\ActivityTypeFormType;
use App\Pagination\ListPaginator;
use App\Repository\ActivityTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/activity-types', name: 'activity_type_')]
final class ActivityTypeController extends AbstractController
{
    /**
     * Column key => DQL field(s) for the index's sortable headers; the map is
     * the whitelist. Description is absent on purpose — it's free text, and
     * ordering it surfaces nothing anyone is looking for. Being out of the map
     * is the whole opt-out: DataTable renders it as a plain header. See ADR 0011.
     *
     * @var array<string, non-empty-list<string>>
     */
    private const array SORT_MAP = [
        'name' => ['t.name'],
    ];

    public function __construct(
        private readonly ActivityTypeRepository $activityTypes,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly ListPaginator $paginator,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $queryBuilder = $this->activityTypes->createOrderedByNameQueryBuilder();
        $this->paginator->applySort($queryBuilder, $request, self::SORT_MAP);

        $pagination = $this->paginator->paginateQuery($queryBuilder, ActivityType::class, $request);

        $rows = [];
        foreach ($pagination as $activityType) {
            $rows[] = [
                'cells' => [
                    'name' => $activityType->getName(),
                    'description' => $activityType->getDescription() ?? '—',
                ],
                'actions' => [
                    ['label' => 'Edit', 'url' => $this->generateUrl('activity_type_edit', ['id' => $activityType->getId()])],
                    [
                        'label' => 'Delete',
                        'url' => $this->generateUrl('activity_type_delete', ['id' => $activityType->getId()]),
                        'method' => 'post',
                        'confirm' => sprintf('Delete %s?', $activityType->getName()),
                        'csrfToken' => $this->csrfToken($activityType),
                    ],
                ],
            ];
        }

        return $this->render('activity_type/index.html.twig', [
            'columns' => [
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'description', 'label' => 'Description'],
            ],
            'rows' => $rows,
            'pagination' => $pagination,
            'sortState' => $this->paginator->sortState($request, self::SORT_MAP),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $activityType = new ActivityType();
        $form = $this->createForm(ActivityTypeFormType::class, $activityType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($activityType);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was added.', $activityType->getName()));

            return $this->redirectToRoute('activity_type_index');
        }

        return $this->render('activity_type/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ActivityType $activityType): Response
    {
        $form = $this->createForm(ActivityTypeFormType::class, $activityType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was updated.', $activityType->getName()));

            return $this->redirectToRoute('activity_type_index');
        }

        return $this->render('activity_type/edit.html.twig', ['form' => $form, 'activity_type' => $activityType]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, ActivityType $activityType): Response
    {
        if (!$this->isCsrfTokenValid($this->csrfTokenId($activityType), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $this->redirectToRoute('activity_type_index');
        }

        $referencingCount = $this->activityTypes->countReferencingActivities($activityType);
        if ($referencingCount > 0) {
            $this->addFlash('error', sprintf(
                'Cannot delete %s — %d activit%s use%s it.',
                $activityType->getName(),
                $referencingCount,
                1 === $referencingCount ? 'y' : 'ies',
                1 === $referencingCount ? 's' : '',
            ));

            return $this->redirectToRoute('activity_type_index');
        }

        $this->entityManager->remove($activityType);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s was deleted.', $activityType->getName()));

        return $this->redirectToRoute('activity_type_index');
    }

    private function csrfTokenId(ActivityType $activityType): string
    {
        return 'delete-activity-type-' . $activityType->getId();
    }

    private function csrfToken(ActivityType $activityType): string
    {
        return $this->csrfTokenManager->getToken($this->csrfTokenId($activityType))->getValue();
    }
}
