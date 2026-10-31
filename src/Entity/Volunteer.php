<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use App\Api\VolunteerListProvider;
use ApiPlatform\Metadata\QueryParameter;
use App\Enum\Gender;
use App\Enum\VolunteerStatus;
use App\Repository\VolunteerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            provider: VolunteerListProvider::class,
            parameters: [
                'search' => new QueryParameter(description: 'Part of a full name or email'),
                'status' => new QueryParameter(description: 'present, upcoming, past or none'),
                'branch' => new QueryParameter(description: 'Branch id'),
                'skill' => new QueryParameter(description: 'Skill id'),
                'source' => new QueryParameter(description: 'Source id'),
            ],
        ),
        new Get(),
    ],
    normalizationContext: ['groups' => ['volunteer:read', 'api:ref']],
)]
#[ORM\Entity(repositoryClass: VolunteerRepository::class)]
class Volunteer implements Authored
{
    use AuthoredTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['volunteer:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['volunteer:read'])]
    private string $firstName = '';

    // Optional on purpose: the VM's rosters name volunteers by first name
    // only, and requiring a surname would block recording someone she has
    // just met. See ADR 0014.
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email]
    #[Groups(['volunteer:read'])]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $phone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $notes = null;

    // The profile fields below are all optional: the VM records people she
    // has only just met, and old rows have no truthful value to backfill.
    // The profile page nudges instead. See ADR 0032.
    #[ORM\Column(length: 2, nullable: true)]
    #[Assert\Country]
    #[Groups(['volunteer:read'])]
    private ?string $nationality = null;

