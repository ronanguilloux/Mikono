<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Adds `iss` to every /authorize answer sent back to a client (RFC 9207). The
 * bundle has no option for it. ChatGPT gives each connection its own callback
 * URL unless the server supports this, and a seeded exact redirect couldn't
 * cover those. See ADR 0051.
 *
 * Discovery promises it on errors too, so this must see every code or error
 * redirect, and only those: the redirect to /login carries neither.
 */
final readonly class OAuthIssuerListener
{
    public function __construct(
        #[Autowire(env: 'DEFAULT_URI')]
        private string $baseUri,
    ) {}

    #[AsEventListener]
    public function __invoke(ResponseEvent $event): void
    {
        $location = $event->getResponse()->headers->get('Location');

        if ('oauth2_authorize' !== $event->getRequest()->attributes->get('_route') || null === $location || 1 !== preg_match('/[?&](code|error)=/', $location)) {
            return;
        }

        $event->getResponse()->headers->set('Location', $location . '&iss=' . rawurlencode(rtrim($this->baseUri, '/')));
    }
}
