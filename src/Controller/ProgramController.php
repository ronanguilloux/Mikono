<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ActivityType;
use App\Entity\Program;
use App\Export\ListExport;
use App\Form\ProgramFormType;
use App\Pagination\ListPaginator;
use App\Repository\ProgramRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    private const array SORT_MAP = [
        'name' => ['prg.name'],
        'project' => ['p.name'],
        'branch' => ['b.name'],
        'dates' => ['prg.startDate', 'prg.endDate'],
    ];

    /** @var list<array{key: string, label: string}> */
    private const array COLUMNS = [
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'project', 'label' => 'Project'],
        ['key' => 'branch', 'label' => 'Branch'],
        ['key' => 'dates', 'label' => 'Dates'],
        ['key' => 'activityTypes', 'label' => 'Activity types'],
    ];

    public function __construct(
        private readonly ProgramRepository $programs,
        private readonly EntityManagerInterface $entityManager,
        private readonly ListPaginator $paginator,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $pagination = $this->paginator->paginateQuery($this->listQueryBuilder($request), Program::class, $request);

        $rows = [];
        /** @var Program $program */
        foreach ($pagination as $program) {
            $id = $program->getId();
            $rows[] = [
                'cells' => $this->cells($program),
                'actions' => [
                    ['label' => 'Edit', 'url' => $this->generateUrl('program_edit', ['id' => $id])],
                    [
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
        ]);
    }

    #[Route('/export.{format}', name: 'export', requirements: ['format' => 'csv|xlsx'], defaults: ['format' => 'csv'], methods: ['GET'])]
    public function export(Request $request, string $format): StreamedResponse
    {
        return ListExport::response('programs', $format, self::COLUMNS, (function () use ($request): \Generator {
            /** @var Program $program */
            foreach ($this->listQueryBuilder($request)->getQuery()->toIterable() as $program) {
                yield $this->cells($program);
            }
        })());
    }

    /**
     * The one query behind both the index and its export.
     */
    private function listQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->programs->createOrderedQueryBuilder();
        $this->paginator->applySort($queryBuilder, $request, self::SORT_MAP);

        return $queryBuilder;
    }

    /**
     * @return array<string, string>
     */
    private function cells(Program $program): array
    {
        return [
            'name' => $program->getName(),
            'project' => $program->getProject()?->getName() ?? '—',
            'branch' => $program->getProject()?->getBranch()?->getName() ?? '—',
            'dates' => self::dates($program),
            'activityTypes' => implode(', ', array_map(
                static fn(ActivityType $type) => $type->getName(),
                $program->getActivityTypes()->toArray(),
            )),
        ];
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
        $form = $this->createForm(ProgramFormType::class, $program);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $program->touch();
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was updated.', $program->getName()));

            return $this->redirectToRoute('program_index');
        }

        return $this->render('program/edit.html.twig', ['form' => $form, 'program' => $program]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Program $program): Response
    {
        if (!$this->isCsrfTokenValid($this->csrfTokenId($program), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $this->redirectToRoute('program_index');
        }

        $this->entityManager->remove($program);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s was deleted.', $program->getName()));

        return $this->redirectToRoute('program_index');
    }

    private function csrfTokenId(Program $program): string
    {
        return 'delete-program-' . $program->getId();
    }
}
