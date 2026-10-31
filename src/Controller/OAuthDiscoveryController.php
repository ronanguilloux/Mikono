<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The two documents a client reads to find out how to sign in: RFC 9728 for
 * the protected resource, RFC 8414 for the authorization server. See ADR 0050.
 *
 * From DEFAULT_URI, not the request: `resource` must equal the URL entered in
 * Claude, and these answer before anyone has authenticated.
 */
final readonly class OAuthDiscoveryController
{
    private string $baseUri;

    public function __construct(#[Autowire(env: 'DEFAULT_URI')] string $baseUri)
    {
        $this->baseUri = rtrim($baseUri, '/');
    }

    #[Route('/.well-known/oauth-protected-resource', name: 'oauth_protected_resource', methods: ['GET'])]
    #[Route('/.well-known/oauth-protected-resource/mcp', name: 'oauth_protected_resource_mcp', methods: ['GET'])]
    public function protectedResource(): JsonResponse
    {
        return new JsonResponse([
            'resource' => $this->baseUri . '/mcp',
            'authorization_servers' => [$this->baseUri],
            'scopes_supported' => ['read'],
            'bearer_methods_supported' => ['header'],
        ]);
    }

    #[Route('/.well-known/oauth-authorization-server', name: 'oauth_authorization_server', methods: ['GET'])]
    public function authorizationServer(): JsonResponse
    {
        return new JsonResponse([
            'issuer' => $this->baseUri,
            'authorization_endpoint' => $this->baseUri . '/authorize',
            'token_endpoint' => $this->baseUri . '/token',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
            'scopes_supported' => ['read'],
        ]);
    }
}
