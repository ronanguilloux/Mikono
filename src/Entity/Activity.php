<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ActivityDuration;
use App\Repository\ActivityRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ActivityRepository::class)]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $date = null;

    #[ORM\ManyToOne(targetEntity: Volunteer::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Volunteer $volunteer = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: ActivityType::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?ActivityType $activityType = null;

    #[ORM\Column(length: 20, enumType: ActivityDuration::class)]
    #[Assert\NotNull]
    private ?ActivityDuration $duration = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $durationOther = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $loggedBy = null;

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

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(?\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getVolunteer(): ?Volunteer
    {
        return $this->volunteer;
    }

    public function setVolunteer(?Volunteer $volunteer): static
    {
        $this->volunteer = $volunteer;

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

    public function getActivityType(): ?ActivityType
    {
        return $this->activityType;
    }

    public function setActivityType(?ActivityType $activityType): static
    {
        $this->activityType = $activityType;

        return $this;
    }

    public function getDuration(): ?ActivityDuration
    {
        return $this->duration;
    }

    public function setDuration(?ActivityDuration $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getDurationOther(): ?string
    {
        return $this->durationOther;
    }

    public function setDurationOther(?string $durationOther): static
    {
        $this->durationOther = $durationOther;

        return $this;
    }

    #[Assert\Callback]
    public function validateDurationOther(ExecutionContextInterface $context): void
    {
        if (ActivityDuration::Other === $this->duration && (null === $this->durationOther || '' === trim($this->durationOther))) {
            $context->buildViolation('Please specify the duration when choosing "Other".')
                ->atPath('durationOther')
                ->addViolation();
        }
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getLoggedBy(): ?User
    {
        return $this->loggedBy;
    }

    public function setLoggedBy(?User $loggedBy): static
    {
        $this->loggedBy = $loggedBy;

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
