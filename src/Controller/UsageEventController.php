<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\UsageEvent;
use App\Enum\UsageEventName;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The write half of the in-page half of /usage. See ADR 0021.
 *
 * Everything here is deliberately quiet: recording that someone used a feature
 * must never be able to break the feature. An unknown name, a missing token
 * and a successful write all answer 204, because the browser has nothing
 * useful to do with any other answer and a 400 would only fill the console
 * with noise during normal use.
 */
final class UsageEventController extends AbstractController
{
    #[Route('/usage/event', name: 'usage_event', methods: ['POST'])]
    public function record(Request $request, EntityManagerInterface $entityManager): Response
    {
        // The enum is the whitelist: a name that is not one of its cases never
        // reaches the database. This is the trust boundary between a string
        // typed by a browser and a stored row.
        $name = UsageEventName::tryFrom((string) $request->request->get('name'));

        // Same-origin, login-protected and writing only an enum plus a
        // timestamp — but it is still a state-changing POST driven by markup,
        // which is exactly what CSRF protection is for. Cheap here, so no
        // reason to skip it.
        $token = (string) $request->request->get('_token');

        if (null === $name || !$this->isCsrfTokenValid('usage_event', $token)) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        $entityManager->persist(new UsageEvent($name));
        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
