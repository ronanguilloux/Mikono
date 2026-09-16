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

    // The profile fields below are all optional: the VM records people she
    // has only just met, and old rows have no truthful value to backfill.
    // The profile page nudges instead. See ADR 0032.
    #[ORM\Column(length: 2, nullable: true)]
    #[Assert\Country]
    private ?string $nationality = null;

    #[ORM\Column(length: 2, nullable: true)]
    #[Assert\Country]
    private ?string $countryOfResidence = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Assert\LessThan('today', message: 'A date of birth must be in the past.')]
    private ?\DateTimeImmutable $dateOfBirth = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profession = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $skills = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $interests = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $emergencyContacts = null;

    // Owning side, so Doctrine loads it lazily and list pages never read the
    // bytes; an inverse one-to-one would always be fetched.
    #[ORM\OneToOne(targetEntity: VolunteerPhoto::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?VolunteerPhoto $photo = null;

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

    public function getNationality(): ?string
    {
        return $this->nationality;
    }

    public function setNationality(?string $nationality): static
    {
        $this->nationality = self::nullIfBlank($nationality);

        return $this;
    }

    public function getCountryOfResidence(): ?string
    {
        return $this->countryOfResidence;
    }

    public function setCountryOfResidence(?string $countryOfResidence): static
    {
        $this->countryOfResidence = self::nullIfBlank($countryOfResidence);

        return $this;
    }

    public function getDateOfBirth(): ?\DateTimeImmutable
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(?\DateTimeImmutable $dateOfBirth): static
    {
        $this->dateOfBirth = $dateOfBirth;

        return $this;
    }

    public function getProfession(): ?string
    {
        return $this->profession;
    }

    public function setProfession(?string $profession): static
    {
        $this->profession = self::nullIfBlank($profession);

        return $this;
    }

    public function getSkills(): ?string
    {
        return $this->skills;
    }

    public function setSkills(?string $skills): static
    {
        $this->skills = self::nullIfBlank($skills);

        return $this;
    }

    public function getInterests(): ?string
    {
        return $this->interests;
    }

    public function setInterests(?string $interests): static
    {
        $this->interests = self::nullIfBlank($interests);

        return $this;
    }

    public function getEmergencyContacts(): ?string
    {
        return $this->emergencyContacts;
    }

    public function setEmergencyContacts(?string $emergencyContacts): static
    {
        $this->emergencyContacts = self::nullIfBlank($emergencyContacts);

        return $this;
    }

    /** True while any profile field is still unrecorded; the profile page nudges on it. */
    public function isProfileIncomplete(): bool
    {
        return in_array(null, [
            $this->nationality,
            $this->countryOfResidence,
            $this->dateOfBirth,
            $this->profession,
            $this->skills,
            $this->interests,
            $this->emergencyContacts,
        ], true);
    }

    public function getPhoto(): ?VolunteerPhoto
    {
        return $this->photo;
    }

    public function setPhoto(?VolunteerPhoto $photo): static
    {
        $this->photo = $photo;

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

    /**
     * The branch of the stay covering today, else of the latest stay (stays
     * are ordered newest first). No column: see ADR 0026.
     */
    public function getBranchOfAttachment(): ?Branch
    {
        $stay = $this->getStayCovering(new \DateTimeImmutable('today')) ?? $this->stays->first();

        return false === $stay ? null : $stay->getBranch();
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

    private static function nullIfBlank(?string $value): ?string
    {
        return (null === $value || '' === trim($value)) ? null : $value;
    }
}
