<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ApiAccessRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One read through /api or an MCP tool, for the API access table on /usage. See
 * ADR 0050.
 *
 * The access log can't answer "what did Claude read": it sees POST /mcp and
 * nothing more. This can, and it stores the path alone, never the query
 * string, because a search term is often a person's name. Deleted after
 * ApiAccessRecorder::RETENTION_DAYS.
 */
#[ORM\Entity(repositoryClass: ApiAccessRepository::class)]
#[ORM\Index(name: 'idx_api_access_occurred_at', columns: ['occurred_at'])]
class ApiAccess
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        #[ORM\Column(length: 180)]
        private string $identifier,
        #[ORM\Column(length: 255)]
        private string $path,
        #[ORM\Column]
        private bool $viaMcp,
        ?\DateTimeImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function isViaMcp(): bool
    {
        return $this->viaMcp;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
