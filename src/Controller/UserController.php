<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\UserFormType;
use App\Pagination\ListPaginator;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/users', name: 'user_')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly ListPaginator $paginator,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $pagination = $this->paginator->paginateQuery(
            $this->users->createOrderedByNameQueryBuilder(),
            User::class,
            $request,
        );

        $rows = [];
        foreach ($pagination as $user) {
            $rows[] = [
                'cells' => [
                    'name' => $user->getFullName(),
                    'email' => $user->getEmail(),
                    'role' => $user->isAdmin() ? 'Admin' : 'Volunteer Manager',
                    'status' => $user->isActive() ? 'Active' : 'Deactivated',
                ],
                'actions' => [
                    ['label' => 'Edit', 'url' => $this->generateUrl('user_edit', ['id' => $user->getId()])],
                    [
                        'label' => 'Delete',
                        'url' => $this->generateUrl('user_delete', ['id' => $user->getId()]),
                        'method' => 'post',
                        'confirm' => sprintf('Delete %s?', $user->getFullName()),
                        'csrfToken' => $this->csrfToken($user),
                    ],
                ],
            ];
        }

        return $this->render('user/index.html.twig', [
            'columns' => [
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'email', 'label' => 'Email'],
                ['key' => 'role', 'label' => 'Role'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $rows,
            'pagination' => $pagination,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserFormType::class, $user, ['password_required' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was added.', $user->getFullName()));

            return $this->redirectToRoute('user_index');
        }

        return $this->render('user/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        $form = $this->createForm(UserFormType::class, $user, ['password_required' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($plainPassword = $form->get('plainPassword')->getData()) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            }
            $user->touch();
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was updated.', $user->getFullName()));

            return $this->redirectToRoute('user_index');
        }

        return $this->render('user/edit.html.twig', ['form' => $form, 'user_account' => $user]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid($this->csrfTokenId($user), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $this->redirectToRoute('user_index');
        }

        if ($user === $this->getUser()) {
            $this->addFlash('error', 'You cannot delete your own account while signed in as it.');

            return $this->redirectToRoute('user_index');
        }

        $referencingCount = $this->users->countReferencingActivities($user);
        if ($referencingCount > 0) {
            $this->addFlash('error', sprintf(
                'Cannot delete %s — %d activit%s logged by them reference this account. Deactivate instead.',
                $user->getFullName(),
                $referencingCount,
                1 === $referencingCount ? 'y' : 'ies',
            ));

            return $this->redirectToRoute('user_index');
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s was deleted.', $user->getFullName()));

        return $this->redirectToRoute('user_index');
    }

    private function csrfTokenId(User $user): string
    {
        return 'delete-user-' . $user->getId();
    }

    private function csrfToken(User $user): string
    {
        return $this->csrfTokenManager->getToken($this->csrfTokenId($user))->getValue();
    }
}
