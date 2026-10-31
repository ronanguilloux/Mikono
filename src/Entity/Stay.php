<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Attribute\Groups;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use App\Repository\StayRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A volunteer's time at one branch, first day to last day inclusive. A
 * volunteer can come back later, to the same branch or another, as a new
 * stay; two stays of one volunteer never overlap (StayController refuses it),
 * so a day belongs to at most one of them. Every activity is tied to the stay
 * covering its date, which is how an activity knows its branch.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            order: ['startDate' => 'DESC'],
            parameters: [
                'volunteer' => new QueryParameter(filter: new ExactFilter(), property: 'volunteer'),
                'branch' => new QueryParameter(filter: new ExactFilter(), property: 'branch'),
                'startDate' => new QueryParameter(filter: new DateFilter(), property: 'startDate', constraints: new Assert\All(new Assert\Date())),
                'endDate' => new QueryParameter(filter: new DateFilter(), property: 'endDate', constraints: new Assert\All(new Assert\Date())),
            ],
        ),
        new Get(),
    ],
    normalizationContext: ['groups' => ['stay:read', 'api:ref']],
)]
#[ORM\Entity(repositoryClass: StayRepository::class)]
class Stay implements Authored
{
    use AuthoredTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['stay:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Volunteer::class, inversedBy: 'stays')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['stay:read'])]
    private ?Volunteer $volunteer = null;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Choose a branch.')]
    #[Groups(['stay:read'])]
    private ?Branch $branch = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull(message: 'Enter the first day of the stay.')]
    #[Groups(['stay:read'])]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull(message: 'Enter the last day of the stay.')]
    #[Assert\GreaterThanOrEqual(propertyPath: 'startDate', message: 'A stay cannot end before it starts.')]
    #[Groups(['stay:read'])]
    private ?\DateTimeImmutable $endDate = null;

    /**
     * Removed with the stay by the ORM: SQLite leaves foreign keys unenforced
     * here, so the column's ON DELETE CASCADE alone would orphan them.
     *
     * @var Collection<int, Achievement>
     */
    #[ORM\OneToMany(targetEntity: Achievement::class, mappedBy: 'stay', cascade: ['remove'])]
    #[ORM\OrderBy(['achievedOn' => 'DESC', 'id' => 'DESC'])]
    #[Groups(['stay:read'])]
    private Collection $achievements;

    #[ORM\Column]
    #[Groups(['stay:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['stay:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->achievements = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVolunteer(): ?Volunteer
    {
        return $this->volunteer;
    }

    public function setVolunteer(?Volunteer $volunteer): static
    {
        $this->volunteer = $volunteer;
        // Keeps Volunteer::getStayCovering() right before any reload.
        $volunteer?->addStay($this);

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

    /** Whether $day (a calendar day, midnight) falls within this stay. */
    public function covers(\DateTimeImmutable $day): bool
    {
        return null !== $this->startDate && null !== $this->endDate
            && $this->startDate <= $day && $day <= $this->endDate;
    }

    /** Calendar days of this stay up to $today inclusive; 0 before it starts. */
    public function getDaysOnSite(\DateTimeImmutable $today): int
    {
        if (null === $this->startDate || null === $this->endDate || $this->startDate > $today) {
            return 0;
        }

        return min($this->endDate, $today)->diff($this->startDate)->days + 1;
    }

    public function overlaps(self $other): bool
    {
        return null !== $this->startDate && null !== $this->endDate
            && null !== $other->startDate && null !== $other->endDate
            && $this->startDate <= $other->endDate && $other->startDate <= $this->endDate;
    }

    /** @return Collection<int, Achievement> */
    public function getAchievements(): Collection
    {
        return $this->achievements;
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
