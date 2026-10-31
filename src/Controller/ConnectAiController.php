<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\ApiDataMap;
use App\Entity\User;
use App\Repository\ApiAccessRepository;
use League\Bundle\OAuth2ServerBundle\Service\CredentialsRevokerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lets an admin connect an AI app to Mikono alone, and disconnect it. There is
 * no secret to hand out: the admin is identified by signing in, and the client
 * ID shown here isn't confidential. See ADR 0050.
 *
 * One route per app rather than `{app}`: RouteSmokeTest walks these for free.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/connect-ai', name: 'connect_ai_')]
final class ConnectAiController extends AbstractController
{
    private const string CLIENT_ID = 'claude';

    public function __construct(
        #[Autowire(env: 'DEFAULT_URI')]
        private readonly string $baseUri,
        private readonly ApiAccessRepository $accesses,
        private readonly CredentialsRevokerInterface $revoker,
        private readonly ApiDataMap $dataMap,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('connect_ai/index.html.twig', [
            'connected' => $this->accesses->isConnected($user->getUserIdentifier()),
            'lastRead' => $this->accesses->lastReadBy($user->getUserIdentifier()),
        ]);
    }

    #[Route('/readable', name: 'readable', methods: ['GET'])]
    public function readable(): Response
    {
        return $this->render('connect_ai/readable.html.twig', [
            'boxes' => $this->dataMap->boxes(),
            'neverReadable' => $this->dataMap->neverReadable(),
        ]);
    }

    #[Route('/claude', name: 'claude', methods: ['GET'])]
    public function claude(): Response
    {
        return $this->render('connect_ai/claude.html.twig', [
            'mcpUrl' => rtrim($this->baseUri, '/') . '/mcp',
            'clientId' => self::CLIENT_ID,
        ]);
    }

    #[Route('/chatgpt', name: 'chatgpt', methods: ['GET'])]
    public function chatgpt(): Response
    {
        return $this->render('connect_ai/chatgpt.html.twig');
    }

    #[Route('/gemini', name: 'gemini', methods: ['GET'])]
    public function gemini(): Response
    {
        return $this->render('connect_ai/gemini.html.twig');
    }

    #[Route('/disconnect', name: 'disconnect', methods: ['POST'])]
    public function disconnect(Request $request, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('connect_ai_disconnect', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        // The signed-in admin's own tokens, and nobody else's.
        $this->revoker->revokeCredentialsForUser($user);
        $this->addFlash('success', 'Disconnected. Claude will ask you to sign in again before it can read anything.');

        return $this->redirectToRoute('connect_ai_index');
    }
}
