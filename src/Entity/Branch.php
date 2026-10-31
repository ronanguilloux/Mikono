<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Attribute\Groups;
use App\Repository\BranchRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One of UCESCO's offices (Nairobi HQ, Mombasa, Samburu, Uganda, USA). Not
 * linked to any other entity yet; the five real rows are seeded by the
 * migration that creates the table, not by the fixtures.
 */
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['branch:read', 'api:ref']],
)]
#[ORM\Entity(repositoryClass: BranchRepository::class)]
class Branch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['branch:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['branch:read', 'api:ref'])]
    private string $name = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['branch:read'])]
    private string $physicalLocation = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['branch:read'])]
    private ?string $projectZones = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['branch:read'])]
    private ?string $programFocus = null;

    #[ORM\Column]
    #[Groups(['branch:read'])]
    private bool $isActive = true;

    /**
     * How many volunteers it can host at once; null when nobody has said.
     * The home screen's look-ahead draws it as a line (ADR 0049).
     */
    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    #[Groups(['branch:read'])]
    private ?int $capacity = null;

    #[ORM\Column]
    #[Groups(['branch:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['branch:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPhysicalLocation(): string
    {
        return $this->physicalLocation;
    }

    public function setPhysicalLocation(string $physicalLocation): static
    {
        $this->physicalLocation = $physicalLocation;

        return $this;
    }

    public function getProjectZones(): ?string
    {
        return $this->projectZones;
    }

    public function setProjectZones(?string $projectZones): static
    {
        $this->projectZones = $projectZones;

        return $this;
    }

    public function getProgramFocus(): ?string
    {
        return $this->programFocus;
    }

    public function setProgramFocus(?string $programFocus): static
    {
        $this->programFocus = $programFocus;

        return $this;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(?int $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
