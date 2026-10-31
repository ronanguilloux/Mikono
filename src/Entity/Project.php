<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Attribute\Groups;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use App\Enum\ProjectOwnership;
use App\Repository\ProjectRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            order: ['name' => 'ASC'],
            parameters: [
                'name' => new QueryParameter(filter: new PartialSearchFilter(), property: 'name'),
                'branch' => new QueryParameter(filter: new ExactFilter(), property: 'branch'),
            ],
        ),
        new Get(),
    ],
    normalizationContext: ['groups' => ['project:read', 'api:ref']],
)]
#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[Assert\Callback('validatePartnerOrganizationName')]
class Project implements Authored
{
    use AuthoredTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['project:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['project:read', 'api:ref'])]
    private string $name = '';

    /**
     * Every activity logged at this project belongs to a stay at this same
     * branch: ActivityController refuses anything else, and ProjectController
     * refuses moving a project away from its activities' stays. See ADR 0027.
     */
    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Choose a branch.')]
    #[Groups(['project:read'])]
    private ?Branch $branch = null;

    #[ORM\Column(length: 20, enumType: ProjectOwnership::class)]
    #[Assert\NotNull]
    #[Groups(['project:read'])]
    private ?ProjectOwnership $ownership = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['project:read'])]
    private ?string $partnerOrganizationName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['project:read'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['project:read'])]
    private bool $isActive = true;

    #[ORM\Column]
    #[Groups(['project:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['project:read'])]
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

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): static
    {
        $this->branch = $branch;

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
