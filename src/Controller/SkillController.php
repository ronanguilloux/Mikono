<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Skill;
use App\Export\ListExport;
use App\Form\SkillFormType;
use App\Pagination\ListPaginator;
use App\Repository\SkillRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/skills', name: 'skill_')]
final class SkillController extends AbstractController
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
        'name' => ['s.name'],
    ];

    /** @var list<array{key: string, label: string}> */
    private const array COLUMNS = [
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'description', 'label' => 'Description'],
    ];

    public function __construct(
        private readonly SkillRepository $skills,
        private readonly EntityManagerInterface $entityManager,
        private readonly ListPaginator $paginator,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $pagination = $this->paginator->paginateQuery($this->listQueryBuilder($request), Skill::class, $request);

        $rows = [];
        foreach ($pagination as $skill) {
            $rows[] = [
                'cells' => $this->cells($skill),
                'actions' => [
                    ['label' => 'Edit', 'url' => $this->generateUrl('skill_edit', ['id' => $skill->getId()])],
                    [
                        'label' => 'Delete',
                        'url' => $this->generateUrl('skill_delete', ['id' => $skill->getId()]),
                        'method' => 'post',
                        'confirm' => sprintf('Delete %s?', $skill->getName()),
                        'csrfTokenId' => $this->csrfTokenId($skill),
                    ],
                ],
            ];
        }

        return $this->render('skill/index.html.twig', [
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'pagination' => $pagination,
            'sortState' => $this->paginator->sortState($request, self::SORT_MAP),
        ]);
    }

    #[Route('/export.{format}', name: 'export', requirements: ['format' => 'csv|xlsx'], defaults: ['format' => 'csv'], methods: ['GET'])]
    public function export(Request $request, string $format): StreamedResponse
    {
        return ListExport::response('skills', $format, self::COLUMNS, (function () use ($request): \Generator {
            /** @var Skill $skill */
            foreach ($this->listQueryBuilder($request)->getQuery()->toIterable() as $skill) {
                yield $this->cells($skill);
            }
        })());
    }

    /**
     * The one query behind both the index and its export.
     */
    private function listQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->skills->createOrderedByNameQueryBuilder();
        $this->paginator->applySort($queryBuilder, $request, self::SORT_MAP);

        return $queryBuilder;
    }

    /**
     * @return array<string, string>
     */
    private function cells(Skill $skill): array
    {
        return [
            'name' => $skill->getName(),
            'description' => $skill->getDescription() ?? '—',
        ];
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $skill = new Skill();
        $form = $this->createForm(SkillFormType::class, $skill);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($skill);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was added.', $skill->getName()));

            return $this->redirectToRoute('skill_index');
        }

        return $this->render('skill/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Skill $skill): Response
    {
        $form = $this->createForm(SkillFormType::class, $skill);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was updated.', $skill->getName()));

            return $this->redirectToRoute('skill_index');
        }

        return $this->render('skill/edit.html.twig', ['form' => $form, 'skill' => $skill]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Skill $skill): Response
    {
        if (!$this->isCsrfTokenValid($this->csrfTokenId($skill), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $this->redirectToRoute('skill_index');
        }

        $volunteerCount = $this->skills->countReferencingVolunteers($skill);
        if ($volunteerCount > 0) {
            $this->addFlash('error', sprintf(
                'Cannot delete %s — %d volunteer%s hold%s it.',
                $skill->getName(),
                $volunteerCount,
                1 === $volunteerCount ? '' : 's',
                1 === $volunteerCount ? 's' : '',
            ));

            return $this->redirectToRoute('skill_index');
        }

        $programCount = $this->skills->countReferencingPrograms($skill);
        if ($programCount > 0) {
            $this->addFlash('error', sprintf(
                'Cannot delete %s — %d program%s need%s it.',
                $skill->getName(),
                $programCount,
                1 === $programCount ? '' : 's',
                1 === $programCount ? 's' : '',
            ));

            return $this->redirectToRoute('skill_index');
        }

        $this->entityManager->remove($skill);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s was deleted.', $skill->getName()));

        return $this->redirectToRoute('skill_index');
    }

    private function csrfTokenId(Skill $skill): string
    {
        return 'delete-skill-' . $skill->getId();
    }
}
