<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

enum ActivityDuration: string
{
    case HalfDay = 'half_day';
    case FullDay = 'full_day';
    case Other = 'other';

    /**
     * "Other" is meaningless without the free text that says what it was, so
     * both write paths reject it: Activity::validateDurationOther() for the
     * single-activity form and the /edit screen, and BatchActivityFormType's
     * class-level callback for the batch one, which is backed by a DTO rather
     * than by the entity. Written here once so the two can't drift — the rule
     * belongs to the enum, and neither caller owns it more than the other.
     */
    public static function checkOtherIsSpecified(?self $duration, ?string $other, ExecutionContextInterface $context): void
    {
        if (self::Other === $duration && (null === $other || '' === trim($other))) {
            $context->buildViolation('Please specify the duration when choosing "Other".')
                ->atPath('durationOther')
                ->addViolation();
        }
    }

    public function label(): string
    {
        return match ($this) {
            self::HalfDay => 'Half day',
            self::FullDay => 'Full day',
            self::Other => 'Other',
        };
    }

    public function toDays(): float
    {
        return match ($this) {
            self::HalfDay => 0.5,
            self::FullDay => 1.0,
            // Free-text value (Activity::$durationOther) isn't parsed into a
            // day count — reports undercount these entries until duration
            // reporting is revisited.
            self::Other => 0.0,
        };
    }
}
