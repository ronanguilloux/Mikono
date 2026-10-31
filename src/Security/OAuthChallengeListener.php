<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Points a 401 at the discovery document. Claude starts sign-in from this
 * header and from nothing else, see ADR 0050.
 */
final readonly class OAuthChallengeListener
{
    public function __construct(
        #[Autowire(env: 'DEFAULT_URI')]
        private string $baseUri,
    ) {}

    #[AsEventListener]
    public function __invoke(ResponseEvent $event): void
    {
        if (401 !== $event->getResponse()->getStatusCode() || 1 !== preg_match('#^/(api|mcp)(/|$)#', $event->getRequest()->getPathInfo())) {
            return;
        }

        $event->getResponse()->headers->set('WWW-Authenticate', \sprintf(
            'Bearer resource_metadata="%s/.well-known/oauth-protected-resource"',
            rtrim($this->baseUri, '/'),
        ));
    }
}
