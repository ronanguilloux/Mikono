<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BeneficiaryGroup;
use App\Export\ListExport;
use App\Form\BeneficiaryGroupFormType;
use App\Pagination\ListPaginator;
use App\Repository\BeneficiaryGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/beneficiary-groups', name: 'beneficiary_group_')]
final class BeneficiaryGroupController extends AbstractController
{
    /**
     * Column key => DQL field(s) for the index's sortable headers; the map is
     * the whitelist. Description is free text, so it stays out. See ADR 0011.
     *
     * @var array<string, non-empty-list<string>>
     */
    private const array SORT_MAP = [
        'name' => ['bg.name'],
    ];

    /** @var list<array{key: string, label: string}> */
    private const array COLUMNS = [
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'description', 'label' => 'Description'],
    ];

    public function __construct(
        private readonly BeneficiaryGroupRepository $groups,
        private readonly EntityManagerInterface $entityManager,
        private readonly ListPaginator $paginator,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $pagination = $this->paginator->paginateQuery($this->listQueryBuilder($request), BeneficiaryGroup::class, $request);

        $rows = [];
        foreach ($pagination as $group) {
            $rows[] = [
                'cells' => $this->cells($group),
                'actions' => [
                    ['label' => 'Edit', 'url' => $this->generateUrl('beneficiary_group_edit', ['id' => $group->getId()])],
                    [
                        'label' => 'Delete',
                        'url' => $this->generateUrl('beneficiary_group_delete', ['id' => $group->getId()]),
                        'method' => 'post',
                        'confirm' => sprintf('Delete %s?', $group->getName()),
                        'csrfTokenId' => $this->csrfTokenId($group),
                    ],
                ],
            ];
        }

        return $this->render('beneficiary_group/index.html.twig', [
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'pagination' => $pagination,
            'sortState' => $this->paginator->sortState($request, self::SORT_MAP),
        ]);
    }

    #[Route('/export.{format}', name: 'export', requirements: ['format' => 'csv|xlsx'], defaults: ['format' => 'csv'], methods: ['GET'])]
    public function export(Request $request, string $format): StreamedResponse
    {
        return ListExport::response('beneficiary-groups', $format, self::COLUMNS, (function () use ($request): \Generator {
            /** @var BeneficiaryGroup $group */
            foreach ($this->listQueryBuilder($request)->getQuery()->toIterable() as $group) {
                yield $this->cells($group);
            }
        })());
    }

    /**
     * The one query behind both the index and its export.
     */
    private function listQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->groups->createOrderedByNameQueryBuilder();
        $this->paginator->applySort($queryBuilder, $request, self::SORT_MAP);

        return $queryBuilder;
    }

    /**
     * @return array<string, string>
     */
    private function cells(BeneficiaryGroup $group): array
    {
        return [
            'name' => $group->getName(),
            'description' => $group->getDescription() ?? '—',
        ];
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $group = new BeneficiaryGroup();
        $form = $this->createForm(BeneficiaryGroupFormType::class, $group);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($group);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was added.', $group->getName()));

            return $this->redirectToRoute('beneficiary_group_index');
        }

        return $this->render('beneficiary_group/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, BeneficiaryGroup $group): Response
    {
        $form = $this->createForm(BeneficiaryGroupFormType::class, $group);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was updated.', $group->getName()));

            return $this->redirectToRoute('beneficiary_group_index');
        }

        return $this->render('beneficiary_group/edit.html.twig', ['form' => $form, 'group' => $group]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, BeneficiaryGroup $group): Response
    {
        if (!$this->isCsrfTokenValid($this->csrfTokenId($group), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $this->redirectToRoute('beneficiary_group_index');
        }

        $programCount = $this->groups->countReferencingPrograms($group);
        if ($programCount > 0) {
            $this->addFlash('error', sprintf(
                'Cannot delete %s — %d program%s serve%s it.',
                $group->getName(),
                $programCount,
                1 === $programCount ? '' : 's',
                1 === $programCount ? 's' : '',
            ));

            return $this->redirectToRoute('beneficiary_group_index');
        }

        $this->entityManager->remove($group);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s was deleted.', $group->getName()));

        return $this->redirectToRoute('beneficiary_group_index');
    }

    private function csrfTokenId(BeneficiaryGroup $group): string
    {
        return 'delete-beneficiary-group-' . $group->getId();
    }
}
