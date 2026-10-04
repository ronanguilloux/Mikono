<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * An entity that records which user added it and who last edited it.
 * App\Security\AuthorStamper calls recordAuthor() on flush; nothing else
 * should. See ADR 0043.
 */
interface Authored
{
    public function recordAuthor(User $user, bool $isNew): void;
}
