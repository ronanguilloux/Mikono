<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UsageEventName;
use App\Repository\UsageEventRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One browser-side gesture that left no HTTP request behind, so the access
 * log behind /usage cannot see it. See ADR 0021.
 *
 * Deliberately three columns. There is no user, no IP, no session id and no
 * free-text context payload: ADR 0018 declined third-party analytics partly
 * because every page here is behind a login, so every event is an identified
 * colleague's behaviour. Recording only "a roster was copied at 14:32" keeps
 * this table non-personal, which is what lets it exist at all. Adding a user
 * column later is not a small change — it is a new data-protection decision
 * and needs its own ADR.
 *
 * The name is stored as a plain string like every other enum in this app, so
 * the column stays portable off SQLite.
 */
#[ORM\Entity(repositoryClass: UsageEventRepository::class)]
#[ORM\Index(name: 'idx_usage_event_name', columns: ['name'])]
class UsageEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, enumType: UsageEventName::class)]
    private UsageEventName $name;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    public function __construct(UsageEventName $name, ?\DateTimeImmutable $occurredAt = null)
    {
        $this->name = $name;
        $this->occurredAt = $occurredAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): UsageEventName
    {
        return $this->name;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
