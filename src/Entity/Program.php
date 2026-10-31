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
use App\Repository\ProgramRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * What a project runs: an always-on programme (no dates), an open-ended one
 * (a start only) or a time-boxed one such as a medical camp. Offers a subset
 * of the global activity types — several programs can offer the same one.
 * See ADR 0030.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            order: ['name' => 'ASC'],
            parameters: [
                'name' => new QueryParameter(filter: new PartialSearchFilter(), property: 'name'),
                'project' => new QueryParameter(filter: new ExactFilter(), property: 'project'),
            ],
        ),
        new Get(),
    ],
    normalizationContext: ['groups' => ['program:read', 'api:ref']],
)]
#[ORM\Entity(repositoryClass: ProgramRepository::class)]
class Program implements Authored
{
    use AuthoredTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['program:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['program:read', 'api:ref'])]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Choose a project.')]
    #[Groups(['program:read'])]
    private ?Project $project = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['program:read'])]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['program:read'])]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['program:read'])]
    private ?string $suggestedRoles = null;

    /**
     * How many volunteers it needs; null when nobody has said. /matches sets
     * it against those booked from today (ADR 0049).
     */
    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    #[Groups(['program:read'])]
    private ?int $volunteersNeeded = null;

    /** Counts and groups, never names — see ADR 0030. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['program:read'])]
    private ?string $beneficiariesReached = null;

    /** @var Collection<int, ActivityType> */
    #[ORM\ManyToMany(targetEntity: ActivityType::class)]
    #[ORM\JoinTable(name: 'program_activity_type')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    #[Assert\Count(min: 1, minMessage: 'Choose at least one activity type.')]
    #[Groups(['program:read'])]
    private Collection $activityTypes;

    /**
     * What the program needs; /matches suggests the volunteers who hold any
     * of them. Optional — see ADR 0036 and ADR 0042.
     *
     * @var Collection<int, Skill>
     */
    #[ORM\ManyToMany(targetEntity: Skill::class)]
    #[ORM\JoinTable(name: 'program_skill')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    #[Groups(['program:read'])]
    private Collection $skills;

    /**
     * Who the program serves, picked from the list; beneficiariesReached
     * stays free text for counts. Optional — see ADR 0030.
     *
     * @var Collection<int, BeneficiaryGroup>
     */
    #[ORM\ManyToMany(targetEntity: BeneficiaryGroup::class)]
    #[ORM\JoinTable(name: 'program_beneficiary_group')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    #[Groups(['program:read'])]
    private Collection $beneficiaryGroups;

    #[ORM\Column]
    #[Groups(['program:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['program:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->activityTypes = new ArrayCollection();
        $this->skills = new ArrayCollection();
        $this->beneficiaryGroups = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if (null !== $this->startDate && null !== $this->endDate && $this->endDate < $this->startDate) {
            $context->buildViolation('The end date must be on or after the start date.')
                ->atPath('endDate')
                ->addViolation();
        }
    }

    /**
     * A missing bound is open: no dates at all means always-on.
     */
    public function covers(\DateTimeImmutable $date): bool
    {
        return (null === $this->startDate || $date >= $this->startDate)
            && (null === $this->endDate || $date <= $this->endDate);
    }

    public function offers(ActivityType $activityType): bool
    {
        return $this->activityTypes->contains($activityType);
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

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getSuggestedRoles(): ?string
    {
        return $this->suggestedRoles;
    }

    public function setSuggestedRoles(?string $suggestedRoles): static
    {
        $this->suggestedRoles = $suggestedRoles;

        return $this;
    }

    public function getBeneficiariesReached(): ?string
    {
        return $this->beneficiariesReached;
    }

    public function setBeneficiariesReached(?string $beneficiariesReached): static
    {
        $this->beneficiariesReached = $beneficiariesReached;

        return $this;
    }

    public function getVolunteersNeeded(): ?int
    {
        return $this->volunteersNeeded;
    }

    public function setVolunteersNeeded(?int $volunteersNeeded): static
    {
        $this->volunteersNeeded = $volunteersNeeded;

        return $this;
    }

    /** @return Collection<int, ActivityType> */
    public function getActivityTypes(): Collection
    {
        return $this->activityTypes;
    }

    public function addActivityType(ActivityType $activityType): static
    {
        if (!$this->activityTypes->contains($activityType)) {
            $this->activityTypes->add($activityType);
        }

        return $this;
    }

    public function removeActivityType(ActivityType $activityType): static
    {
        $this->activityTypes->removeElement($activityType);

        return $this;
    }

    /** @return Collection<int, Skill> */
    public function getSkills(): Collection
    {
        return $this->skills;
    }

    public function addSkill(Skill $skill): static
    {
        if (!$this->skills->contains($skill)) {
            $this->skills->add($skill);
        }

        return $this;
    }

    public function removeSkill(Skill $skill): static
    {
        $this->skills->removeElement($skill);

        return $this;
    }

    /** @return Collection<int, BeneficiaryGroup> */
    public function getBeneficiaryGroups(): Collection
    {
        return $this->beneficiaryGroups;
    }

    public function addBeneficiaryGroup(BeneficiaryGroup $group): static
    {
        if (!$this->beneficiaryGroups->contains($group)) {
            $this->beneficiaryGroups->add($group);
        }

        return $this;
    }

    public function removeBeneficiaryGroup(BeneficiaryGroup $group): static
    {
        $this->beneficiaryGroups->removeElement($group);

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
