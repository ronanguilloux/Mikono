<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BranchRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One of UCESCO's offices (Nairobi HQ, Mombasa, Samburu, Uganda, USA). Not
 * linked to any other entity yet; the five real rows are seeded by the
 * migration that creates the table, not by the fixtures.
 */
#[ORM\Entity(repositoryClass: BranchRepository::class)]
class Branch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $physicalLocation = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $projectZones = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $programFocus = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
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
