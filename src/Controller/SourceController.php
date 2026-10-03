<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Source;
use App\Export\ListExport;
use App\Form\SourceFormType;
use App\Pagination\ListPaginator;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Recruitment sources, the /skills shape (ADR 0041).
 */
#[Route('/sources', name: 'source_')]
final class SourceController extends AbstractController
{
    /**
     * Column key => DQL field(s) for the index's sortable headers; free-text
     * description stays out, as on /skills. See ADR 0011.
     *
     * @var array<string, non-empty-list<string>>
     */
    private const array SORT_MAP = [
        'name' => ['s.name'],
    ];

    /** @var list<array{key: string, label: string}> */
    private const array COLUMNS = [
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'description', 'label' => 'Description'],
    ];

    public function __construct(
        private readonly SourceRepository $sources,
        private readonly EntityManagerInterface $entityManager,
        private readonly ListPaginator $paginator,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $pagination = $this->paginator->paginateQuery($this->listQueryBuilder($request), Source::class, $request);

        /** @var list<Source> $sourcesOnPage */
        $sourcesOnPage = iterator_to_array($pagination, false);
        $volunteerCounts = $this->sources->countReferencingVolunteersFor($sourcesOnPage);

        $rows = [];
        foreach ($sourcesOnPage as $source) {
            $id = $source->getId();
            $volunteerCount = null === $id ? 0 : ($volunteerCounts[$id] ?? 0);

            $rows[] = [
                'cells' => $this->cells($source),
                'actions' => [
                    ['label' => 'Edit', 'url' => $this->generateUrl('source_edit', ['id' => $id])],
                    $volunteerCount > 0
                        ? ['label' => 'Delete', 'disabledReason' => $this->guardReason($source, $volunteerCount)]
                        : [
                            'label' => 'Delete',
                            'url' => $this->generateUrl('source_delete', ['id' => $id]),
                            'method' => 'post',
                            'confirm' => sprintf('Delete %s?', $source->getName()),
                            'csrfTokenId' => $this->csrfTokenId($source),
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
        return ListExport::response('sources', $format, self::COLUMNS, (function () use ($request): \Generator {
            /** @var Source $source */
            foreach ($this->listQueryBuilder($request)->getQuery()->toIterable() as $source) {
                yield $this->cells($source);
            }
        })());
    }

    /**
     * The one query behind both the index and its export.
     */
    private function listQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->sources->createOrderedByNameQueryBuilder();
        $this->paginator->applySort($queryBuilder, $request, self::SORT_MAP);

        return $queryBuilder;
    }

    /**
     * @return array<string, string>
     */
    private function cells(Source $source): array
    {
        return [
            'name' => $source->getName(),
            'description' => $source->getDescription() ?? '—',
        ];
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $source = new Source();
        $form = $this->createForm(SourceFormType::class, $source);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($source);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was added.', $source->getName()));

            return $this->redirectToRoute('source_index');
        }

        return $this->render('source/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Source $source): Response
    {
        $form = $this->createForm(SourceFormType::class, $source);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was updated.', $source->getName()));

            return $this->redirectToRoute('source_index');
        }

        return $this->render('source/edit.html.twig', ['form' => $form, 'source' => $source]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Source $source): Response
    {
        if (!$this->isCsrfTokenValid($this->csrfTokenId($source), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $this->redirectToRoute('source_index');
        }

        $volunteerCount = $this->sources->countReferencingVolunteers($source);
        if ($volunteerCount > 0) {
            $this->addFlash('error', $this->guardReason($source, $volunteerCount));

            return $this->redirectToRoute('source_index');
        }

        $this->entityManager->remove($source);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s was deleted.', $source->getName()));

        return $this->redirectToRoute('source_index');
    }

    private function guardReason(Source $source, int $volunteerCount): string
    {
        return sprintf(
            'Cannot delete %s — %d volunteer%s came through it.',
            $source->getName(),
            $volunteerCount,
            1 === $volunteerCount ? '' : 's',
        );
    }

    private function csrfTokenId(Source $source): string
    {
        return 'delete-source-' . $source->getId();
    }
}
