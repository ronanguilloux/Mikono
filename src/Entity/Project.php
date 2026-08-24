<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProjectLocation;
use App\Enum\ProjectOwnership;
use App\Repository\ProjectRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[Assert\Callback('validatePartnerOrganizationName')]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 20, enumType: ProjectLocation::class)]
    #[Assert\NotNull]
    private ?ProjectLocation $location = null;

    #[ORM\Column(length: 20, enumType: ProjectOwnership::class)]
    #[Assert\NotNull]
    private ?ProjectOwnership $ownership = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $partnerOrganizationName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

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

    public function validatePartnerOrganizationName(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if (ProjectOwnership::Partner === $this->ownership && !$this->partnerOrganizationName) {
            $context->buildViolation('Enter the partner organization\'s name for a partner project.')
                ->atPath('partnerOrganizationName')
                ->addViolation();
        }
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

    public function getLocation(): ?ProjectLocation
    {
        return $this->location;
    }

    public function setLocation(?ProjectLocation $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getOwnership(): ?ProjectOwnership
    {
        return $this->ownership;
    }

    public function setOwnership(?ProjectOwnership $ownership): static
    {
        $this->ownership = $ownership;

        return $this;
    }

    public function getPartnerOrganizationName(): ?string
    {
        return $this->partnerOrganizationName;
    }

    public function setPartnerOrganizationName(?string $partnerOrganizationName): static
    {
        $this->partnerOrganizationName = $partnerOrganizationName;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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
