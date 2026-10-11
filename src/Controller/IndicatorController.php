<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Indicator;
use App\Export\ListExport;
use App\Form\IndicatorFormType;
use App\Pagination\ListPaginator;
use App\Repository\IndicatorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * What achievements count, the /sources shape.
 */
#[Route('/indicators', name: 'indicator_')]
final class IndicatorController extends AbstractController
{
    /**
     * Column key => DQL field(s) for the index's sortable headers; free-text
     * description stays out, as on /skills. See ADR 0011.
     *
     * @var array<string, non-empty-list<string>>
     */
    private const array SORT_MAP = [
        'name' => ['i.name'],
    ];

    /** @var list<array{key: string, label: string}> */
    private const array COLUMNS = [
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'description', 'label' => 'Description'],
    ];

    public function __construct(
        private readonly IndicatorRepository $indicators,
        private readonly EntityManagerInterface $entityManager,
        private readonly ListPaginator $paginator,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $pagination = $this->paginator->paginateQuery($this->listQueryBuilder($request), Indicator::class, $request);

        /** @var list<Indicator> $indicatorsOnPage */
        $indicatorsOnPage = iterator_to_array($pagination, false);
        $achievementCounts = $this->indicators->countReferencingAchievementsFor($indicatorsOnPage);

        $rows = [];
        foreach ($indicatorsOnPage as $indicator) {
            $id = $indicator->getId();
            $achievementCount = null === $id ? 0 : ($achievementCounts[$id] ?? 0);

            $rows[] = [
                'cells' => $this->cells($indicator),
                'actions' => [
                    ['label' => 'Edit', 'url' => $this->generateUrl('indicator_edit', ['id' => $id])],
                    $achievementCount > 0
                        ? ['label' => 'Delete', 'disabledReason' => $this->guardReason($indicator, $achievementCount)]
                        : [
                            'label' => 'Delete',
                            'url' => $this->generateUrl('indicator_delete', ['id' => $id]),
                            'method' => 'post',
                            'confirm' => sprintf('Delete %s?', $indicator->getName()),
                            'csrfTokenId' => $this->csrfTokenId($indicator),
                        ],
                ],
            ];
        }

        return $this->render('source/index.html.twig', [
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'pagination' => $pagination,
            'sortState' => $this->paginator->sortState($request, self::SORT_MAP),
        ]);
    }

    #[Route('/export.{format}', name: 'export', requirements: ['format' => 'csv|xlsx'], defaults: ['format' => 'csv'], methods: ['GET'])]
    public function export(Request $request, string $format): StreamedResponse
    {
        return ListExport::response('indicators', $format, self::COLUMNS, (function () use ($request): \Generator {
            /** @var Indicator $indicator */
            foreach ($this->listQueryBuilder($request)->getQuery()->toIterable() as $indicator) {
                yield $this->cells($indicator);
            }
        })());
    }

    /**
     * The one query behind both the index and its export.
     */
    private function listQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->indicators->createOrderedByNameQueryBuilder();
        $this->paginator->applySort($queryBuilder, $request, self::SORT_MAP);

        return $queryBuilder;
    }

    /**
     * @return array<string, string>
     */
    private function cells(Indicator $indicator): array
    {
        return [
            'name' => $indicator->getName(),
            'description' => $indicator->getDescription() ?? '—',
        ];
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $indicator = new Indicator();
        $form = $this->createForm(IndicatorFormType::class, $indicator);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($indicator);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was added.', $indicator->getName()));

            return $this->redirectToRoute('indicator_index');
        }

        return $this->render('source/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Indicator $indicator): Response
    {
        $form = $this->createForm(IndicatorFormType::class, $indicator);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was updated.', $indicator->getName()));

            return $this->redirectToRoute('indicator_index');
        }

        return $this->render('source/edit.html.twig', ['form' => $form, 'source' => $indicator]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Indicator $indicator): Response
    {
        if (!$this->isCsrfTokenValid($this->csrfTokenId($indicator), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $this->redirectToRoute('indicator_index');
        }

        $achievementCount = $this->indicators->countReferencingAchievements($indicator);
        if ($achievementCount > 0) {
            $this->addFlash('error', $this->guardReason($indicator, $achievementCount));

            return $this->redirectToRoute('indicator_index');
        }

        $this->entityManager->remove($indicator);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s was deleted.', $indicator->getName()));

        return $this->redirectToRoute('indicator_index');
    }

    private function guardReason(Indicator $indicator, int $achievementCount): string
    {
        return sprintf(
            'Cannot delete %s — %d achievement%s count%s it.',
            $indicator->getName(),
            $achievementCount,
            1 === $achievementCount ? '' : 's',
            1 === $achievementCount ? 's' : '',
        );
    }

    private function csrfTokenId(Indicator $indicator): string
    {
        return 'delete-indicator-' . $indicator->getId();
    }
}
