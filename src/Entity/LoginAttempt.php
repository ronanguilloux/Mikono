<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LoginAttemptRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One sign-in attempt, successful or not, for the Sign-ins table on /usage.
 * See ADR 0028.
 *
 * Unlike UsageEvent this row IS personal — an identifier and an IP — which is
 * why it lives in its own table, is shown to admins only and is deleted after
 * LoginAttemptRecorder::RETENTION_DAYS. A null identifier means the submitted
 * string was not an email address; it is never stored, so a password typed
 * into the email box cannot land here.
 */
#[ORM\Entity(repositoryClass: LoginAttemptRepository::class)]
#[ORM\Index(name: 'idx_login_attempt_occurred_at', columns: ['occurred_at'])]
class LoginAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        #[ORM\Column(length: 180, nullable: true)]
        private ?string $identifier,
        #[ORM\Column]
        private bool $succeeded,
        #[ORM\Column(length: 45, nullable: true)]
        private ?string $ip,
        ?\DateTimeImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function isSucceeded(): bool
    {
        return $this->succeeded;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
