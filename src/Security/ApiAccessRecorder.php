<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ApiAccess;
use App\Repository\ApiAccessRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Writes the API access half of /usage. See ADR 0050.
 *
 * On the response, not kernel.terminate: terminate fires for the main request
 * only, and an MCP tool's reads are sub-requests of POST /mcp. Only data routes
 * count, so OpenAPI documents and JSON-LD contexts stay out.
 */
final readonly class ApiAccessRecorder
{
    public const int RETENTION_DAYS = 90;

    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private ApiAccessRepository $accesses,
    ) {}

    #[AsEventListener]
    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        $token = $this->security->getToken();

        // Always an OAuth2Token on a data route: the `api` firewall takes nothing else.
        if (!$token instanceof OAuth2Token || !$event->getResponse()->isSuccessful() || !\is_string($route) || !self::isDataRoute($route)) {
            return;
        }

        $this->entityManager->persist(new ApiAccess($token->getUserIdentifier(), mb_substr($request->getPathInfo(), 0, 255), !$event->isMainRequest(), $token->getOAuthClientId()));
        $this->accesses->pruneOlderThan(new \DateTimeImmutable(\sprintf('today -%d days', self::RETENTION_DAYS)));
        $this->entityManager->flush();
    }

    private static function isDataRoute(string $route): bool
    {
        return \in_array($route, ['api_report', 'api_volunteer_photo'], true)
            || (str_starts_with($route, '_api_') && !str_contains($route, 'validation_errors') && !str_contains($route, '_api_errors'));
    }
}
