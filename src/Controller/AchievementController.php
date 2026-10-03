<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Achievement;
use App\Entity\Stay;
use App\Export\ListExport;
use App\Form\AchievementFormType;
use App\Pagination\ListPaginator;
use App\Repository\AchievementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * What volunteers achieved during their stays. Listed across volunteers at
 * /reports/achievements, but added from a stay on the volunteer's page, so the new
 * route is `stay_achievement_new` with the stay's `{id}`. See ADR 0038.
 */
final class AchievementController extends AbstractController
{
    /**
     * Column key => DQL field(s) for the index's sortable headers; the map is
     * the whitelist. See ADR 0011.
     *
     * @var array<string, non-empty-list<string>>
     */
    private const array SORT_MAP = [
        'achievedOn' => ['ach.achievedOn'],
        'title' => ['ach.title'],
        'volunteer' => ['v.firstName', 'v.lastName'],
        'project' => ['p.name'],
        'branch' => ['b.name'],
    ];

    /**
     * The description stays out: it may name children or donors (ADR 0038).
     *
     * @var list<array{key: string, label: string}>
     */
    private const array COLUMNS = [
        ['key' => 'achievedOn', 'label' => 'Achieved on'],
        ['key' => 'title', 'label' => 'Achievement'],
        ['key' => 'volunteer', 'label' => 'Volunteer'],
        ['key' => 'project', 'label' => 'Project'],
        ['key' => 'branch', 'label' => 'Branch'],
    ];

    public function __construct(
        private readonly AchievementRepository $achievements,
        private readonly EntityManagerInterface $entityManager,
        private readonly ListPaginator $paginator,
    ) {}

    #[Route('/reports/achievements', name: 'achievement_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $pagination = $this->paginator->paginateQuery($this->listQueryBuilder($request), Achievement::class, $request);

        $rows = [];
        foreach ($pagination as $achievement) {
            $rows[] = [
                'cells' => $this->cells($achievement),
                'actions' => [
                    ['label' => 'Edit', 'url' => $this->generateUrl('achievement_edit', ['id' => $achievement->getId()])],
                    [
                        'label' => 'Delete',
                        'url' => $this->generateUrl('achievement_delete', ['id' => $achievement->getId()]),
                        'method' => 'post',
                        'confirm' => sprintf('Delete "%s"?', $achievement->getTitle()),
                        'csrfTokenId' => self::csrfTokenId($achievement),
                    ],
                ],
            ];
        }

        return $this->render('achievement/index.html.twig', [
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'pagination' => $pagination,
            'sortState' => $this->paginator->sortState($request, self::SORT_MAP),
        ]);
    }

    #[Route('/reports/achievements/export.{format}', name: 'achievement_export', requirements: ['format' => 'csv|xlsx'], defaults: ['format' => 'csv'], methods: ['GET'])]
    public function export(Request $request, string $format): StreamedResponse
    {
        return ListExport::response('achievements', $format, self::COLUMNS, (function () use ($request): \Generator {
            /** @var Achievement $achievement */
            foreach ($this->listQueryBuilder($request)->getQuery()->toIterable() as $achievement) {
                yield $this->cells($achievement);
            }
        })());
    }

    /**
     * The one query behind both the index and its export.
     */
    private function listQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->achievements->createNewestFirstQueryBuilder();
        $this->paginator->applySort($queryBuilder, $request, self::SORT_MAP);

        return $queryBuilder;
    }

    /**
     * @return array<string, string>
     */
    private function cells(Achievement $achievement): array
    {
        $stay = $achievement->getStay();

        return [
            'achievedOn' => $achievement->getAchievedOn()?->format('j M Y') ?? '—',
            'title' => $achievement->getTitle(),
            'volunteer' => $stay?->getVolunteer()?->getFullName() ?? '—',
            'project' => $achievement->getProject()?->getName() ?? '—',
            'branch' => $stay?->getBranch()?->getName() ?? '—',
        ];
    }

    #[Route('/stays/{id}/achievements/new', name: 'stay_achievement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Stay $stay): Response
    {
        $volunteer = $stay->getVolunteer() ?? throw $this->createNotFoundException();
        $achievement = (new Achievement())->setStay($stay);
        $form = $this->createForm(AchievementFormType::class, $achievement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->fits($form, $achievement, $stay)) {
            $this->entityManager->persist($achievement);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Achievement added for %s.', $volunteer->getFullName()));

            return $this->redirectToRoute('volunteer_show', ['id' => $volunteer->getId()]);
        }

        return $this->render('achievement/new.html.twig', ['form' => $form, 'volunteer' => $volunteer, 'stay' => $stay]);
    }

    #[Route('/achievements/{id}/edit', name: 'achievement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Achievement $achievement): Response
    {
        $stay = $achievement->getStay() ?? throw $this->createNotFoundException();
        $volunteer = $stay->getVolunteer() ?? throw $this->createNotFoundException();
        $form = $this->createForm(AchievementFormType::class, $achievement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->fits($form, $achievement, $stay)) {
            $achievement->touch();
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Achievement updated for %s.', $volunteer->getFullName()));

            return $this->redirectToRoute('volunteer_show', ['id' => $volunteer->getId()]);
        }

        return $this->render('achievement/edit.html.twig', ['form' => $form, 'volunteer' => $volunteer, 'stay' => $stay]);
    }

    #[Route('/achievements/{id}/delete', name: 'achievement_delete', methods: ['POST'])]
    public function delete(Request $request, Achievement $achievement): Response
    {
        $volunteer = $achievement->getStay()?->getVolunteer() ?? throw $this->createNotFoundException();
        $redirect = $this->redirectToRoute('volunteer_show', ['id' => $volunteer->getId()]);

        $token = $request->request->all()['_token'] ?? null;
        if (!\is_string($token) || !$this->isCsrfTokenValid(self::csrfTokenId($achievement), $token)) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $redirect;
        }

        $this->entityManager->remove($achievement);
        $this->entityManager->flush();

        $this->addFlash('success', 'Achievement was deleted.');

        return $redirect;
    }

    public static function csrfTokenId(Achievement $achievement): string
    {
        return 'delete-achievement-' . $achievement->getId();
    }

    /**
     * The day falls within the stay — the anniversary needs a real day — and
     * the project is at the stay's branch (ADR 0027). StayController and
     * ProjectController re-check both when a stay or project moves.
     *
     * @param FormInterface<Achievement> $form
     */
    private function fits(FormInterface $form, Achievement $achievement, Stay $stay): bool
    {
        $day = $achievement->getAchievedOn();
        if (null !== $day && !$stay->covers($day)) {
            $form->get('achievedOn')->addError(new FormError(sprintf(
                'Choose a day within the stay, %s – %s.',
                $stay->getStartDate()?->format('j M Y'),
                $stay->getEndDate()?->format('j M Y'),
            )));

            return false;
        }

        $branch = $achievement->getProject()?->getBranch();
        if (null !== $branch && $branch !== $stay->getBranch()) {
            $form->get('project')->addError(new FormError(sprintf(
                'This project is at %s, but the stay is at %s.',
                $branch->getName(),
                $stay->getBranch()?->getName() ?? 'another branch',
            )));

            return false;
        }

        return true;
    }
}
