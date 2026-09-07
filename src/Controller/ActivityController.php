<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\BatchActivityInput;
use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\User;
use App\Entity\Volunteer;
use App\Enum\ActivityDuration;
use App\Form\ActivityFormType;
use App\Form\BatchActivityFormType;
use App\Pagination\ListPaginator;
use App\Repository\ActivityRepository;
use App\Repository\VolunteerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/activities', name: 'activity_')]
final class ActivityController extends AbstractController
{
    /**
     * Column key => DQL field(s) for the index's sortable headers; the map is
     * the whitelist. The three joins in createOrderedByDateDescQueryBuilder()
     * are all to-one, so sorting on a joined name can't multiply rows and the
     * page size keeps meaning what it says.
     *
     * Duration is absent on purpose: it's stored as the enum values
     * half_day/full_day/other alongside a free-text durationOther, so any
     * ORDER BY on it is arbitrary. See ADR 0011.
     *
     * @var array<string, non-empty-list<string>>
     */
    private const array SORT_MAP = [
        'date' => ['a.date'],
        'volunteer' => ['v.lastName', 'v.firstName'],
        'project' => ['p.name'],
        'activityType' => ['t.name'],
    ];

    public function __construct(
        private readonly ActivityRepository $activities,
        private readonly VolunteerRepository $volunteers,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly ListPaginator $paginator,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $today = new \DateTimeImmutable('today');

        $volunteer = $this->requestedVolunteer($request);

        $queryBuilder = $this->activities->createOrderedByDateDescQueryBuilder($volunteer);
        $this->paginator->applySort($queryBuilder, $request, self::SORT_MAP);

        $pagination = $this->paginator->paginateQuery($queryBuilder, Activity::class, $request);

        $rows = [];
        foreach ($pagination as $activity) {
            $date = $activity->getDate();
            $rows[] = [
                // Extra to DataTable's own row shape, which ignores it: the
                // mobile card list tags future-dated rows as planned, the way
                // the home screen's tomorrow roster does. The desktop table
                // stays exactly as it was.
                'planned' => null !== $date && $date > $today,
                'cells' => [
                    'date' => $date?->format('D j M Y') ?? '—',
                    'volunteer' => $activity->getVolunteer()?->getFullName() ?? '—',
                    'project' => $activity->getProject()?->getName() ?? '—',
                    'activityType' => $activity->getActivityType()?->getName() ?? '—',
                    'duration' => ActivityDuration::Other === $activity->getDuration()
                        ? ($activity->getDurationOther() ?? ActivityDuration::Other->label())
                        : ($activity->getDuration()?->label() ?? '—'),
                ],
                'actions' => [
                    ['label' => 'Edit', 'url' => $this->generateUrl('activity_edit', ['id' => $activity->getId()])],
                    [
                        'label' => 'Delete',
                        'url' => $this->generateUrl('activity_delete', ['id' => $activity->getId()]),
                        'method' => 'post',
                        'confirm' => sprintf(
                            'Delete the %s activity for %s?',
                            $date?->format('j M Y') ?? 'undated',
                            $activity->getVolunteer()?->getFullName() ?? 'unknown volunteer',
                        ),
                        'csrfToken' => $this->csrfToken($activity),
                    ],
                ],
            ];
        }

        return $this->render('activity/index.html.twig', [
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'volunteer', 'label' => 'Volunteer'],
                ['key' => 'project', 'label' => 'Project'],
                ['key' => 'activityType', 'label' => 'Activity type'],
                ['key' => 'duration', 'label' => 'Duration'],
            ],
            'rows' => $rows,
            'pagination' => $pagination,
            'sortState' => $this->paginator->sortState($request, self::SORT_MAP),
            'volunteer' => $volunteer,
            // Every volunteer, not just the active ones the activity forms
            // offer: this reads history, and someone who finished their stint
            // has to stay findable.
            'volunteers' => $this->volunteers->findAllOrderedByName(),
        ]);
    }

    /**
     * The index's `?volunteer=<id>` filter, read the way ListPaginator reads
     * its own params: through query->all(), because InputBag::get() throws on
     * `?volunteer[]=1` and getInt() throws on `?volunteer=abc`. Anything
     * unusable — blank, non-numeric, an array, an id that no longer exists —
     * means no filter rather than a 400 or a 404.
     */
    private function requestedVolunteer(Request $request): ?Volunteer
    {
        $raw = $request->query->all()['volunteer'] ?? null;

        if (!is_scalar($raw) || (int) $raw < 1) {
            return null;
        }

        return $this->volunteers->find((int) $raw);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $activity = new Activity();
        $form = $this->createForm(ActivityFormType::class, $activity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $activity->setLoggedBy($this->loggedByUser());
            $this->entityManager->persist($activity);
            $this->entityManager->flush();

            $this->addFlash('success', 'Activity was logged.');

            return $this->redirectToRoute('activity_index');
        }

        return $this->render('activity/new.html.twig', ['form' => $form]);
    }

    #[Route('/new-batch', name: 'new_batch', methods: ['GET', 'POST'])]
    public function newBatch(Request $request): Response
    {
        $today = new \DateTimeImmutable('today');
        $data = new BatchActivityInput();
        // The home screen links here pre-filled: its rosters pass the day they
        // cover, and "Assign volunteers" on a quiet project passes that
        // project, so the VM lands on a form that only needs the people.
        $data->date = $this->requestedDate($request) ?? $today;
        $data->project = $this->requestedProject($request);
        $form = $this->createForm(BatchActivityFormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $loggedBy = $this->loggedByUser();

            foreach ($data->volunteers as $volunteer) {
                $activity = new Activity();
                $activity->setDate($data->date);
                $activity->setVolunteer($volunteer);
                $activity->setProject($data->project);
                $activity->setActivityType($data->activityType);
                $activity->setDuration($data->duration);
                $activity->setDurationOther($data->durationOther);
                foreach ($data->escorts as $escort) {
                    $activity->addEscort($escort);
                }
                $activity->setNotes($data->notes);
                $activity->setLoggedBy($loggedBy);
                $this->entityManager->persist($activity);
            }

            $this->entityManager->flush();

            $count = count($data->volunteers);
            $this->addFlash('success', sprintf('Logged %d %s.', $count, 1 === $count ? 'activity' : 'activities'));

            if ('add_another' === $request->request->get('save_action')) {
                return $this->redirectToRoute('activity_new_batch');
            }

            return $this->redirectToRoute('activity_index');
        }

        return $this->render('activity/new_batch.html.twig', [
            'form' => $form,
            'todayIso' => $today->format('Y-m-d'),
            'tomorrowIso' => $today->modify('+1 day')->format('Y-m-d'),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Activity $activity): Response
    {
        // loggedBy intentionally stays untouched here — fixing a mistake
        // shouldn't silently reassign who originally logged it.
        $form = $this->createForm(ActivityFormType::class, $activity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $activity->touch();
            $this->entityManager->flush();

            $this->addFlash('success', 'Activity was updated.');

            return $this->redirectToRoute('activity_index');
        }

        return $this->render('activity/edit.html.twig', ['form' => $form, 'activity' => $activity]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Activity $activity): Response
    {
        if (!$this->isCsrfTokenValid($this->csrfTokenId($activity), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $this->redirectToRoute('activity_index');
        }

        $this->entityManager->remove($activity);
        $this->entityManager->flush();

        $this->addFlash('success', 'Activity was deleted.');

        return $this->redirectToRoute('activity_index');
    }

    /**
     * The batch form's `?date=<Y-m-d>` prefill, read through query->all() for
     * the same reason as requestedVolunteer(): InputBag::get() throws on
     * `?date[]=x`. An unusable date means today's default, not a 400.
     */
    private function requestedDate(Request $request): ?\DateTimeImmutable
    {
        $raw = $request->query->all()['date'] ?? null;
        if (!is_string($raw)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

        return false === $date ? null : $date;
    }

    /**
     * The batch form's `?project=<id>` prefill. Read exactly like
     * requestedVolunteer() above: getInt() throws on `?project=abc` and
     * InputBag::get() throws on `?project[]=1`, and neither belongs on a link
     * the home screen hands out. Anything unusable means no prefill.
     */
    private function requestedProject(Request $request): ?Project
    {
        $raw = $request->query->all()['project'] ?? null;

        if (!is_scalar($raw) || (int) $raw < 1) {
            return null;
        }

        return $this->entityManager->find(Project::class, (int) $raw);
    }

    private function loggedByUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function csrfTokenId(Activity $activity): string
    {
        return 'delete-activity-' . $activity->getId();
    }

    private function csrfToken(Activity $activity): string
    {
        return $this->csrfTokenManager->getToken($this->csrfTokenId($activity))->getValue();
    }
}
