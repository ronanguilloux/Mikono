<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\VolunteerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VolunteerRepository::class)]
class Volunteer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $firstName = '';

    // Optional on purpose: the VM's rosters name volunteers by first name
    // only, and requiring a surname would block recording someone she has
    // just met. See ADR 0014.
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /**
     * Newest first. Whether a volunteer is active is read from these, never
     * stored: a stay ending is what "finished their stint" means.
     *
     * @var Collection<int, Stay>
     */
    #[ORM\OneToMany(targetEntity: Stay::class, mappedBy: 'volunteer', cascade: ['remove'])]
    #[ORM\OrderBy(['startDate' => 'DESC'])]
    private Collection $stays;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->stays = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        // '' and null both mean "no surname recorded"; store one of them.
        $this->lastName = ('' === $lastName) ? null : $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . ($this->lastName ?? ''));
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
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

    /** @return Collection<int, Stay> */
    public function getStays(): Collection
    {
        return $this->stays;
    }

    public function addStay(Stay $stay): static
    {
        if (!$this->stays->contains($stay)) {
            $this->stays->add($stay);
            $stay->setVolunteer($this);
        }

        return $this;
    }

    public function removeStay(Stay $stay): static
    {
        $this->stays->removeElement($stay);

        return $this;
    }

    /** The stay covering $day (a calendar day, midnight), if any. Stays never overlap. */
    public function getStayCovering(\DateTimeImmutable $day): ?Stay
    {
        foreach ($this->stays as $stay) {
            if ($stay->covers($day)) {
                return $stay;
            }
        }

        return null;
    }

    /** Active means a stay covers today. See ADR 0026. */
    public function isActive(): bool
    {
        return null !== $this->getStayCovering(new \DateTimeImmutable('today'));
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
