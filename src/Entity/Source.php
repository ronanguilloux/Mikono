<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SourceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Where a volunteer heard of UCESCO — a recruitment channel. A global list
 * like Skill, seeded by its migration with the VM's channels, so a new one is
 * a row, not a deploy. See ADR 0041.
 */
#[ORM\Entity(repositoryClass: SourceRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_source_name', fields: ['name'])]
#[UniqueEntity('name')]
class Source
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
