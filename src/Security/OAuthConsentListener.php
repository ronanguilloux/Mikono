<?php

declare(strict_types=1);

namespace App\Security;

use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;
use League\Bundle\OAuth2ServerBundle\OAuth2Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The consent step of /authorize: the GET shows a person what they are about
 * to connect, and their POST back to the same URL answers it. See ADR 0050.
 *
 * Non-admins are refused here so they get an OAuth error at once, rather than
 * a token that access_control would reject on every call.
 */
final readonly class OAuthConsentListener
{
    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private CsrfTokenManagerInterface $csrfTokens,
        private Environment $twig,
    ) {}

    #[AsEventListener(event: OAuth2Events::AUTHORIZATION_REQUEST_RESOLVE)]
    public function __invoke(AuthorizationRequestResolveEvent $event): void
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_DENIED);

            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->isMethod('POST')) {
            $event->setResponse(new Response($this->twig->render('oauth/consent.html.twig', [
                'client_name' => $event->getClient()->getName(),
                'redirect_host' => parse_url((string) $event->getRedirectUri(), \PHP_URL_HOST),
            ])));

            return;
        }

        $token = $request->request->getString('_token');
        if (!$this->csrfTokens->isTokenValid(new CsrfToken('oauth_consent', $token))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }

        $event->resolveAuthorization('allow' === $request->request->getString('decision'));
    }
}
