<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Activity;
use App\Form\ActivityFormType;
use App\Repository\ActivityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/activities', name: 'activity_')]
final class ActivityController extends AbstractController
{
    public function __construct(
        private readonly ActivityRepository $activities,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $rows = [];
        foreach ($this->activities->findAllOrderedByDateDesc() as $activity) {
            $rows[] = [
                'cells' => [
                    'date' => $activity->getDate()?->format('D j M Y') ?? '—',
                    'volunteer' => $activity->getVolunteer()?->getFullName() ?? '—',
                    'project' => $activity->getProject()?->getName() ?? '—',
                    'activityType' => $activity->getActivityType()?->getName() ?? '—',
                    'duration' => $activity->getDuration()?->label() ?? '—',
                ],
                'actions' => [
                    ['label' => 'Edit', 'url' => $this->generateUrl('activity_edit', ['id' => $activity->getId()])],
                    [
                        'label' => 'Delete',
                        'url' => $this->generateUrl('activity_delete', ['id' => $activity->getId()]),
                        'method' => 'post',
                        'confirm' => 'Delete this activity entry?',
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
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $activity = new Activity();
        $form = $this->createForm(ActivityFormType::class, $activity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $activity->setLoggedBy($this->getUser());
            $this->entityManager->persist($activity);
            $this->entityManager->flush();

            $this->addFlash('success', 'Activity was logged.');

            return $this->redirectToRoute('activity_index');
        }

        return $this->render('activity/new.html.twig', ['form' => $form]);
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

    private function csrfTokenId(Activity $activity): string
    {
        return 'delete-activity-' . $activity->getId();
    }

    private function csrfToken(Activity $activity): string
    {
        return $this->csrfTokenManager->getToken($this->csrfTokenId($activity))->getValue();
    }
}
