<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * The two columns behind Authored. Null means "before ADR 0043", "written
 * from the console" or "that user was deleted" — UserRepository::
 * detachAuthorship() clears them, since SQLite runs without foreign keys
 * here.
 */
trait AuthoredTrait
{
    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $updatedBy = null;

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function recordAuthor(User $user, bool $isNew): void
    {
        if ($isNew) {
            $this->createdBy = $user;
        }
        $this->updatedBy = $user;
    }
}
