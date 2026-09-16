<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\LoginAttempt;
use App\Repository\LoginAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Writes the Sign-ins half of /usage. See ADR 0028.
 *
 * Only the passport's UserBadge identifier is ever read, never the
 * credentials. Throttled attempts arrive as LoginFailureEvent too, which is
 * what makes repeated failures against one address visible.
 */
final readonly class LoginAttemptRecorder
{
    public const int RETENTION_DAYS = 90;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoginAttemptRepository $attempts,
    ) {}

    #[AsEventListener]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $this->record($event->getUser()->getUserIdentifier(), true, $event->getRequest());
    }

    #[AsEventListener]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        // No passport when the form was submitted with an empty email.
        $badge = $event->getPassport()?->getBadge(UserBadge::class);
        $identifier = $badge instanceof UserBadge ? $badge->getUserIdentifier() : null;

        $this->record($identifier, false, $event->getRequest());
    }

    private function record(?string $identifier, bool $succeeded, Request $request): void
    {
        // Anything that isn't an email address is dropped, not stored: that is
        // where a password typed into the wrong box would end up.
        if (false === filter_var($identifier, \FILTER_VALIDATE_EMAIL)) {
            $identifier = null;
        }

        // Same width as User::$email; a longer address can't be an account.
        $identifier = null === $identifier ? null : mb_substr($identifier, 0, 180);

        $this->entityManager->persist(new LoginAttempt($identifier, $succeeded, $request->getClientIp()));
        $this->attempts->pruneOlderThan(new \DateTimeImmutable(\sprintf('today -%d days', self::RETENTION_DAYS)));
        $this->entityManager->flush();
    }
}
