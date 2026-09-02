<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\ActivityType;
use App\Entity\Escort;
use App\Entity\Project;
use App\Entity\Volunteer;
use App\Enum\ActivityDuration;

/**
 * Backing model for BatchActivityFormType — one submission fans out into
 * one Activity per selected volunteer, all sharing these fields.
 */
final class BatchActivityInput
{
    public ?\DateTimeImmutable $date = null;

    public ?Project $project = null;

    public ?ActivityType $activityType = null;

    public ?ActivityDuration $duration = null;

    public ?string $durationOther = null;

    /** @var list<Escort> */
    public array $escorts = [];

    /** @var list<Volunteer> */
    public array $volunteers = [];

    public ?string $notes = null;
}
