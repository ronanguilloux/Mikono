<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AchievementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Something a volunteer left behind during a stay — "Built a library" — as
 * opposed to an activity, which is one day's log entry. Its day falls within
 * the stay and its project is at the stay's branch; AchievementController
 * checks both on save, and stay and project edits re-check them. See ADR 0038.
 */
#[ORM\Entity(repositoryClass: AchievementRepository::class)]
class Achievement implements Authored
{
    use AuthoredTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Stay::class, inversedBy: 'achievements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Stay $stay = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Choose the project it was part of.')]
    private ?Project $project = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Say what was achieved.')]
    #[Assert\Length(max: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull(message: 'Enter the day it was achieved.')]
    private ?\DateTimeImmutable $achievedOn = null;

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

    public function getStay(): ?Stay
    {
        return $this->stay;
    }

    public function setStay(?Stay $stay): static
    {
        $this->stay = $stay;

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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function getAchievedOn(): ?\DateTimeImmutable
    {
        return $this->achievedOn;
    }

    public function setAchievedOn(?\DateTimeImmutable $achievedOn): static
    {
        $this->achievedOn = $achievedOn;

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
