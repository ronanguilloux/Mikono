<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The data model explained for non-developer admins — static content, kept
 * next to /usage in the admin part of the Settings menu.
 */
#[IsGranted('ROLE_ADMIN')]
final class DataMapController extends AbstractController
{
    #[Route('/datamap', name: 'datamap_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('datamap/index.html.twig');
    }
}
