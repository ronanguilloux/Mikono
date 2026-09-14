<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Stay;
use App\Entity\Volunteer;
use App\Form\StayFormType;
use App\Repository\StayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A volunteer's stays, managed from that volunteer's page — no index of its
 * own, since a stay means nothing apart from its volunteer. The new route is
 * named `volunteer_stay_new` because its `{id}` is the volunteer's. See ADR 0026.
 */
final class StayController extends AbstractController
{
    public function __construct(
        private readonly StayRepository $stays,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('/volunteers/{id}/stays/new', name: 'volunteer_stay_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Volunteer $volunteer): Response
    {
        $stay = new Stay();
        $form = $this->createForm(StayFormType::class, $stay);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->fits($form, $stay, $volunteer)) {
            $volunteer->addStay($stay);
            $this->entityManager->persist($stay);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Stay added for %s.', $volunteer->getFullName()));

            return $this->redirectToRoute('volunteer_show', ['id' => $volunteer->getId()]);
        }

        return $this->render('stay/new.html.twig', ['form' => $form, 'volunteer' => $volunteer]);
    }

    #[Route('/stays/{id}/edit', name: 'stay_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Stay $stay): Response
    {
        $volunteer = $stay->getVolunteer() ?? throw $this->createNotFoundException();
        $form = $this->createForm(StayFormType::class, $stay);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->fits($form, $stay, $volunteer)) {
            $stay->touch();
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Stay updated for %s.', $volunteer->getFullName()));

            return $this->redirectToRoute('volunteer_show', ['id' => $volunteer->getId()]);
        }

        return $this->render('stay/edit.html.twig', ['form' => $form, 'volunteer' => $volunteer]);
    }

    #[Route('/stays/{id}/delete', name: 'stay_delete', methods: ['POST'])]
    public function delete(Request $request, Stay $stay): Response
    {
        $volunteer = $stay->getVolunteer() ?? throw $this->createNotFoundException();
        $redirect = $this->redirectToRoute('volunteer_show', ['id' => $volunteer->getId()]);

        $token = $request->request->all()['_token'] ?? null;
        if (!\is_string($token) || !$this->isCsrfTokenValid(self::csrfTokenId($stay), $token)) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $redirect;
        }

        $referencingCount = $this->stays->countReferencingActivities($stay);
        if ($referencingCount > 0) {
            $this->addFlash('error', self::guardReason($referencingCount));

            return $redirect;
        }

        $volunteer->removeStay($stay);
        $this->entityManager->remove($stay);
        $this->entityManager->flush();

        $this->addFlash('success', 'Stay was deleted.');

        return $redirect;
    }

    /**
     * Shared by the volunteer page's inert Delete and the refusal above, so
     * the warning and the refusal can't drift apart.
     */
    public static function guardReason(int $referencingCount): string
    {
        return sprintf(
            'Cannot delete this stay — %d activit%s %s logged in it.',
            $referencingCount,
            1 === $referencingCount ? 'y' : 'ies',
            1 === $referencingCount ? 'is' : 'are',
        );
    }

    public static function csrfTokenId(Stay $stay): string
    {
        return 'delete-stay-' . $stay->getId();
    }

    /**
     * The rules a single stay's own constraints can't see: no overlap with the
     * volunteer's other stays (so a day has one branch at most), and, on edit,
     * no activity already logged in the stay left outside its new dates or at
     * another branch's projects (ADR 0027).
     *
     * @param FormInterface<Stay> $form
     */
    private function fits(FormInterface $form, Stay $stay, Volunteer $volunteer): bool
    {
        foreach ($volunteer->getStays() as $other) {
            if ($other !== $stay && $other->overlaps($stay)) {
                $form->get('startDate')->addError(new FormError(sprintf(
                    'This overlaps the stay at %s, %s – %s.',
                    $other->getBranch()?->getName() ?? 'another branch',
                    $other->getStartDate()?->format('j M Y'),
                    $other->getEndDate()?->format('j M Y'),
                )));

                return false;
            }
        }

        $outside = $this->stays->countActivitiesOutside($stay);
        if ($outside > 0) {
            $form->get('startDate')->addError(new FormError(sprintf(
                '%d activit%s logged in this stay would fall outside these dates.',
                $outside,
                1 === $outside ? 'y' : 'ies',
            )));

            return false;
        }

        $elsewhere = $this->stays->countActivitiesAtOtherBranch($stay);
        if ($elsewhere > 0) {
            $form->get('branch')->addError(new FormError(sprintf(
                '%d activit%s logged in this stay %s at another branch\'s projects.',
                $elsewhere,
                1 === $elsewhere ? 'y' : 'ies',
                1 === $elsewhere ? 'is' : 'are',
            )));

            return false;
        }

        return true;
    }
}
