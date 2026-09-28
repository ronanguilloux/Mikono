<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BeneficiaryGroupRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Who a program serves — a group, never a person, so no personal data
 * (ADR 0034). One global list, like ActivityType, but not seeded: the VM
 * enters it. See ADR 0030.
 */
#[ORM\Entity(repositoryClass: BeneficiaryGroupRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_beneficiary_group_name', fields: ['name'])]
#[UniqueEntity('name')]
class BeneficiaryGroup
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
