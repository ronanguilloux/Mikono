<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A volunteer's picture, already re-encoded as a small JPEG with no metadata.
 * Its own table so that loading a volunteer never loads the bytes: Volunteer
 * holds the owning, lazy side. See ADR 0032.
 */
#[ORM\Entity]
class VolunteerPhoto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var string|resource DBAL hands a blob back as a stream */
    #[ORM\Column(type: 'blob')]
    private mixed $bytes;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $jpeg)
    {
        $this->bytes = $jpeg;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBytes(): string
    {
        if (is_resource($this->bytes)) {
            $this->bytes = (string) stream_get_contents($this->bytes, offset: 0);
        }
        \assert(is_string($this->bytes));

        return $this->bytes;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
