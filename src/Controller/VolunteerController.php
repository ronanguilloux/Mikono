<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Volunteer;
use App\Form\VolunteerFormType;
use App\Pagination\ListPaginator;
use App\Repository\ActivityRepository;
use App\Repository\VolunteerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/volunteers', name: 'volunteer_')]
final class VolunteerController extends AbstractController
{
    /**
     * Column key => DQL field(s) for the index's sortable headers. The map is
     * the whitelist, so nothing a reader types reaches DQL. `name` needs two
     * fields because getFullName() has no single column behind it. See ADR 0011.
     *
     * @var array<string, non-empty-list<string>>
     */
    private const array SORT_MAP = [
        'name' => ['v.lastName', 'v.firstName'],
        'email' => ['v.email'],
        'phone' => ['v.phone'],
        'status' => ['v.isActive'],
    ];

    public function __construct(
        private readonly VolunteerRepository $volunteers,
        private readonly ActivityRepository $activities,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly ListPaginator $paginator,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $queryBuilder = $this->volunteers->createOrderedByNameQueryBuilder();
        $this->paginator->applySort($queryBuilder, $request, self::SORT_MAP);

        $pagination = $this->paginator->paginateQuery($queryBuilder, Volunteer::class, $request);

        /** @var list<Volunteer> $volunteersOnPage */
        $volunteersOnPage = iterator_to_array($pagination, false);
        // One query for the whole page. The delete-guard's own count stays
        // per-entity in delete() — this is the same rule read ahead of time so
        // the index can show Delete as unavailable rather than let the reader
        // discover it from a flash after confirming.
        $activityCounts = $this->volunteers->countReferencingActivitiesFor($volunteersOnPage);

        $rows = [];
        $guardedCount = 0;
        foreach ($volunteersOnPage as $volunteer) {
            $id = $volunteer->getId();
            $referencingCount = null === $id ? 0 : ($activityCounts[$id] ?? 0);
            if ($referencingCount > 0) {
                ++$guardedCount;
            }

            $rows[] = [
                'cells' => [
                    'name' => $volunteer->getFullName(),
                    'email' => $volunteer->getEmail() ?? '—',
                    'phone' => $volunteer->getPhone() ?? '—',
                    'status' => $volunteer->isActive() ? 'Active' : 'Inactive',
                ],
                'actions' => [
                    ['label' => 'View', 'url' => $this->generateUrl('volunteer_show', ['id' => $id])],
                    ['label' => 'Edit', 'url' => $this->generateUrl('volunteer_edit', ['id' => $id])],
                    $referencingCount > 0
                        ? ['label' => 'Delete', 'disabledReason' => $this->guardReason($volunteer, $referencingCount)]
                        : [
                            'label' => 'Delete',
                            'url' => $this->generateUrl('volunteer_delete', ['id' => $id]),
                            'method' => 'post',
                            'confirm' => sprintf('Delete %s?', $volunteer->getFullName()),
                            'csrfToken' => $this->csrfToken($volunteer),
                        ],
                ],
            ];
        }

        return $this->render('volunteer/index.html.twig', [
            'columns' => [
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'email', 'label' => 'Email'],
                ['key' => 'phone', 'label' => 'Phone'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $rows,
            'pagination' => $pagination,
            'sortState' => $this->paginator->sortState($request, self::SORT_MAP),
            'guardedCount' => $guardedCount,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $volunteer = new Volunteer();
        $form = $this->createForm(VolunteerFormType::class, $volunteer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($volunteer);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was added.', $volunteer->getFullName()));

            return $this->redirectToRoute('volunteer_index');
        }

        return $this->render('volunteer/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Volunteer $volunteer): Response
    {
        $activities = $this->activities->findByVolunteerOrderedByDateDesc($volunteer);

        $totalDays = 0.0;
        $mostRecent = null;
        foreach ($activities as $activity) {
            $totalDays += $activity->getDuration()?->toDays() ?? 0.0;

            $date = $activity->getDate();
            if (null !== $date && (null === $mostRecent || $date > $mostRecent)) {
                $mostRecent = $date;
            }
        }

        $today = new \DateTimeImmutable('today');

        return $this->render('volunteer/show.html.twig', [
            'volunteer' => $volunteer,
            'activities' => $activities,
            'activityCount' => count($activities),
            'totalDays' => $totalDays,
            'mostRecent' => $mostRecent,
            'mostRecentIsPlanned' => null !== $mostRecent && $mostRecent > $today,
            'today' => $today,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Volunteer $volunteer): Response
    {
        $form = $this->createForm(VolunteerFormType::class, $volunteer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $volunteer->touch();
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was updated.', $volunteer->getFullName()));

            return $this->redirectToRoute('volunteer_index');
        }

        return $this->render('volunteer/edit.html.twig', ['form' => $form, 'volunteer' => $volunteer]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Volunteer $volunteer): Response
    {
        if (!$this->isCsrfTokenValid($this->csrfTokenId($volunteer), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $this->redirectToRoute('volunteer_index');
        }

        $referencingCount = $this->volunteers->countReferencingActivities($volunteer);
        if ($referencingCount > 0) {
            $this->addFlash('error', $this->guardReason($volunteer, $referencingCount));

            return $this->redirectToRoute('volunteer_index');
        }

        $this->entityManager->remove($volunteer);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s was deleted.', $volunteer->getFullName()));

        return $this->redirectToRoute('volunteer_index');
    }

    /**
     * Why this volunteer can't be deleted, in one sentence. Shared by the
     * index's greyed-out Delete and the flash raised if a delete is attempted
     * anyway, so the warning and the refusal can't drift apart.
     */
    private function guardReason(Volunteer $volunteer, int $referencingCount): string
    {
        return sprintf(
            'Cannot delete %s — %d activit%s reference%s them. Mark them inactive instead.',
            $volunteer->getFullName(),
            $referencingCount,
            1 === $referencingCount ? 'y' : 'ies',
            1 === $referencingCount ? 's' : '',
        );
    }

    private function csrfTokenId(Volunteer $volunteer): string
    {
        return 'delete-volunteer-' . $volunteer->getId();
    }

    private function csrfToken(Volunteer $volunteer): string
    {
        return $this->csrfTokenManager->getToken($this->csrfTokenId($volunteer))->getValue();
    }
}
