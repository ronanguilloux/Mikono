<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\IndicatorRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What an achievement counts — "Children reached", "Meals served" — so impact
 * can be summed rather than read from free text. A unit, never a person, so
 * no personal data (ADR 0034). One global list, unseeded like
 * BeneficiaryGroup: the VM enters it. See ADR 0047.
 */
#[ORM\Entity(repositoryClass: IndicatorRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_indicator_name', fields: ['name'])]
#[UniqueEntity('name')]
class Indicator
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }
}