    #[ORM\Column(length: 2, nullable: true)]
    #[Assert\Country]
    #[Groups(['volunteer:read'])]
    private ?string $countryOfResidence = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Assert\LessThan('today', message: 'A date of birth must be in the past.')]
    #[Groups(['volunteer:read'])]
    private ?\DateTimeImmutable $dateOfBirth = null;

    // Sensitive (DPA s.2), for accommodation pairing only; null means not
    // recorded, unlike PreferNotToSay. See ADR 0037.
    #[ORM\Column(length: 20, nullable: true, enumType: Gender::class)]
    #[Groups(['volunteer:read'])]
    private ?Gender $gender = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $profession = null;

    /**
     * Picked from the global list so they can be filtered and matched against
     * a program's; the free-text field they replaced is gone. See ADR 0036.
     *
     * @var Collection<int, Skill>
     */
    #[ORM\ManyToMany(targetEntity: Skill::class)]
    #[ORM\JoinTable(name: 'volunteer_skill')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    #[Groups(['volunteer:read'])]
    private Collection $skills;

    /**
     * Where they heard of UCESCO, possibly several channels. Recorded once per
     * volunteer, not per stay. See ADR 0041.
     *
     * @var Collection<int, Source>
     */
    #[ORM\ManyToMany(targetEntity: Source::class)]
    #[ORM\JoinTable(name: 'volunteer_source')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    #[Groups(['volunteer:read'])]
    private Collection $sources;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $interests = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $emergencyContacts = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $accommodationPreference = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $pickupAirport = null;

    // Free text until someone needs to list a supervisor's volunteers; it may
    // become a User or an Escort then. See ADR 0032.
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $supervisor = null;

    // Only ever written through PassportNumberCipher, never from a form or a
    // fixture: a leaked .db or backup must not carry the number. See ADR 0033.
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $passportNumberCiphertext = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?\DateTimeImmutable $passportExpiresOn = null;

    // A pointer to the folder holding their documents; the app never fetches
    // it. See ADR 0046.
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(requireTld: true)]
    #[Groups(['volunteer:read'])]
    private ?string $driveFolderUrl = null;

    // The intro form's other answers, imported from the legacy workbook and
    // editable since. Text, not varchar: some answers run to pages.
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $instagram = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $facebook = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $twitter = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $tiktok = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $linkedin = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $hobbies = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $freeTimeActivities = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $motivation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $skillsDescription = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $employer = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $education = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $favouriteFood = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $accommodationAddress = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?bool $experienceWithChildren = null;

    // Every legacy-workbook value that had no typed home (roster lines, partial
    // dates, unmatched text), kept verbatim so the import loses nothing.
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['volunteer:read'])]
    private ?string $legacyRecord = null;

    // Owning side, so Doctrine loads it lazily and list pages never read the
    // bytes; an inverse one-to-one would always be fetched.
    #[ORM\OneToOne(targetEntity: VolunteerPhoto::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?VolunteerPhoto $photo = null;

    /**
     * Newest first. A volunteer's status is read from these, never stored:
     * a stay ending is what "finished their stint" means.
     *
     * @var Collection<int, Stay>
     */
    #[ORM\OneToMany(targetEntity: Stay::class, mappedBy: 'volunteer', cascade: ['remove'])]
    #[ORM\OrderBy(['startDate' => 'DESC'])]
    #[Groups(['volunteer:read'])]
    private Collection $stays;

    #[ORM\Column]
    #[Groups(['volunteer:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['volunteer:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->stays = new ArrayCollection();
        $this->skills = new ArrayCollection();
        $this->sources = new ArrayCollection();
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

    #[Groups(['volunteer:read', 'api:ref'])]
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

    public function getGender(): ?Gender
    {
        return $this->gender;
    }

    public function setGender(?Gender $gender): static
    {
        $this->gender = $gender;

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

    /** @return Collection<int, Source> */
    public function getSources(): Collection
    {
        return $this->sources;
    }

    public function addSource(Source $source): static
    {
        if (!$this->sources->contains($source)) {
            $this->sources->add($source);
        }

        return $this;
    }

    public function removeSource(Source $source): static
    {
        $this->sources->removeElement($source);

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

    public function getAccommodationPreference(): ?string
    {
        return $this->accommodationPreference;
    }

    public function setAccommodationPreference(?string $accommodationPreference): static
    {
        $this->accommodationPreference = self::nullIfBlank($accommodationPreference);

        return $this;
    }

    public function getPickupAirport(): ?string
    {
        return $this->pickupAirport;
    }

    public function setPickupAirport(?string $pickupAirport): static
    {
        $this->pickupAirport = self::nullIfBlank($pickupAirport);

        return $this;
    }

    public function getSupervisor(): ?string
    {
        return $this->supervisor;
    }

    public function setSupervisor(?string $supervisor): static
    {
        $this->supervisor = self::nullIfBlank($supervisor);

        return $this;
    }

    public function getPassportNumberCiphertext(): ?string
    {
        return $this->passportNumberCiphertext;
    }

    public function setPassportNumberCiphertext(?string $passportNumberCiphertext): static
    {
        $this->passportNumberCiphertext = $passportNumberCiphertext;

        return $this;
    }

    public function getPassportExpiresOn(): ?\DateTimeImmutable
    {
        return $this->passportExpiresOn;
    }

    public function setPassportExpiresOn(?\DateTimeImmutable $passportExpiresOn): static
    {
        $this->passportExpiresOn = $passportExpiresOn;

        return $this;
    }

    public function isPassportExpired(\DateTimeImmutable $today): bool
    {
        return null !== $this->passportExpiresOn && $this->passportExpiresOn < $today;
    }

    /** Kenya wants six months of validity left on entry. */
    public function isPassportExpiringSoon(\DateTimeImmutable $today): bool
    {
        return null !== $this->passportExpiresOn
            && !$this->isPassportExpired($today)
            && $this->passportExpiresOn < $today->modify('+6 months');
    }

    public function getDriveFolderUrl(): ?string
    {
        return $this->driveFolderUrl;
    }

    public function setDriveFolderUrl(?string $driveFolderUrl): static
    {
        $this->driveFolderUrl = self::nullIfBlank($driveFolderUrl);

        return $this;
    }

    /**
     * True while any profile field is still unrecorded; the profile page nudges on it.
     * The second-slice fields (accommodation, airport, supervisor,
     * passport) are left out: they don't apply to every volunteer — a Kenyan
     * volunteer has no pickup airport — so counting them would make the pill
     * permanent.
     */
    public function isProfileIncomplete(): bool
    {
        return in_array(null, [
            $this->nationality,
            $this->countryOfResidence,
            $this->dateOfBirth,
            $this->profession,
            $this->interests,
            $this->emergencyContacts,
        ], true) || $this->skills->isEmpty();
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

    /**
     * The first rule that matches wins: a stay covering $today, then one
     * starting after it (even with past stays behind it), then any stay.
     * VolunteerRepository ranks the same rule in SQL. See ADR 0026.
     */
    /** getStatus() as of today, for the API: a derived value, never a column. */
    #[Groups(['volunteer:read'])]
    #[SerializedName('status')]
    public function getCurrentStatus(): VolunteerStatus
    {
        return $this->getStatus(new \DateTimeImmutable('today'));
    }

    #[Groups(['volunteer:read'])]
    public function getHasPhoto(): bool
    {
        return null !== $this->photo;
    }

    public function getStatus(\DateTimeImmutable $today): VolunteerStatus
    {
        if (null !== $this->getStayCovering($today)) {
            return VolunteerStatus::Present;
        }
        if ($this->stays->exists(static fn(int|string $key, Stay $stay): bool => $stay->getStartDate() > $today)) {
            return VolunteerStatus::Upcoming;
        }

        return $this->stays->isEmpty() ? VolunteerStatus::NoStay : VolunteerStatus::Past;
    }

    /** Present today. See ADR 0026. */
    public function isActive(): bool
    {
        return VolunteerStatus::Present === $this->getStatus(new \DateTimeImmutable('today'));
    }

    /**
     * Calendar days covered by this volunteer's stays, up to $today inclusive.
     * Stays never overlap (StayController refuses it), so the sum is exact.
     * No column: derived from stays, like getStatus(). See ADR 0026.
     */
    public function getDaysOnSite(\DateTimeImmutable $today): int
    {
        $days = 0;
        foreach ($this->stays as $stay) {
            $days += $stay->getDaysOnSite($today);
        }

        return $days;
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

    public function getInstagram(): ?string
    {
        return $this->instagram;
    }

    /**
     * The handle when the value names exactly one account — bare, `@handle`
     * or a profile URL — so the profile can link it. Anything else (two
     * handles, a name, an email) is null and stays text.
     */
    public function getInstagramHandle(): ?string
    {
        return null !== $this->instagram
            && 1 === preg_match('~^(?:@|(?:https?://)?(?:www\.)?instagram\.com/)?([a-z0-9._]{1,30})/?(?:\?\S*)?$~i', trim($this->instagram), $match)
            ? $match[1]
            : null;
    }

    public function setInstagram(?string $instagram): static
    {
        $this->instagram = self::nullIfBlank($instagram);

        return $this;
    }

    public function getFacebook(): ?string
    {
        return $this->facebook;
    }

    public function setFacebook(?string $facebook): static
    {
        $this->facebook = self::nullIfBlank($facebook);

        return $this;
    }

    public function getTwitter(): ?string
    {
        return $this->twitter;
    }

    public function setTwitter(?string $twitter): static
    {
        $this->twitter = self::nullIfBlank($twitter);

        return $this;
    }

    public function getTiktok(): ?string
    {
        return $this->tiktok;
    }

    public function setTiktok(?string $tiktok): static
    {
        $this->tiktok = self::nullIfBlank($tiktok);

        return $this;
    }

    public function getLinkedin(): ?string
    {
        return $this->linkedin;
    }

    public function setLinkedin(?string $linkedin): static
    {
        $this->linkedin = self::nullIfBlank($linkedin);

        return $this;
    }

    public function getHobbies(): ?string
    {
        return $this->hobbies;
    }

    public function setHobbies(?string $hobbies): static
    {
        $this->hobbies = self::nullIfBlank($hobbies);

        return $this;
    }

    public function getFreeTimeActivities(): ?string
    {
        return $this->freeTimeActivities;
    }

    public function setFreeTimeActivities(?string $freeTimeActivities): static
    {
        $this->freeTimeActivities = self::nullIfBlank($freeTimeActivities);

        return $this;
    }

    public function getMotivation(): ?string
    {
        return $this->motivation;
    }

    public function setMotivation(?string $motivation): static
    {
        $this->motivation = self::nullIfBlank($motivation);

        return $this;
    }

    public function getSkillsDescription(): ?string
    {
        return $this->skillsDescription;
    }

    public function setSkillsDescription(?string $skillsDescription): static
    {
        $this->skillsDescription = self::nullIfBlank($skillsDescription);

        return $this;
    }

    public function getEmployer(): ?string
    {
        return $this->employer;
    }

    public function setEmployer(?string $employer): static
    {
        $this->employer = self::nullIfBlank($employer);

        return $this;
    }

    public function getEducation(): ?string
    {
        return $this->education;
    }

    public function setEducation(?string $education): static
    {
        $this->education = self::nullIfBlank($education);

        return $this;
    }

    public function getFavouriteFood(): ?string
    {
        return $this->favouriteFood;
    }

    public function setFavouriteFood(?string $favouriteFood): static
    {
        $this->favouriteFood = self::nullIfBlank($favouriteFood);

        return $this;
    }

    public function getAccommodationAddress(): ?string
    {
        return $this->accommodationAddress;
    }

    public function setAccommodationAddress(?string $accommodationAddress): static
    {
        $this->accommodationAddress = self::nullIfBlank($accommodationAddress);

        return $this;
    }

    public function getLegacyRecord(): ?string
    {
        return $this->legacyRecord;
    }

    public function setLegacyRecord(?string $legacyRecord): static
    {
        $this->legacyRecord = self::nullIfBlank($legacyRecord);

        return $this;
    }

    public function getExperienceWithChildren(): ?bool
    {
        return $this->experienceWithChildren;
    }

    public function setExperienceWithChildren(?bool $experienceWithChildren): static
    {
        $this->experienceWithChildren = $experienceWithChildren;

        return $this;
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
